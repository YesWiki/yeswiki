<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Command\DbCommand;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/**
 * The queue's table is the running instance's state, not the wiki's content: a backup carries none of its rows, and a restore neither drops, replaces nor fills it.
 *
 * So are the rows of the Journal's `queue` channel, the consumer's runs and passes: a backup
 * leaves them out of the Journal it carries, and a restore puts the live ones back with their ids,
 * the open row of the pass the restore itself runs in among them.
 *
 * Otherwise a restored copy holds the backup's own message as "running", replays what was
 * pending when the backup was taken, acknowledges the restore by an id in a table it has just
 * replaced, and sends the scheduled tasks back to an older anchor, which runs them again.
 */
class BackupsLeaveTheQueueAloneTest extends YesWikiTestCase
{
    private const PREFIX = 'ywqbak_';

    private ?string $file = null;
    private ?string $lockDir = null;
    private MockClock $clock;

    /** @var (\Closure(string): void)|null told of each statement the probe wiki's connection has run */
    private ?\Closure $afterQuery = null;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
    private JournalSchema $journalSchema;
    private Journal $journal;
    private QueueRecorder $recorder;

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->dropProbeTables();
        }
        unset($this->recorder, $this->journal, $this->journalSchema, $this->transport, $this->schema, $this->db);
        if ($this->file !== null) {
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($this->file . $suffix);
            }
        }
        if ($this->lockDir !== null) {
            foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
                @unlink($lock);
            }
            @rmdir($this->lockDir . '/cache');
            @rmdir($this->lockDir);
            $this->lockDir = null;
        }
        parent::tearDown();
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testADumpCarriesNoQueueTable(array $params): void
    {
        $this->open($params);
        $this->send('pending at backup time');

        $dump = $this->db->dumper()->dump();

        $this->assertSame('', $dump['error']);
        $this->assertSame([self::PREFIX . 'pages', self::PREFIX . 'triples'], DumpRewriter::tables($dump['sql']));
        $this->assertStringNotContainsString(self::PREFIX . 'queue', $dump['sql']);
    }

    /**
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testARestoreLeavesTheQueueAsItIs(array $params, string $restore): void
    {
        $this->open($params);
        $before = $this->send('pending at backup time');
        $dump = $this->db->dumper()->dump()['sql'];
        $after = $this->send('sent after the backup');
        $this->db->query('UPDATE ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . " SET body = 'after'");

        $this->db->{$restore}($dump);

        $this->assertSame('before', $this->db->scalar('SELECT body FROM ' . $this->db->quoteIdentifier(self::PREFIX . 'pages')), 'the content is restored');
        $this->assertSame([$before, $after], $this->pendingIds(), 'the queue is the running one, with the same ids');
    }

    /**
     * A backup taken before the queue was left out still holds its rows: they are not replayed.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testAnOlderBackupsQueueRowsAreNotReplayed(array $params): void
    {
        $this->open($params);
        $live = $this->send('live');
        $queue = $this->schema->table();
        $dump = $this->db->dumper()->dump()['sql']
            . "\n" . $this->db->schema()->getTableSchema($queue) . ";\n"
            . 'INSERT INTO ' . $this->db->quoteIdentifier($queue)
            . ' (queue_name, message_type, body, headers, created_at, available_at)'
            . " VALUES ('default', 'Old', 'replayed', '[]', '2026-10-01 00:00:00', '2026-10-01 00:00:00');\n";

        $this->db->restoreStagedFromDump($dump);

        $this->assertSame([$live], $this->pendingIds());
    }

    /**
     * Restoring into a database that never had a queue (a fresh install from a backup) leaves one ready.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testARestoreIntoADatabaseWithoutQueueCreatesIt(array $params): void
    {
        $this->open($params);
        $dump = $this->db->dumper()->dump()['sql'];
        $this->schema->drop();
        $this->assertFalse($this->schema->exists());

        $this->db->restoreStagedFromDump($dump);

        $this->assertTrue($this->schema->exists());
        $this->assertSame([], $this->pendingIds());
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testADumpCarriesNoQueueJournalRow(array $params): void
    {
        $this->open($params);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $this->recordRun('RunBeforeTheBackup');
        $this->recorder->passStarted(QueueConsumerMode::Api, null, false);

        $dump = $this->db->dumper()->dump();

        $this->assertSame('', $dump['error']);
        $this->assertContains($this->journalSchema->table(), DumpRewriter::tables($dump['sql']));
        $this->assertStringContainsString('backup.audited', $dump['sql']);
        $this->assertStringNotContainsString('RunBeforeTheBackup', $dump['sql']);
        $this->assertStringNotContainsString('queue.pass', $dump['sql']);
    }

    /** The same through core:exportdb, whose mysqldump applies a `--where` to every table it is given: the Journal is dumped by a call of its own. */
    public function testTheDumpToolCarriesNoQueueJournalRow(): void
    {
        $params = $this->openMysql();
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $this->recordRun('RunBeforeTheBackup');

        $dump = $this->exportDb($params);

        $this->assertTrue(DbCommand::isDump($dump));
        $this->assertSame([self::PREFIX . 'pages', self::PREFIX . 'triples', $this->journalSchema->table()], DumpRewriter::tables($dump), 'the content tables, then the Journal its own call appends');
        $this->assertStringContainsString('backup.audited', $dump);
        $this->assertStringNotContainsString('RunBeforeTheBackup', $dump);
    }

    /** What core:exportdb asks of the dump tool: the content tables, triples included, in one call, then the Journal less its queue rows in another, into a file of its own that is appended to the first. */
    public function testTheDumpToolDumpsTheJournalApartWithoutItsQueueRows(): void
    {
        $calls = DbCommand::dumpInvocations(['--host=db', '--user=u'], 'wiki', ['yw_pages', 'yw_journal', 'yw_triples'], 'yw_', '/tmp/dump.sql');

        $this->assertArrayHasKey('/tmp/dump.sql', $calls, 'the first call writes the dump itself');
        $this->assertCount(2, $calls);
        $tablesOf = static fn (array $args): array => array_values(array_filter($args, static fn (string $arg): bool => str_starts_with($arg, 'yw_')));
        $whereOf = static fn (array $args): array => array_values(array_filter($args, static fn (string $arg): bool => str_starts_with($arg, '--where=')));
        $apart = array_values(array_filter($calls, static fn (array $args): bool => in_array('yw_journal', $args, true)));
        $this->assertCount(1, $apart);
        $this->assertSame(['yw_journal'], $tablesOf($apart[0]), 'the Journal is dumped by a call of its own');
        $this->assertCount(1, $whereOf($apart[0]));
        $this->assertStringContainsString('queue', $whereOf($apart[0])[0], 'less its queue channel');
        $whole = $calls['/tmp/dump.sql'];
        $this->assertSame(['yw_pages', 'yw_triples'], $tablesOf($whole), 'the content tables, triples included, in the one call');
        $this->assertSame([], $whereOf($whole), 'whole');
        foreach ($calls as $file => $args) {
            $this->assertContains("--result-file=$file", $args, 'each call writes the file it is listed under, to be appended to the dump');
            $this->assertContains('--host=db', $args, 'with the connection');
        }

        $single = DbCommand::dumpInvocations(['--host=db'], 'wiki', ['yw_pages'], 'yw_', '/tmp/dump.sql');
        $this->assertSame(['/tmp/dump.sql'], array_keys($single), 'a wiki without a Journal is dumped in one call');
    }

    /**
     * The Scheduler keeps nothing in the triples: a dump carries every one of them.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testADumpCarriesEveryTriple(array $params): void
    {
        $this->open($params);
        $this->triple('Home', 'content');
        $this->triple('Other', 'more content');

        $dump = $this->db->dumper()->dump()['sql'];

        $this->assertStringContainsString('content', $dump);
        $this->assertStringContainsString('more content', $dump);
    }

    /** The same through core:exportdb: the triples are dumped with the pages, in the same call. */
    public function testTheDumpToolCarriesEveryTriple(): void
    {
        $params = $this->openMysql();
        $this->triple('Home', 'content');
        $this->triple('Other', 'more content');

        $dump = $this->exportDb($params);

        $this->assertTrue(DbCommand::isDump($dump));
        $this->assertStringContainsString('content', $dump);
        $this->assertStringContainsString('more content', $dump);
    }

    /**
     * A backup of another wiki may hold a row under the id of a live queue row. The live row keeps its id, since the consumer
     * and the Scheduler find their rows by it, and the backup's row moves to a new id after every other.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testABackupRowWhoseIdALiveQueueRowHoldsMovesToANewOne(array $params, string $restore): void
    {
        $this->open($params);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $run = $this->recordRun('RunBeforeTheBackup');
        $dump = $this->db->dumper()->dump()['sql'] . $this->journalInsert(['id' => $run, 'channel' => 'audit', 'action' => 'foreign.audited']);

        $this->db->{$restore}($dump);

        $rows = $this->journalRows();
        $this->assertSame('queue queue.run.handled RunBeforeTheBackup', $rows[$run] ?? null, 'the live row keeps its id');
        $moved = array_keys($rows, 'audit foreign.audited ', true);
        $this->assertCount(1, $moved, "the backup's row is kept");
        $this->assertGreaterThan($run, $moved[0]);

        $this->journal->audit('after.restore');
        $after = $this->journalRows();
        $last = max([0, ...array_keys($after)]);
        $this->assertSame('audit after.restore ', $after[$last] ?? null, 'the Journal counts on from its largest id');
        $this->assertGreaterThan($moved[0], $last);
    }

    /**
     * The pass that runs the restore opened its row before it: a backup of another wiki that holds a row under that id leaves
     * the pass its row, which the pass closes by id when it ends. Renumbered, it would show "running" until it was purged.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testAForeignBackupHoldingTheRunningPassesIdLetsThePassCloseItsRow(array $params, string $restore): void
    {
        $this->open($params);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $pass = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $dump = $this->db->dumper()->dump()['sql'] . $this->journalInsert(['id' => $pass, 'channel' => 'audit', 'action' => 'foreign.audited']);

        $this->db->{$restore}($dump);
        $this->recorder->passFinished($pass, new QueuePassReport(0, 0, 10, QueuePassReport::EMPTY), 1024);

        $rows = $this->journalRows();
        $this->assertSame('queue queue.pass ' . QueuePassReport::EMPTY, $rows[$pass] ?? null, 'the pass closed its own row');
        $this->assertCount(1, array_keys($rows, 'audit foreign.audited ', true));
    }

    /**
     * A backup of another wiki that holds a row under the id of a task's older dispatch, and none under its latest: both keep
     * their ids, so the latest is still the one with the largest id, and the task is not sent again before its next slot.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testAForeignBackupHoldingATasksOlderDispatchLeavesItsAnchorWhereItWas(array $params, string $restore): void
    {
        $this->open($params);
        $this->openJournal();
        $this->recorder->taskDispatched(new QueueTestTask('digest'), 1800);
        $older = (int)$this->db->scalar('SELECT MAX(id) FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()));
        $this->journal->audit('between.the.dispatches');
        $this->clock->sleep(86400);
        $this->recorder->taskDispatched(new QueueTestTask('digest'), 1800);
        $dump = $this->db->dumper()->dump()['sql'] . $this->journalInsert(['id' => $older, 'channel' => 'audit', 'action' => 'foreign.audited']);

        $this->db->{$restore}($dump);

        $this->assertSame('2026-10-24 12:00:00', (new ScheduleHistory($this->db, $this->journalSchema))->of('digest')?->lastDispatchedAt?->format('Y-m-d H:i:s'));
    }

    public function testOnlyTheQueueTableIsLeftOut(): void
    {
        $tables = ['yw_pages', 'yw_triples', 'yw_queue', 'yw_queue_extra', 'yw_journal'];

        $this->assertSame(['yw_pages', 'yw_triples', 'yw_queue_extra', 'yw_journal'], DumpRewriter::contentTables($tables, 'yw_'));
    }

    /**
     * The restored Journal is in place from the swap on: a request may write to it before the restore is over.
     *
     * Its row takes the restored table's next id, which, from a backup taken before the running pass
     * opened, is the pass's own. The live queue rows are put into the restored Journal before it is
     * swapped in, so that row takes another id and the pass keeps writing its own row.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testAJournalWriteRightAfterTheSwapLeavesTheRunningPassItsId(array $params): void
    {
        $this->open($params, observed: true);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $dump = $this->db->dumper()->dump()['sql'];
        $pass = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $swappedIn = '/\bTO [`"]' . preg_quote($this->journalSchema->table(), '/') . '[`"]/';
        $this->afterQuery = function (string $query) use ($swappedIn): void {
            if (preg_match($swappedIn, $query) === 1) {
                $this->afterQuery = null;
                $this->journal->audit('written.after.the.swap');
            }
        };

        $this->db->restoreStagedFromDump($dump);
        $this->afterQuery = null;

        $rows = $this->journalRows();
        $this->assertSame('queue queue.pass ', $rows[$pass] ?? null, 'the running pass keeps its id');
        $this->assertContains('audit written.after.the.swap ', $rows, 'the write after the swap is kept, under another id');
        $this->recorder->passHeartbeat($pass, 2, 1024);
        $context = json_decode((string)$this->db->scalar('SELECT context FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()) . ' WHERE id = ?', null, [$pass]), true);
        $this->assertSame(2, is_array($context) ? ($context['handled'] ?? null) : null);
    }

    /**
     * A task seen, then sent, after the backup: its live rows survive the restore, a task row the backup holds is dropped, and the task is not sent again.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testARestoreKeepsTheLiveTasks(array $params, string $restore): void
    {
        $this->open($params);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $this->assertSame(0, $this->ticker()->tick(), 'the task is seen, nothing is due');
        $dump = $this->db->dumper()->dump()['sql'] . $this->journalInsert(['channel' => 'queue', 'action' => QueueJournalEntry::TASK_DISPATCHED, 'target' => 'ForeignTask']);
        $this->clock->sleep(31 * 60);
        $this->assertSame(1, $this->ticker()->tick(), 'it is due and sent');
        $rows = $this->journalRows();
        $this->assertContains('queue queue.task.dispatched digest', $rows);

        $this->db->{$restore}($dump);

        $this->assertSame($rows, $this->journalRows(), "the content is the backup's, the task rows the live ones");
        $this->assertSame('2026-10-23 12:31:00', (new ScheduleHistory($this->db, $this->journalSchema))->of('digest')?->lastDispatchedAt?->format('Y-m-d H:i:s'));
        $this->clock->sleep(60);
        $this->assertSame(0, $this->ticker()->tick(), 'a task that was sent is not sent again');
        $this->assertCount(1, $this->pendingIds());
    }

    /** Writes a triple of that resource into the probe wiki. */
    private function triple(string $resource, string $value): void
    {
        $this->db->query('INSERT INTO ' . $this->db->quoteIdentifier(self::PREFIX . 'triples') . ' (resource, property, value) VALUES (?, ?, ?)', [$resource, 'https://yeswiki.net/vocabulary/probe', $value]);
    }

    /**
     * Audit A, run R and open pass P in the Journal; a backup to which a foreign queue row X is added; run R2 after it; then the restore.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testARestoreKeepsTheLiveQueueRows(array $params, string $restore): void
    {
        $this->open($params);
        $this->openJournal();
        $this->journal->audit('backup.audited', 'Home');
        $audit = (int)$this->db->scalar('SELECT MAX(id) FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()));
        $run = $this->recordRun('RunBeforeTheBackup');
        $pass = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $dump = $this->db->dumper()->dump()['sql'] . $this->journalInsert(['channel' => 'queue', 'action' => 'queue.pass', 'target' => 'ForeignPass']);
        $after = $this->recordRun('RunAfterTheBackup');

        $this->db->{$restore}($dump);

        $this->assertSame([
            $audit => 'audit backup.audited Home',
            $run => 'queue queue.run.handled RunBeforeTheBackup',
            $pass => 'queue queue.pass ',
            $after => 'queue queue.run.handled RunAfterTheBackup',
        ], $this->journalRows(), "the content is the backup's, the queue rows are the live ones with their ids");

        $this->recorder->passHeartbeat($pass, 3, 1024);
        $context = json_decode((string)$this->db->scalar('SELECT context FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()) . ' WHERE id = ?', null, [$pass]), true);
        $this->assertSame(3, is_array($context) ? ($context['handled'] ?? null) : null, 'the running pass still writes its own row');

        $this->journal->audit('after.restore');
        $this->assertContains('audit after.restore ', $this->journalRows(), 'the Journal counts on from its largest id');
    }

    /** @return array<string, array{array<string, string|bool>}> */
    public static function servers(): array
    {
        $servers = [];
        foreach (QueueTransportTest::realServers() as $name => [$params]) {
            $servers[$name] = [['table_prefix' => self::PREFIX] + $params];
        }

        return ['sqlite' => [['db_driver' => 'sqlite', 'table_prefix' => self::PREFIX, 'debug' => false]]] + $servers;
    }

    /**
     * Each server with each way of restoring: the plain one, which drops the wiki's tables first, and the staged one.
     *
     * @return array<string, array{array<string, string|bool>, string}>
     */
    public static function restores(): array
    {
        $restores = [];
        foreach (self::servers() as $server => [$params]) {
            foreach (['restoreFromDump', 'restoreStagedFromDump'] as $restore) {
                $restores["$server, $restore"] = [$params, $restore];
            }
        }

        return $restores;
    }

    /**
     * A wiki of two content tables and a queue, on that server.
     *
     * @param array<string, string|bool> $params
     */
    private function open(array $params, bool $observed = false): void
    {
        if ($params['db_driver'] !== 'sqlite' && !QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        if ($params['db_driver'] === 'sqlite') {
            $this->file = tempnam(sys_get_temp_dir(), 'yw-backup-queue-') . '.db';
            $params['db_database'] = $this->file;
        }
        $bag = new ParameterBag(['table_prefix' => self::PREFIX] + $params);
        $this->db = $observed ? $this->observedDbService($bag) : new DbService($bag);
        $this->dropProbeTables();
        $this->db->query('CREATE TABLE ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . ' (tag VARCHAR(190), body TEXT)');
        $this->db->query('INSERT INTO ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . " (tag, body) VALUES ('Home', 'before')");
        QueueTestKit::tripleStore($this->db);
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->clock = new MockClock('2026-10-23 12:00:00', 'UTC');
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
    }

    /** A connection that tells afterQuery of each statement once it has run, to act at a given point of a restore. */
    private function observedDbService(ParameterBag $bag): DbService
    {
        return new class($bag, function (string $query): void {
            if ($this->afterQuery !== null) {
                ($this->afterQuery)($query);
            }
        }) extends DbService {
            public function __construct(ParameterBag $bag, private readonly \Closure $observe)
            {
                parent::__construct($bag);
            }

            public function query($query, array $params = [])
            {
                $result = parent::query($query, $params);
                ($this->observe)((string)$query);

                return $result;
            }
        };
    }

    /** A Journal on the probe wiki, and the consumer's recorder writing into it. */
    private function openJournal(): void
    {
        $db = $this->db;
        $journalSchema = $this->journalSchema = new JournalSchema($db);
        $journalSchema->create();
        $this->journal = new Journal(new ServiceLocator([
            DbService::class => static fn (): DbService => $db,
            JournalSchema::class => static fn (): JournalSchema => $journalSchema,
        ]));
        $log = fopen('php://memory', 'w+b');
        if ($log !== false) {
            $this->journal->writeTo($log);
        }
        $this->recorder = new QueueRecorder($db, $journalSchema, $this->clock);
    }

    /** A ticker over the probe wiki as a request would build one, with one task every 30 minutes. */
    private function ticker(): QueueScheduleTicker
    {
        $this->lockDir ??= sys_get_temp_dir() . '/yw-backup-locks-' . bin2hex(random_bytes(4));
        $provider = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessage::every('30 minutes', new QueueTestTask('digest'))];
            }
        };
        $awake = new class(new ParameterBag(['wiki_status' => 'running'])) extends HibernationService {
            public function isWikiHibernatedNow(): bool
            {
                return false;
            }
        };

        return new QueueScheduleTicker(
            [$provider],
            new QueueBus($this->transport, new QueueHandlerLocator([], new ServiceLocator([])), $this->journal, $this->recorder, QueueTestKit::noRequest()),
            new ScheduleHistory($this->db, $this->journalSchema),
            $this->recorder,
            QueueTestKit::locksIn($this->lockDir),
            $this->clock,
            $awake,
            $this->journal
        );
    }

    /** Records a handled run of that message type and answers the id of its row. */
    private function recordRun(string $type): int
    {
        $this->recorder->messageFinished(1, $type, null, QueueRecorder::HANDLED, QueueConsumerMode::Api, (float)strtotime('2026-10-23 12:00:00 UTC'), null);

        return (int)$this->db->scalar('SELECT MAX(id) FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()));
    }

    /**
     * A statement adding a row to the Journal, as a dump would hold it.
     *
     * @param array<string, int|string> $fields
     */
    private function journalInsert(array $fields): string
    {
        $fields += ['at' => '2026-10-01 00:00:00', 'last_at' => '2026-10-01 00:00:00', 'repeat' => 1, 'level' => 'info', 'actor' => '', 'target' => ''];

        return "\nINSERT INTO " . $this->db->quoteIdentifier($this->journalSchema->table())
            . ' (' . implode(', ', array_map($this->db->quoteIdentifier(...), array_keys($fields))) . ')'
            . " VALUES ('" . implode("', '", array_map(fn (int|string $value): string => $this->db->escape((string)$value), $fields)) . "');\n";
    }

    /**
     * Every row of the Journal, as `channel action target` by id.
     *
     * @return array<int, string>
     */
    private function journalRows(): array
    {
        $rows = [];
        foreach ($this->db->loadAll('SELECT id, channel, action, target FROM ' . $this->db->quoteIdentifier($this->journalSchema->table()) . ' ORDER BY id') as $row) {
            $rows[(int)$row['id']] = "{$row['channel']} {$row['action']} {$row['target']}";
        }

        return $rows;
    }

    /**
     * The probe wiki on the MySQL server, which core:exportdb dumps with mysqldump.
     *
     * @return array<string, string|bool>
     */
    private function openMysql(): array
    {
        $params = ['table_prefix' => self::PREFIX] + QueueTransportTest::realServers()['mysql'][0];
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no mysql server at {$params['db_host']}");
        }
        $this->open($params);

        return $params;
    }

    /**
     * What core:exportdb writes of the probe wiki; the test is skipped when the dump tool cannot be used here.
     *
     * @param array<string, string|bool> $params
     */
    private function exportDb(array $params): string
    {
        $wiki = $this->getWiki()->services;
        $command = new DbCommand(new ServiceLocator([
            ConsoleService::class => static fn (): ConsoleService => $wiki->get(ConsoleService::class),
            ParameterBagInterface::class => static fn (): ParameterBagInterface => new ParameterBag(['db_host' => "{$params['db_host']}:{$params['db_port']}"] + $params),
            LocalFiles::class => static fn (): LocalFiles => $wiki->get(LocalFiles::class),
            DbService::class => fn (): DbService => $this->db,
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $file = sys_get_temp_dir() . '/yw-exportdb-' . bin2hex(random_bytes(4)) . '.sql';

        try {
            $tester = new CommandTester($command);
            if ($tester->execute(['--filepath' => $file]) !== 0) {
                $this->markTestSkipped('mysqldump is not usable here: ' . $tester->getDisplay());
            }

            return (string)file_get_contents($file);
        } finally {
            @unlink($file);
        }
    }

    /** Queues a message and answers its id. */
    private function send(string $value): int
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage($value)));

        return (int)$sent->last(TransportMessageIdStamp::class)?->getId();
    }

    /** @return list<int> */
    private function pendingIds(): array
    {
        return array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->db->loadAll('SELECT id FROM ' . $this->db->quoteIdentifier($this->schema->table()) . ' ORDER BY id')
        );
    }

    private function dropProbeTables(): void
    {
        foreach ($this->db->schema()->getTables() as $table) {
            if (str_starts_with($table, self::PREFIX) || preg_match('/^x*yw(staging|replaced)' . substr(sha1(self::PREFIX), 0, 6) . '_/', $table)) {
                $this->db->query('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier($table));
            }
        }
    }
}

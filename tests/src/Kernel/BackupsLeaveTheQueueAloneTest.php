<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/**
 * The queue's tables are the running instance's state, not the wiki's content: a backup carries neither, and a restore leaves both as they were, creating them when the database has none.
 */
class BackupsLeaveTheQueueAloneTest extends YesWikiTestCase
{
    private const PREFIX = 'ywqbak_';

    private ?string $file = null;
    private MockClock $clock;

    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->dropProbeTables();
        }
        unset($this->transport, $this->schema, $this->db);
        if ($this->file !== null) {
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($this->file . $suffix);
            }
        }
        parent::tearDown();
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testABackupCarriesNeitherTheQueueNorItsHistory(array $params): void
    {
        $this->open($params);
        $this->send('pending at backup time');
        $this->writeHistory();

        $dump = $this->db->dumper()->dump();

        $this->assertSame('', $dump['error']);
        $this->assertEqualsCanonicalizing([self::PREFIX . 'pages', self::PREFIX . 'triples'], DumpRewriter::tables($dump['sql']), 'PostgreSQL lists its tables in no particular order');
        $this->assertStringNotContainsString(self::PREFIX . 'queue', $dump['sql']);
    }

    /**
     * An open pass (a `queue.pass` row whose target is empty) and a dispatch are what the running instance holds: a restore of a dump taken from another state leaves them, and the queue, with the same ids.
     *
     * @param array<string, string|bool>                $params
     * @param 'restoreFromDump'|'restoreStagedFromDump' $restore
     */
    #[DataProvider('restores')]
    public function testARestoreLeavesTheLiveHistoryAndAnOpenPassAsTheyWere(array $params, string $restore): void
    {
        $this->open($params);
        $dump = $this->db->dumper()->dump()['sql'];
        $this->db->query('UPDATE ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . " SET body = 'after'");
        $pending = $this->send('pending after the backup');
        $this->writeHistory();
        $history = $this->historyRows();
        $this->assertCount(2, $history);

        $this->db->{$restore}($dump);

        $this->assertSame('before', $this->db->scalar('SELECT body FROM ' . $this->db->quoteIdentifier(self::PREFIX . 'pages')), 'the content is restored');
        $this->assertSame($history, $this->historyRows(), 'the open pass and the dispatch are the same rows, with the same ids');
        $this->assertSame([$pending], $this->pendingIds(), 'the queue is the running one, with the same ids');
    }

    /**
     * Restoring into a database that never had the queue (a fresh install from a backup) leaves both tables ready.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testARestoreIntoADatabaseWithoutTheQueueCreatesBothTables(array $params): void
    {
        $this->open($params);
        $dump = $this->db->dumper()->dump()['sql'];
        $this->schema->drop();
        $this->assertFalse($this->schema->exists());

        $this->db->restoreStagedFromDump($dump);

        $this->assertTrue($this->schema->exists());
        $this->assertSame([], $this->pendingIds());
        $this->assertSame([], $this->historyRows());
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
    private function open(array $params): void
    {
        if ($params['db_driver'] !== 'sqlite' && !QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        if ($params['db_driver'] === 'sqlite') {
            $this->file = tempnam(sys_get_temp_dir(), 'yw-backup-queue-') . '.db';
            $params['db_database'] = $this->file;
        }
        $bag = new ParameterBag(['table_prefix' => self::PREFIX] + $params);
        $this->db = new DbService($bag);
        $this->dropProbeTables();
        $this->db->query('CREATE TABLE ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . ' (tag VARCHAR(190), body TEXT)');
        $this->db->query('INSERT INTO ' . $this->db->quoteIdentifier(self::PREFIX . 'pages') . " (tag, body) VALUES ('Home', 'before')");
        QueueTestKit::tripleStore($this->db);
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->clock = new MockClock(new \DateTimeImmutable('2026-10-23 12:00:00'));
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
    }

    /** An open pass (empty target) and a dispatch, as the recorder writes them. */
    private function writeHistory(): void
    {
        $table = $this->db->quoteIdentifier($this->schema->historyTable());
        foreach ([['queue.pass', ''], ['queue.task.dispatched', 'some-task']] as [$action, $target]) {
            $this->db->query(
                "INSERT INTO {$table} (at, last_at, actor, action, target, context) VALUES (?, ?, ?, ?, ?, ?)",
                ['2026-10-23 12:00:00', '2026-10-23 12:00:00', 'cli', $action, $target, '{}']
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function historyRows(): array
    {
        return $this->db->loadAll('SELECT * FROM ' . $this->db->quoteIdentifier($this->schema->historyTable()) . ' ORDER BY id');
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

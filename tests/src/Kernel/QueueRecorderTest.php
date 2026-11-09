<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueHistoryEntry;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

/** What the consumer writes about its passes and messages, and what the health checks and the admin page read back. */
#[CoversClass(QueueRecorder::class)]
#[CoversClass(QueueStatistics::class)]
#[CoversClass(QueueHistoryEntry::class)]
class QueueRecorderTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    /** Where the holders' locks live, so that the test never touches the dev wiki's cache/. */
    private string $lockDir;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
    private QueueRecorder $recorder;
    private QueueStatistics $statistics;
    /** Whether the tables are on a database server, which the test must clean up, rather than in its own file. */
    private bool $onServer = false;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-recorder-locks-' . bin2hex(random_bytes(4));
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->use(new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ])));
    }

    protected function tearDown(): void
    {
        if ($this->onServer) {
            $this->schema->drop();
        }
        unset($this->statistics, $this->recorder, $this->transport, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [] as $holder) {
            @unlink($holder);
        }
        @rmdir($this->lockDir . '/' . QueueHolderLock::DIRECTORY);
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testARunIsARowOfTheHistory(array $params): void
    {
        $this->on($params);
        $start = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(1.5);

        $this->recorder->messageFinished(7, 'App\\Foo', 'digest-daily', QueueRecorder::FAILED, QueueConsumerMode::Api, $start, 'boom');

        $rows = $this->db->loadAll("SELECT * FROM {$this->schema->historyTable()} WHERE action LIKE ?", [QueueHistoryEntry::RUN_PREFIX . '%']);
        $this->assertCount(1, $rows);
        [$row] = $rows;
        $this->assertSame('queue.run.failed', $row['action']);
        $this->assertSame('App\\Foo', $row['target']);
        $this->assertSame('api', $row['actor']);
        $this->assertSame('2026-10-23 12:00:01', $row['at'], 'a run is dated by its end, in UTC');
        $this->assertSame($row['at'], $row['last_at']);
        $context = $this->context($row);
        $this->assertSame(7, $context['message_id']);
        $this->assertSame('digest-daily', $context['task_id']);
        $this->assertSame(1500, $context['duration_ms'], 'it began that long before it ended: its start is not written apart');
        $this->assertSame('boom', $context['message'], 'the cause is where the history keeps a message');

        $this->recorder->messageFinished(8, 'App\\Foo', null, QueueRecorder::HANDLED, QueueConsumerMode::Api, $start, null);
        $handled = $this->db->loadSingle("SELECT * FROM {$this->schema->historyTable()} WHERE action = ?", ['queue.run.handled']);
        $this->assertNotNull($handled);
        $this->assertEqualsCanonicalizing(['message_id', 'duration_ms'], array_keys($this->context($handled)), 'a null is left out, never written');
    }

    /** What the queue does is written to its own table: nothing of it reaches the Journal's. */
    public function testRunsPassesAndTasksAreWrittenToTheHistoryTableNotTheJournal(): void
    {
        $journal = new JournalSchema($this->db);
        $journal->create();

        $pass = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $this->recorder->messageFinished(1, 'App\\Foo', null, QueueRecorder::HANDLED, QueueConsumerMode::Api, 0.0, null);
        $this->recorder->taskDispatched(new QueueTestTask('digest'));

        $this->assertSame(
            [QueueHistoryEntry::PASS, 'queue.run.handled', QueueHistoryEntry::TASK_DISPATCHED],
            array_column($this->db->loadAll("SELECT action FROM {$this->schema->historyTable()} ORDER BY id"), 'action')
        );
        $this->assertSame(1, $pass);
        $this->assertSame(0, (int)$this->db->scalar("SELECT COUNT(*) FROM {$journal->table()}"), 'the Journal holds none of it');
    }

    /** The last row of each task action is all the Scheduler remembers of the task: however old, it is not pruned. */
    public function testPruneKeepsTheLastRowOfEachTaskActionHoweverOld(): void
    {
        $task = new QueueTestTask('digest-month');
        $this->recorder->taskDispatched($task);
        $this->clock->sleep(60);
        $this->recorder->taskDispatched($task);
        $this->recorder->messageFinished(1, 'App\\Foo', null, QueueRecorder::HANDLED, QueueConsumerMode::Api, 0.0, null);
        $newest = (int)$this->db->scalar("SELECT MAX(id) FROM {$this->schema->historyTable()} WHERE action = ?", null, [QueueHistoryEntry::TASK_DISPATCHED]);
        $this->clock->sleep(40 * 86400);

        $this->assertSame(2, $this->recorder->prune(7), 'the older dispatch and the run are gone');

        $this->assertSame([$newest], array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->db->loadAll("SELECT id FROM {$this->schema->historyTable()}")
        ));
    }

    /**
     * The last pass of each mode is all that tells when its consumer was last seen: however old, it is not pruned, so a worker down
     * for longer than the retention is "not seen for 40 days" rather than "never seen at work".
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testPruneKeepsTheLastPassOfEachModeHoweverOld(array $params): void
    {
        $this->on($params);
        $older = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);
        $this->recorder->passFinished($older, new QueuePassReport(0, 0, 10, QueuePassReport::TIME_LIMIT), 1024);
        $this->clock->sleep(3600);
        $last = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);
        $this->recorder->passFinished($last, new QueuePassReport(0, 0, 10, QueuePassReport::TIME_LIMIT), 1024);
        $request = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passFinished($request, new QueuePassReport(0, 0, 10, QueuePassReport::EMPTY), 1024);
        $seen = new \DateTimeImmutable('2026-10-23 13:00:00');
        $this->clock->sleep(40 * 86400);

        $this->assertSame(1, $this->recorder->prune(7), 'the older pass of the worker alone');

        $this->assertSame([$last, $request], array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->db->loadAll("SELECT id FROM {$this->schema->historyTable()} ORDER BY id")
        ), 'the last pass of each mode, however old');
        $this->assertEquals($seen, $this->statistics->lastSeenAt(QueueConsumerMode::Worker));

        $hibernation = $this->createStub(HibernationService::class);
        $hibernation->method('isWikiHibernatedNow')->willReturn(false);
        $status = (new QueueFallback(QueueTestKit::settings('worker'), $this->statistics, $hibernation, QueueTestKit::locksIn($this->lockDir), $this->clock))->status();
        $this->assertNotNull($status, 'the worker is not seen within its delay: the end of requests stands in for it');
        $this->assertEquals($seen, $status->lastSeen, 'last seen 40 days ago, not never: the alert is QUEUE_HEALTH_FALLBACK_FAILED');
        $this->assertEquals($seen->modify('+' . QueueConsumerMode::Worker->stalledAfterSeconds() . ' seconds'), $status->since, 'in fallback since the worker\'s delay ran out after that pass');
    }

    /** A pass and a run are history: past the retention, counted from their last sign of life, they go, bar the last pass of each mode. */
    public function testPruneRemovesRunsAndPassesOlderThanTheRetention(): void
    {
        $old = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $this->recorder->passFinished($old, new QueuePassReport(0, 0, 10, QueuePassReport::EMPTY), 1024);
        $this->recorder->messageFinished(1, 'App\\Old', null, QueueRecorder::HANDLED, QueueConsumerMode::Api, 0.0, null);
        $this->clock->sleep(6 * 86400);
        $running = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $this->recorder->messageFinished(2, 'App\\Recent', null, QueueRecorder::HANDLED, QueueConsumerMode::Api, 0.0, null);
        $this->clock->sleep(2 * 86400);
        $this->recorder->passHeartbeat($running, 1, 1024);

        $this->assertSame(2, $this->recorder->prune(7), 'the pass and the run older than a week');

        $this->assertSame(['', 'App\\Recent'], array_column($this->db->loadAll("SELECT target FROM {$this->schema->historyTable()} ORDER BY id"), 'target'), 'the recent run, and the pass still alive');
        $this->assertSame(0, $this->recorder->prune(7), 'nothing more to remove');
    }

    /** The queue's rows never reach stderr: they are written to the table alone, whatever the severity of the run. */
    public function testTheQueueRowsNeverReachTheServerLog(): void
    {
        $script = sys_get_temp_dir() . '/yw-recorder-' . bin2hex(random_bytes(4)) . '.php';
        $database = sys_get_temp_dir() . '/yw-recorder-' . bin2hex(random_bytes(4)) . '.db';
        file_put_contents($script, '<?php
chdir(' . var_export((string)getcwd(), true) . ');
require "vendor/autoload.php";
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\Queue\QueueRecorder;

$db = new YesWiki\Kernel\Service\DbService(new Symfony\Component\DependencyInjection\ParameterBag\ParameterBag([
    "db_driver" => "sqlite", "db_database" => ' . var_export($database, true) . ', "table_prefix" => "ywq_", "debug" => false,
]));
$schema = new YesWiki\Kernel\Service\Queue\QueueSchema($db);
$schema->create();
$recorder = new QueueRecorder($db, $schema, new Symfony\Component\Clock\MockClock(new DateTimeImmutable("2026-10-23 12:00:00")));
$pass = $recorder->passStarted(QueueConsumerMode::Worker, null, false);
$recorder->passHeartbeat($pass, 1, 10);
$recorder->messageFinished(1, "T", "task", QueueRecorder::FAILED, QueueConsumerMode::Api, 0.0, "boom");
$recorder->messageFinished(2, "T", null, QueueRecorder::KILLED, QueueConsumerMode::Api, 0.0, "gone");
$recorder->messageIgnored(3, "T", "boom");
$recorder->passFinished($pass, new QueuePassReport(1, 0, 10, QueuePassReport::EMPTY), 10);
echo $db->scalar("SELECT COUNT(*) FROM {$schema->historyTable()}");
');

        try {
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            $stdout = (string)stream_get_contents($pipes[1]);
            $stderr = (string)stream_get_contents($pipes[2]);
            proc_close($process);

            $this->assertSame('', $stderr, 'the recorder wrote to stderr');
            $this->assertSame('5', $stdout, 'a pass, two runs, the task\'s row and the ignored one are all in the table');
        } finally {
            foreach ([$script, $database, $database . '-wal', $database . '-shm'] as $file) {
                @unlink($file);
            }
        }
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testAPassIsOneRowMovedByItsHeartbeats(array $params): void
    {
        $this->on($params);
        $id = $this->recorder->passStarted(QueueConsumerMode::Worker, new QueuePassLimits(1 << 30), false);

        $row = $this->historyRow($id);
        $this->assertSame('queue.pass', $row['action']);
        $this->assertSame('', $row['target'], 'an open pass has no stop reason');
        $this->assertSame('worker', $row['actor']);
        $this->assertSame(self::START, $row['at']);
        $this->assertSame($row['at'], $row['last_at']);
        $started = $this->context($row);
        $this->assertSame(getmypid(), $started['pid']);
        $this->assertArrayNotHasKey('budget_ms', $started, 'a pass has no time limit column');
        $this->assertEquals($this->statistics->recentPasses(1)[0]['startedAt'], $this->statistics->recentPasses(1)[0]['finishedAt'], 'no sign of life yet: last seen when it started');

        $this->clock->sleep(60);
        $this->recorder->passHeartbeat($id, 3, 1000);

        $row = $this->historyRow($id);
        $this->assertSame(self::START, $row['at'], 'a heartbeat does not move the start');
        $this->assertSame('2026-10-23 12:01:00', $row['last_at']);
        $this->assertSame('', $row['target'], 'a heartbeat does not end the pass');
        $beaten = $this->context($row);
        $this->assertSame(3, $beaten['handled']);
        $this->assertArrayNotHasKey('finished_at', $beaten, 'the heartbeat is `last_at`, not written twice');
        $this->assertSame($started['pid'], $beaten['pid']);
        $this->assertArrayNotHasKey('budget_ms', $beaten);

        $this->clock->sleep(60);
        $this->recorder->passFinished($id, new QueuePassReport(5, 0, 120000, QueuePassReport::STOPPED), 2000);

        $row = $this->historyRow($id);
        $this->assertSame(QueuePassReport::STOPPED, $row['target']);
        $this->assertSame('2026-10-23 12:02:00', $row['last_at']);

        $pass = $this->statistics->recentPasses(1)[0];
        $this->assertSame($id, $pass['id']);
        $this->assertSame('worker', $pass['mode']);
        $this->assertEquals(new \DateTimeImmutable(self::START), $pass['startedAt']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:02:00'), $pass['finishedAt']);
        $this->assertSame(5, $pass['handled']);
        $this->assertSame(QueuePassReport::STOPPED, $pass['stopReason']);
        $this->assertArrayNotHasKey('budgetMs', $pass);
        $this->assertSame(2000, $pass['peakMemory']);
        $this->assertSame(1 << 30, $pass['memoryLimit']);
        $this->assertSame(getmypid(), $pass['pid']);
        $this->assertTrue($pass['blocking'], 'a worker\'s pass is its consumer\'s whole life');
        $this->assertFalse($pass['beforeResponse']);
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testPassesThatEndByThemselvesAreNotConsumerStarts(array $params): void
    {
        $this->on($params);
        $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);
        $this->recorder->passStarted(QueueConsumerMode::Api, null, false);

        $this->assertSame(1, $this->statistics->consumerStarts(3600), 'only a worker is a consumer that started');

        $restarted = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);
        $this->recorder->passFinished($restarted, new QueuePassReport(0, 0, 10, QueuePassReport::CONFIG_CHANGED), 0);

        $this->assertSame(1, $this->statistics->consumerStarts(3600), 'a restart asked for by a configuration change is not a crash');
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testStatisticsReadTheHistory(array $params): void
    {
        $this->on($params);
        foreach ([1 => 100, 2 => 200, 3 => 300] as $id => $ms) {
            $this->ran($id, 'T', QueueRecorder::HANDLED, $ms);
        }
        $this->ran(4, 'T', QueueRecorder::FAILED, 50, 'boom');
        $this->ran(5, 'T', QueueRecorder::RETRIED, 50, 'again');

        $this->assertSame(['handled' => 3, 'failed' => 1, 'killed' => 0, 'avgMs' => 200], $this->statistics->outcomes(7 * 86400));

        $failed = $this->statistics->history(10, 'failed');
        $this->assertCount(1, $failed);
        $this->assertSame(['id', 'messageId', 'type', 'taskId', 'status', 'mode', 'finishedAt', 'durationMs', 'error'], array_keys($failed[0]));
        $this->assertSame(4, $failed[0]['messageId']);
        $this->assertSame('T', $failed[0]['type']);
        $this->assertNull($failed[0]['taskId']);
        $this->assertSame('failed', $failed[0]['status']);
        $this->assertSame('request', $failed[0]['mode']);
        $this->assertSame(50, $failed[0]['durationMs']);
        $this->assertSame('boom', $failed[0]['error']);
        $this->assertSame('failed', $this->statistics->messageState(4), 'gone from the queue, its last run tells');
        $this->assertSame('pending', $this->statistics->messageState(5));

        $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $waited = $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $after = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->clock->sleep(2);
        $this->recorder->passFinished($waited, new QueuePassReport(1, 0, 2000, QueuePassReport::EMPTY), 0);
        $this->recorder->passFinished($after, new QueuePassReport(1, 0, 2000, QueuePassReport::EMPTY), 0);

        $this->assertSame([2.0], $this->statistics->visitorWaits(3600), 'only the closed passes a visitor waited for');
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:02'), $this->statistics->lastActivityAt());
        $this->assertSame([5, 4, 3, 2, 1], array_column($this->statistics->history(10), 'messageId'), 'a pass is not a run');
    }

    public function testAPassAtWorkIsActivityNow(): void
    {
        $pass = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passFinished($pass, new QueuePassReport(0, 0, 0, QueuePassReport::EMPTY), 0);
        $this->clock->sleep(100);
        $this->assertEquals(new \DateTimeImmutable(self::START), $this->statistics->lastActivityAt(), 'nothing runs: the last pass tells');

        $holder = QueueHolderLock::newToken();
        $running = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false, $holder);
        $this->clock->sleep(100);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:01:40'), $this->statistics->lastActivityAt(), 'its process may have died: only its lock would tell');

        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->lockDir . '/' . QueueHolderLock::pathOf($holder));
        try {
            $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:03:20'), $this->statistics->lastActivityAt(), 'a pass whose process holds its lock is at work now');
        } finally {
            $this->assertSame(0, $letGo());
        }
        $this->recorder->passFinished($running, new QueuePassReport(0, 0, 0, QueuePassReport::EMPTY), 0);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:03:20'), $this->statistics->lastActivityAt(), 'ended now');
    }

    /** A type or a task longer than the column is cut, not refused. */
    public function testWhatIsLongerThanTheColumnIsCut(): void
    {
        $long = str_repeat('é', 300);
        $cut = str_repeat('é', 191);
        $task = new QueueTestTask($long, $long);

        $this->recorder->taskDeclared($task);
        $this->recorder->messageFinished(1, $long, $long, QueueRecorder::FAILED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->recorder->messageFinished(2, $long, $long, QueueRecorder::FAILED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->recorder->taskDispatched($task);

        $rows = $this->db->loadAll("SELECT action, target, context FROM {$this->schema->historyTable()} ORDER BY id");
        $this->assertSame(
            [QueueHistoryEntry::TASK_DECLARED, 'queue.run.failed', QueueHistoryEntry::TASK_FINISHED, 'queue.run.failed', QueueHistoryEntry::TASK_FINISHED, QueueHistoryEntry::TASK_DISPATCHED],
            array_column($rows, 'action')
        );
        $this->assertSame(array_fill(0, 6, $cut), array_column($rows, 'target'));
    }

    public function testAPassIsOpenUntilItFinishes(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Request, new QueuePassLimits(1000), true);

        $open = $this->statistics->lastPass();
        $this->assertNotNull($open);
        $this->assertSame($id, $open['id']);
        $this->assertSame('request', $open['mode']);
        $this->assertSame('', $open['stopReason'], 'a pass still running has no stop reason yet');
        $this->assertEquals($open['startedAt'], $open['finishedAt'], 'last seen when it started');
        $this->assertTrue($open['beforeResponse']);
        $this->assertArrayNotHasKey('budgetMs', $open);
        $this->assertSame(1000, $open['memoryLimit']);
        $this->assertSame(getmypid(), $open['pid']);
        $this->assertFalse($open['blocking'], 'a request pass is not a long-lived consumer');

        $this->clock->sleep(2);
        $this->recorder->passFinished($id, new QueuePassReport(4, 1, 2000, QueuePassReport::PHP_LIMIT), 12345);

        $closed = $this->statistics->lastPass();
        $this->assertNotNull($closed);
        $this->assertSame(4, $closed['handled']);
        $this->assertSame('php-limit', $closed['stopReason']);
        $this->assertSame(12345, $closed['peakMemory']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:02'), $closed['finishedAt']);
    }

    /**
     * A pass found dead keeps its last sign of life: `last_at` is when its consumer was last seen at work, not when it was found gone.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testAKilledPassKeepsItsLastSignOfLife(array $params): void
    {
        $this->on($params);
        $holder = QueueHolderLock::newToken();
        $id = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false, holder: $holder);
        $this->recorder->passHeartbeat($id, 2, 4096);
        $before = $this->historyRow($id);
        $this->assertEquals([['id' => $id, 'holder' => $holder, 'lastAt' => new \DateTimeImmutable(self::START)]], $this->statistics->openPasses());

        $this->clock->sleep(3600);
        $this->assertTrue($this->recorder->passKilled($id));

        $row = $this->historyRow($id);
        $this->assertSame(QueuePassReport::KILLED, $row['target']);
        $this->assertSame(self::START, $row['last_at'], 'found dead an hour later, it was last seen when it started');
        $this->assertSame($before['context'], $row['context'], 'what it did so far is what it did');
        $this->assertSame([], $this->statistics->openPasses());
        $this->assertSame(QueuePassReport::KILLED, $this->statistics->lastPass()['stopReason'] ?? null);
    }

    public function testAPassThatEndsAfterAllRewritesItsReason(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passKilled($id);

        $this->recorder->passFinished($id, new QueuePassReport(1, 0, 10, QueuePassReport::EMPTY), 0);

        $this->assertSame(QueuePassReport::EMPTY, $this->historyRow($id)['target'], 'it was not dead after all');
    }

    public function testAPassClosedOnceIsNotKilled(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passFinished($id, new QueuePassReport(1, 0, 10, QueuePassReport::EMPTY), 0);

        $this->assertFalse($this->recorder->passKilled($id));
        $this->assertSame(QueuePassReport::EMPTY, $this->historyRow($id)['target']);
        $this->assertFalse($this->recorder->passKilled($id + 1000), 'no such pass');
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testAKilledPassIsNoVisitorWait(array $params): void
    {
        $this->on($params);
        $waited = $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $killed = $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $this->clock->sleep(2);
        $this->recorder->passHeartbeat($killed, 0, 0);
        $this->recorder->passFinished($waited, new QueuePassReport(1, 0, 2000, QueuePassReport::EMPTY), 0);
        $this->assertTrue($this->recorder->passKilled($killed));

        $this->assertSame([2.0], $this->statistics->visitorWaits(3600), 'how long a dead pass lasted measures no wait');
    }

    public function testAPassNamesTheHolderItWasGiven(): void
    {
        $token = QueueHolderLock::newToken();
        $manual = $this->recorder->passStarted(QueueConsumerMode::Request, null, false, $token);
        $unnamed = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);

        $this->assertSame($token, $this->context($this->historyRow($manual))['holder']);
        $this->assertArrayNotHasKey('holder', $this->context($this->historyRow($unnamed)), 'no token is made up: a pass names its holder when it is given one');
        $holders = array_column($this->statistics->openPasses(), 'holder', 'id');
        $this->assertSame($token, $holders[$manual] ?? null);
        $this->assertSame('', $holders[$unnamed] ?? null, 'a pass that names no holder has an empty one');
    }

    public function testABlockingConsumerBeatsAndCountsAsAStart(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Worker, new QueuePassLimits(PHP_INT_MAX), false);
        $this->clock->sleep(60);
        $this->recorder->passHeartbeat($id, 7, 999);

        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertArrayNotHasKey('budgetMs', $pass);
        $this->assertSame(0, $pass['memoryLimit'], 'no memory bound is stored as 0');
        $this->assertSame('', $pass['stopReason'], 'a heartbeat does not end the pass');
        $this->assertSame(7, $pass['handled']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:01:00'), $pass['finishedAt'], 'finishedAt of an open pass is its last sign of life');
        $this->assertTrue($pass['blocking']);

        $this->assertSame(1, $this->statistics->consumerStarts(600));
        $this->clock->sleep(600);
        $this->assertSame(0, $this->statistics->consumerStarts(600));
    }

    public function testRecentPassesAreNewestFirst(): void
    {
        $first = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);
        $second = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);

        $this->assertSame([$second, $first], array_column($this->statistics->recentPasses(5), 'id'));
        $this->assertCount(1, $this->statistics->recentPasses(1));
    }

    public function testAFinishedMessageIsInTheHistory(): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(1.5);

        $this->recorder->messageFinished(12, QueueTestMessage::class, 'search-drain', QueueRecorder::FAILED, QueueConsumerMode::Api, $startedAt, str_repeat('x', 2000));

        [$row] = $this->statistics->history(10);
        $this->assertSame(12, $row['messageId']);
        $this->assertSame(QueueTestMessage::class, $row['type']);
        $this->assertSame('search-drain', $row['taskId']);
        $this->assertSame('failed', $row['status']);
        $this->assertSame('api', $row['mode']);
        $this->assertSame(1500, $row['durationMs']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:01'), $row['finishedAt'], 'dated by its end, to the second');
        $this->assertSame(1000, mb_strlen((string)$row['error']), 'the error is cut, not the row refused');
    }

    public function testHistoryFiltersByStatusNewestFirst(): void
    {
        $now = (float)$this->clock->now()->format('U.u');
        $this->recorder->messageFinished(1, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, $now, null);
        $this->recorder->messageFinished(2, QueueTestMessage::class, null, QueueRecorder::RETRIED, QueueConsumerMode::Request, $now, 'boom');
        $this->recorder->messageFinished(3, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, $now, null);

        $this->assertSame([3, 2, 1], array_column($this->statistics->history(10), 'messageId'));
        $this->assertSame([3, 1], array_column($this->statistics->history(10, 'handled'), 'messageId'));
        $this->assertSame([3], array_column($this->statistics->history(1, 'handled'), 'messageId'));
    }

    public function testTheOutcomesOfOneTypeOverAWindow(): void
    {
        $this->finish(1, QueueRecorder::HANDLED, 0.1);
        $this->finish(2, QueueRecorder::FAILED, 0.1);
        $this->finish(3, QueueRecorder::FAILED, 0.1);
        $this->finish(4, QueueRecorder::KILLED, 0.1);
        $this->finish(5, QueueRecorder::RETRIED, 0.1);
        $this->recorder->messageIgnored(2, QueueTestMessage::class, 'boom');
        QueueTestKit::recordRun($this->db, 'Other\\Type', QueueRecorder::HANDLED, 1000, '2026-10-23 11:00:01', 9);
        QueueTestKit::recordRun($this->db, QueueTestMessage::class, QueueRecorder::HANDLED, 1000, '2026-10-21 11:00:01', 10);

        $this->assertSame(
            ['handled' => 1, 'failed' => 2, 'killed' => 1, 'avgMs' => 100],
            $this->statistics->outcomes(86400, QueueTestMessage::class),
            'a retry is not an outcome, nor an acknowledgement, nor another type, nor a row older than the window'
        );
        $this->assertSame(['handled' => 0, 'failed' => 0, 'killed' => 0, 'avgMs' => null], $this->statistics->outcomes(86400, 'Never\\Seen'));
    }

    public function testByTypeJoinsTheQueueAndTheHistory(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $this->transport->withQueue('failed')->send(new Envelope(new QueueTestMessage('broken')));
        $this->finish(1, QueueRecorder::HANDLED, 0.1);
        $this->finish(2, QueueRecorder::HANDLED, 0.3);
        QueueTestKit::recordRun($this->db, 'Other\\Gone', QueueRecorder::HANDLED, 1000, '2026-10-01 00:00:01', 9);

        $types = $this->statistics->byType(7);

        $this->assertSame([QueueTestMessage::class], array_column($types, 'type'), 'a type only seen before the window is not listed');
        $this->assertSame(1, $types[0]['pending']);
        $this->assertSame(1, $types[0]['failed']);
        $this->assertSame(200, $types[0]['avgMs']);
        $this->assertSame('2026-10-23 12:00:00', $types[0]['lastSuccessAt']?->format('Y-m-d H:i:s'), 'stored to the second');
    }

    public function testFailedListsTheFailureQueue(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('broken')));
        $id = (int)$sent->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->moveToFailed($id, 'RuntimeException: boom');

        $failed = $this->statistics->failed();

        $this->assertCount(1, $failed);
        $this->assertSame($id, $failed[0]['id']);
        $this->assertSame(QueueTestMessage::class, $failed[0]['type']);
        $this->assertSame('RuntimeException: boom', $failed[0]['error']);
        $this->assertNull($failed[0]['killedIn'], 'it failed by itself');
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    public function testOnlyAClaimedMessageOfTheDefaultQueueIsInProgress(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('claimed')));
        $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $this->assertSame(0, $this->statistics->inProgressCount());

        $failed = (int)$this->transport->send(new Envelope(new QueueTestMessage('failed')))->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->moveToFailed($failed, 'boom');
        $killed = (int)$this->transport->send(new Envelope(new QueueTestMessage('killed')))->last(TransportMessageIdStamp::class)?->getId();
        QueueTestKit::failAsKilled($this->db, $this->schema, $this->clock, $killed, QueueConsumerMode::Request);
        $this->transport->workFor(QueueTestHolders::passToken());
        [$taken] = [...$this->transport->get()];

        $this->assertSame(1, $this->statistics->inProgressCount(), 'neither the one waiting nor those in `failed`');
        $this->transport->ack($taken);
        $this->assertSame(0, $this->statistics->inProgressCount());
    }

    public function testFailedMessagesAreListedByTypeLeavingTheKilledOnes(): void
    {
        $fail = function (object $message): int {
            $id = (int)$this->transport->send(new Envelope($message))->last(TransportMessageIdStamp::class)?->getId();
            $this->transport->moveToFailed($id, 'boom');

            return $id;
        };
        $first = $fail(new QueueTestMessage('a'));
        $second = $fail(new QueueTestMessage('b'));
        $third = $fail(new QueueTestMessage('c'));
        $other = $fail(new QueueTestTask('d'));
        $killed = (int)$this->transport->send(new Envelope(new QueueTestMessage('k')))->last(TransportMessageIdStamp::class)?->getId();
        QueueTestKit::failAsKilled($this->db, $this->schema, $this->clock, $killed, QueueConsumerMode::Api);

        $this->assertSame([], $this->statistics->failedOfTypes([]));
        $this->assertSame([$third, $second], array_column($this->statistics->failedOfTypes([QueueTestMessage::class], 2), 'id'), 'the newest first, up to the limit, the killed one left out');
        $this->assertSame([$third, $second, $first], array_column($this->statistics->failedOfTypes([QueueTestMessage::class]), 'id'));
        $this->assertSame([$other, $third, $second, $first], array_column($this->statistics->failedOfTypes([QueueTestMessage::class, QueueTestTask::class]), 'id'));
        $this->assertSame([], $this->statistics->failedOfTypes(['Never\\Seen']));
        $this->assertSame([
            ['type' => QueueTestMessage::class, 'killedIn' => null, 'count' => 3],
            ['type' => QueueTestMessage::class, 'killedIn' => 'api', 'count' => 1],
            ['type' => QueueTestTask::class, 'killedIn' => null, 'count' => 1],
        ], $this->statistics->failedGroups(), 'by type, and by the mode its consumer was killed in');
    }

    public function testPendingCountAndOldest(): void
    {
        $this->assertNull($this->statistics->oldestPendingAt());
        $this->transport->send(new Envelope(new QueueTestMessage('one')));
        $this->clock->sleep(30);
        $this->transport->send(new Envelope(new QueueTestMessage('two'), [new DelayStamp(60000)]));

        $this->assertSame(2, $this->statistics->pendingCount(), 'delayed messages are waiting too');
        $this->assertEquals(new \DateTimeImmutable(self::START), $this->statistics->oldestPendingAt());
    }

    public function testMessageStateFollowsTheMessage(): void
    {
        $id = (int)$this->transport->send(new Envelope(new QueueTestMessage('one')))->last(TransportMessageIdStamp::class)?->getId();
        $this->assertSame('pending', $this->statistics->messageState($id));

        $this->transport->workFor(QueueTestHolders::passToken());
        [$taken] = [...$this->transport->get()];
        $this->assertSame('running', $this->statistics->messageState($id));

        $this->transport->ack($taken);
        $this->recorder->messageFinished($id, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, 0.0, null);
        $this->assertSame('handled', $this->statistics->messageState($id));

        $this->recorder->messageFinished(50, QueueTestMessage::class, null, QueueRecorder::RETRIED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->assertSame('pending', $this->statistics->messageState(50), 'a retry waits under a new id: the work is still to do');
        $this->recorder->messageFinished(51, QueueTestMessage::class, null, QueueRecorder::FAILED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->assertSame('failed', $this->statistics->messageState(51));

        $killed = (int)$this->transport->send(new Envelope(new QueueTestMessage('two')))->last(TransportMessageIdStamp::class)?->getId();
        QueueTestKit::failAsKilled($this->db, $this->schema, $this->clock, $killed, QueueConsumerMode::Request);
        $this->assertSame('killed', $this->statistics->messageState($killed));
        $failed = (int)$this->transport->send(new Envelope(new QueueTestMessage('three')))->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->moveToFailed($failed, 'undecodable: gone');
        $this->assertSame('failed', $this->statistics->messageState($failed));

        $this->recorder->messageIgnored(52, QueueTestMessage::class, 'boom');
        $this->assertSame('failed', $this->statistics->messageState(52), 'an acknowledged failure is still a failure');

        $this->assertSame('unknown', $this->statistics->messageState(999));
    }

    /** A delivery past the hour and the minute with no lock file whose consumer is dead is killed, though no pass has moved it to `failed` yet. */
    public function testAnAbandonedDeliveryWithNoLockFilePastTheHourAndTheMinuteIsKilled(): void
    {
        $id = (int)$this->transport->send(new Envelope(new QueueTestMessage('one')))->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->claimAs(QueueConsumerMode::Request);
        $this->transport->workFor(QueueTestHolders::passToken());
        [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::token());

        $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS);
        $this->assertSame('running', $this->statistics->messageState($id), 'with no lock file, within the hour and the minute');

        $this->clock->sleep(1);
        $this->assertSame('killed', $this->statistics->messageState($id));
    }

    /**
     * A task's last run is told by its history rows alone, and ScheduleHistory reads it back.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testATasksLastRunIsTheHistorysAlone(array $params): void
    {
        $this->on($params);
        $task = new QueueTestTask('digest');

        $this->recorder->taskDispatched($task);
        $this->recorder->taskFinished('digest', QueueRecorder::FAILED, 'boom');
        $this->assertSame(['failed', 'boom'], $this->taskSummary('digest'));
        $this->clock->sleep(60);
        $this->recorder->taskDispatched($task);
        $this->recorder->taskFinished('digest', QueueRecorder::FAILED, 'boom again');
        $this->assertSame(['failed', 'boom again'], $this->taskSummary('digest'));
        $this->recorder->taskFinished('digest', QueueRecorder::HANDLED, null);
        $this->assertSame(['handled', null], $this->taskSummary('digest'));

        $history = (new ScheduleHistory($this->db, $this->schema))->of('digest') ?? $this->fail('no history');
        $this->assertSame('2026-10-23 12:01:00', $history->lastDispatchedAt?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:01:00', $history->lastFinishedAt?->format('Y-m-d H:i:s'));
    }

    /**
     * A task's appearance, dispatches and runs are `queue` rows whose target is the task, and whose context says what the history needs.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTaskRowsAreNamedByTheirTask(array $params): void
    {
        $this->on($params);
        $task = new QueueTestTask('digest');
        $table = $this->schema->historyTable();

        $this->recorder->taskDeclared($task);
        $this->recorder->taskDispatched($task);
        $this->recorder->taskFinished('digest', QueueRecorder::RETRIED, 'again');
        $this->recorder->taskFinished('digest', QueueRecorder::FAILED, 'boom');
        $this->recorder->taskFinished('digest', QueueRecorder::HANDLED, null);

        $rows = $this->db->loadAll("SELECT * FROM {$table} ORDER BY id");
        $this->assertSame(
            [QueueHistoryEntry::TASK_DECLARED, QueueHistoryEntry::TASK_DISPATCHED, QueueHistoryEntry::TASK_FINISHED, QueueHistoryEntry::TASK_FINISHED, QueueHistoryEntry::TASK_FINISHED],
            array_column($rows, 'action')
        );
        $this->assertSame(array_fill(0, 5, 'digest'), array_column($rows, 'target'), 'the task, not the message type');
        $this->assertSame(array_fill(0, 5, self::START), array_column($rows, 'at'));
        $this->assertSame(['{}', '{}'], [(string)$rows[0]['context'], (string)$rows[1]['context']], 'an appearance and a dispatch are their date alone');
        $this->assertSame(['status' => 'retried', 'message' => 'again'], $this->context($rows[2]));
        $this->assertSame(['status' => 'failed', 'message' => 'boom'], $this->context($rows[3]));
        $this->assertSame(['status' => 'handled'], $this->context($rows[4]), 'a null message is left out');
    }

    /** A run of a scheduled task is a `queue.run.*` row of its message type and a `queue.task.finished` row of the task. */
    public function testAScheduledTasksRunWritesBothRows(): void
    {
        $this->recorder->messageFinished(7, QueueTestMessage::class, 'digest', QueueRecorder::FAILED, QueueConsumerMode::Api, (float)$this->clock->now()->format('U.u'), 'boom');
        $this->recorder->messageFinished(8, QueueTestMessage::class, null, QueueRecorder::FAILED, QueueConsumerMode::Api, (float)$this->clock->now()->format('U.u'), 'boom');

        $rows = $this->db->loadAll("SELECT action, target FROM {$this->schema->historyTable()} ORDER BY id");

        $this->assertSame([
            ['action' => 'queue.run.failed', 'target' => QueueTestMessage::class],
            ['action' => 'queue.task.finished', 'target' => 'digest'],
            ['action' => 'queue.run.failed', 'target' => QueueTestMessage::class],
        ], $rows, 'a message that is no task\'s leaves no task row');
    }

    /**
     * The recorder's and the statistics' SQL on the engines the wiki may run on.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testOnEachServer(array $params): void
    {
        $this->on($params);

        $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $pass = $this->recorder->passStarted(QueueConsumerMode::Worker, new QueuePassLimits(1 << 33), false);
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(0.25);
        $this->recorder->messageFinished(99, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Worker, $startedAt, null);
        $this->recorder->passHeartbeat($pass, 1, 1 << 32);
        $this->recorder->passFinished($pass, new QueuePassReport(1, 1, 250, QueuePassReport::TIME_LIMIT), 1 << 32);

        $this->assertSame([QueueTestMessage::class], array_column($this->statistics->byType(), 'type'));
        $this->assertSame(1, $this->statistics->byType()[0]['pending']);
        $this->assertSame(250, $this->statistics->byType()[0]['avgMs']);
        $this->assertSame(['handled' => 1, 'failed' => 0, 'killed' => 0, 'avgMs' => 250], $this->statistics->outcomes(3600, QueueTestMessage::class));
        $this->assertSame('time-limit', $this->statistics->lastPass()['stopReason'] ?? null);
        $this->assertSame(1 << 32, $this->statistics->lastPass()['peakMemory'] ?? null, 'bytes past 4 GB fit');
        $this->assertSame(1 << 33, $this->statistics->lastPass()['memoryLimit'] ?? null);
        $this->assertSame(1, $this->statistics->consumerStarts(600));
        $this->assertSame('handled', $this->statistics->messageState(99));
        $this->assertSame([99], array_column($this->statistics->history(10), 'messageId'));
        $this->assertSame([], $this->statistics->failed());
    }

    /**
     * SQLite in a file of its own, and the development servers when they are there.
     *
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function servers(): array
    {
        return ['sqlite' => [['db_driver' => 'sqlite']]] + QueueTransportTest::realServers();
    }

    /**
     * Moves the test onto that server, in tables of its own that tearDown() removes; SQLite stays on the file setUp() made.
     *
     * @param array<string, string|bool> $params
     */
    private function on(array $params): void
    {
        if ($params['db_driver'] === 'sqlite') {
            return;
        }
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $this->use(new DbService(new ParameterBag($params)), fresh: true);
        $this->onServer = true;
    }

    /** The queue's tables on $db, and the services over them. */
    private function use(DbService $db, bool $fresh = false): void
    {
        $this->db = $db;
        $this->schema = new QueueSchema($db);
        if ($fresh) {
            $this->schema->drop();
        }
        $this->schema->create();
        $this->transport = new QueueTransport($db, $this->schema, clock: $this->clock, holderLock: new QueueHolderLock(QueueTestKit::locksIn($this->lockDir)));
        $this->recorder = new QueueRecorder($db, $this->schema, $this->clock);
        $this->statistics = new QueueStatistics($db, $this->schema, $this->transport, $this->clock);
    }

    /** @return array<string, mixed> */
    private function historyRow(int $id): array
    {
        $row = $this->db->loadSingle("SELECT * FROM {$this->schema->historyTable()} WHERE id = ?", [$id]);
        $this->assertNotNull($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function context(array $row): array
    {
        $context = json_decode((string)$row['context'], true);
        $this->assertIsArray($context);
        $this->assertNotContains(null, $context, 'a null would read back as the string "null" on MySQL');

        return $context;
    }

    /** A history row for a QueueTestMessage that took $seconds, finished now. */
    private function finish(int $id, string $status, float $seconds): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep($seconds);
        $this->recorder->messageFinished($id, QueueTestMessage::class, null, $status, QueueConsumerMode::Request, $startedAt, null);
    }

    /** A run of that type that took $ms in request mode, finished now. */
    private function ran(int $id, string $type, string $status, int $ms, ?string $error = null): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep($ms / 1000);
        $this->recorder->messageFinished($id, $type, null, $status, QueueConsumerMode::Request, $startedAt, $error);
    }

    /** @return array{?string, ?string} the task's last status and last error, as ScheduleHistory reads them */
    private function taskSummary(string $taskId): array
    {
        $task = (new ScheduleHistory($this->db, $this->schema))->of($taskId) ?? $this->fail("no row for {$taskId}");

        return [$task->lastStatus, $task->lastError];
    }
}

<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Message\PruneQueue;
use YesWiki\Kernel\Message\PruneQueueHandler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** The scheduler only feeds the queue: what is due becomes a message, once, and what the tasks did is kept per task. */
#[CoversClass(QueueScheduleTicker::class)]
#[CoversClass(QueueCheckpoint::class)]
#[CoversClass(QueueRecorder::class)]
#[CoversClass(QueueStatistics::class)]
#[CoversClass(KernelSchedule::class)]
#[CoversClass(PruneQueueHandler::class)]
class QueueScheduleTickerTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;

    /** @var array<string, string> task id => interval: what the test provider declares, rebuilt at each call as a module's provider would */
    private array $tasks = ['test-task' => '30 minutes'];

    /** Whether the digests' provider of testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks throws. */
    private bool $digestsBroken = false;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->build(new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            // The query log is how testATickWithNothingDueTouchesNoTable counts what a tick costs.
            'debug' => true,
        ])));
        $this->schema->create();
        (new JournalSchema($this->db))->create();
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->transport, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        @unlink(substr($this->file, 0, -3));
    }

    public function testNothingIsDueBeforeTheFirstInterval(): void
    {
        $this->assertSame(0, $this->ticker()->tick());

        $this->clock->sleep(29 * 60);

        $later = $this->ticker();
        $this->assertSame(0, $later->tick());
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 12:30:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
    }

    public function testADueTaskIsDispatchedOnceAndRecorded(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(31 * 60);

        $this->assertSame(1, $ticker->tick());
        $this->assertSame(0, $ticker->tick(), 'a run is dispatched once');

        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('task:test-task', $this->db->scalar("SELECT dedup_key FROM {$this->schema->table()} WHERE queue_name = 'default'"));
        [$task] = $this->statistics->tasks();
        $this->assertSame('test-task', $task['taskId']);
        $this->assertSame('Test task test-task', $task['label']);
        $this->assertSame(1800, $task['everySeconds']);
        $this->assertSame('2026-10-23 12:31:00', $task['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 13:00:00', $task['nextDueAt']?->format('Y-m-d H:i:s'), 'the next run, about 30 min away');
        $this->assertSame('2026-10-23 13:00:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
    }

    public function testAMissedTaskIsCaughtUpOnlyOnce(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(5 * 3600);

        $this->assertSame(1, $ticker->tick(), 'ten missed runs, one catch-up');
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 17:30:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
    }

    public function testTheCheckpointSurvivesANewTicker(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(31 * 60);
        $this->assertSame(1, $this->ticker()->tick());

        $fresh = $this->ticker();
        $this->assertSame(0, $fresh->tick());

        // Without the due time, a ticker has to rebuild the schedule from the saved state: the 12:30 run is still done.
        $this->checkpointDueAt(null);
        $rebuilt = $this->ticker();
        $this->assertSame(0, $rebuilt->tick());
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 13:00:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
    }

    public function testTheDeclaredTasksAreWhatTheProvidersDeclareNow(): void
    {
        $this->tasks = ['test-task' => '30 minutes', 'other' => '1 hour'];
        $this->assertSame(['test-task', 'other'], $this->ticker()->declaredTaskIds());

        unset($this->tasks['other']);
        $this->assertSame(['test-task'], $this->ticker()->declaredTaskIds());
    }

    /** A deleted data source or a disabled extension: its row would be late or failing for ever, with nothing left to run. */
    public function testADueTickForgetsTheTasksThatLeftTheSchedule(): void
    {
        $this->tasks = ['test-task' => '30 minutes', 'gone' => '30 minutes'];
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(31 * 60);
        $ticker->tick();
        $ids = array_column($this->statistics->tasks(), 'taskId');
        sort($ids);
        $this->assertSame(['gone', 'test-task'], $ids);

        unset($this->tasks['gone']);
        $this->clock->sleep(31 * 60);
        $this->ticker()->tick();

        $this->assertSame(['test-task'], array_column($this->statistics->tasks(), 'taskId'));
        $this->assertNotNull($this->statistics->scheduleDueAt(), 'the Scheduler\'s own row is not a task and stays');
    }

    /**
     * A provider that throws (the digests' markers unreadable, a broken private bucket) is journaled and left out of that tick; the other tasks are still dispatched, and its tasks are kept, and still judged, rather than pruned as if it no longer declared them.
     *
     * Pruned, they would leave no row for `queue-task-overdue` to find late: the digests would stop with nothing to say so.
     */
    public function testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks(): void
    {
        $digests = new class(function (): array {
            if ($this->digestsBroken) {
                throw new \RuntimeException('private/digests is not readable');
            }

            return [RecurringMessage::every('30 minutes', new QueueTestTask('digest'))];
        }) implements ProvidesSchedule {
            /** @param \Closure(): list<RecurringMessage> $messages */
            public function __construct(private \Closure $messages)
            {
            }

            public function recurringMessages(): array
            {
                return ($this->messages)();
            }
        };
        $ticker = $this->ticker([$digests]);
        $ticker->tick();
        $this->clock->sleep(31 * 60);
        $this->assertSame(2, $ticker->tick());
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");

        $this->digestsBroken = true;
        $this->clock->sleep(31 * 60);

        $this->assertSame(1, $this->ticker([$digests])->tick(), 'the other task is still dispatched');
        $ids = array_column($this->statistics->tasks(), 'taskId');
        sort($ids);
        $this->assertSame(['digest', 'test-task'], $ids, 'the digest task is kept');
        $declared = $this->ticker([$digests])->declaredTaskIds();
        sort($declared);
        $this->assertSame(['digest', 'test-task'], $declared, 'and still judged by the health checks');
        $journaled = $this->db->loadAll('SELECT action, target, context FROM ' . (new JournalSchema($this->db))->table() . " WHERE action = 'queue.schedule_provider_failed'");
        $this->assertNotEmpty($journaled);
        $this->assertSame($digests::class, $journaled[0]['target']);
        $this->assertStringContainsString('private/digests is not readable', (string)$journaled[0]['context']);

        $this->digestsBroken = false;
        $this->assertSame(['test-task', 'digest'], $this->ticker([$digests])->declaredTaskIds(), 'back as soon as it declares again');
    }

    /** The Scheduler refuses a message declared twice: refused for the whole schedule, every task of every provider would stop, the health checks with them. */
    public function testATaskDeclaredTwiceIsScheduledOnceAndStopsNothing(): void
    {
        $twice = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [
                    RecurringMessage::every('30 minutes', new QueueTestTask('digest')),
                    RecurringMessage::every('30 minutes', new QueueTestTask('digest')),
                ];
            }
        };
        $ticker = $this->ticker([$twice]);
        $ticker->tick();
        $this->clock->sleep(31 * 60);

        $this->assertSame(2, $this->ticker([$twice])->tick(), 'both tasks are dispatched, the twice-declared one once');
        $this->assertSame(['test-task', 'digest'], $this->ticker([$twice])->declaredTaskIds());
        $journaled = $this->db->loadAll('SELECT target FROM ' . (new JournalSchema($this->db))->table() . " WHERE action = 'queue.schedule_provider_failed'");
        $this->assertNotEmpty($journaled, 'the extension is told it declares a task twice');
        $this->assertSame($twice::class, $journaled[0]['target']);
    }

    public function testRunNowDispatchesImmediately(): void
    {
        $ticker = $this->ticker();

        $this->assertTrue($ticker->runNow('test-task'));
        $this->assertTrue($ticker->runNow('test-task'));
        $this->assertFalse($ticker->runNow('no-such-task'));

        $this->assertSame(1, $this->statistics->pendingCount(), 'a run still waiting is not queued twice');
        [$task] = $this->statistics->tasks();
        $this->assertSame('2026-10-23 12:00:00', $task['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $this->assertSame(1800, $task['everySeconds']);
        $this->assertNull($this->statistics->scheduleDueAt(), 'running a task by hand is not a tick');
    }

    public function testConsecutiveFailuresAreCounted(): void
    {
        $recorder = new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock);

        foreach (['first', 'second', 'third'] as $error) {
            $recorder->taskFinished('x', QueueRecorder::FAILED, $error);
        }
        $this->assertSame(['x', 3, 'failed', 'third'], $this->summary('x'));

        $recorder->taskFinished('x', QueueRecorder::RETRIED, 'again');
        $this->assertSame(['x', 3, 'retried', 'again'], $this->summary('x'), 'an attempt that will be retried does not end the run');

        $recorder->taskFinished('x', QueueRecorder::HANDLED, null);
        $this->assertSame(['x', 0, 'handled', null], $this->summary('x'));
    }

    public function testAHandledMessageOfATaskIsRecordedOnTheTask(): void
    {
        $recorder = new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock);

        $recorder->messageFinished(1, QueueTestTask::class, 'test-task', QueueRecorder::KILLED, QueueConsumerMode::Request, (float)$this->clock->now()->format('U'), QueueTransport::killedCause('request'));
        $recorder->messageFinished(2, QueueTestMessage::class, null, QueueRecorder::FAILED, QueueConsumerMode::Request, (float)$this->clock->now()->format('U'), 'boom');

        $this->assertSame(['test-task', 1, 'killed', QueueTransport::killedCause('request')], $this->summary('test-task'));
        $this->assertCount(1, $this->statistics->tasks(), 'a message that is no task has no row');
    }

    public function testATickWithNothingDueTouchesNoTable(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $queries = count($this->db->getQueryLog());

        $this->clock->sleep(30);
        $ticker->tick();
        $this->assertCount($queries, $this->db->getQueryLog(), 'a blocking consumer ticks every second: the due time is held in memory');

        $this->clock->sleep(31);
        $ticker->tick();
        $this->assertCount($queries + 1, $this->db->getQueryLog(), 'once a minute it is read again, and that is all');
    }

    /**
     * The due time held in memory goes stale when another process writes the checkpoint: one whose schedule has a task this one did not know of when it last read, a restored database.
     */
    public function testALongLivedTickerNoticesADueTimeWrittenElsewhere(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->tasks['quick'] = '1 minute';
        $this->checkpointDueAt('2026-10-23 12:01:00');

        $this->clock->sleep(61);

        $this->assertSame(1, $ticker->tick());
        $this->assertSame(['quick'], array_column($this->statistics->tasks(), 'taskId'));
    }

    public function testOnlyOneProcessTicksAtATime(): void
    {
        $lock = new RuntimeLock();
        $held = $lock->acquire(QueueCheckpoint::LOCK);
        $this->assertTrue($lock->tryLock($held));

        try {
            $this->clock->sleep(3600);
            $this->assertSame(0, $this->ticker()->tick(), 'while another process ticks, this one does not');
            $this->assertNull($this->statistics->scheduleDueAt());
        } finally {
            $lock->release($held);
        }

        $this->assertSame(0, $this->ticker()->tick());
        $this->assertSame('2026-10-23 13:30:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
        $this->clock->sleep(1801);
        $this->assertSame(1, $this->ticker()->tick(), 'a tick lets go of the lock when it is over');
    }

    public function testThePruneTaskForgetsWhatIsPastItsRetention(): void
    {
        $messages = $this->schema->table();
        foreach ([['failed', 31], ['failed', 29], ['default', 40]] as [$queue, $days]) {
            $this->db->query(
                "INSERT INTO {$messages} (queue_name, message_type, body, headers, created_at, available_at, attempts) VALUES (?, 'T', 'x', '[]', ?, ?, 0)",
                [$queue, $this->ago($days), $this->ago($days)]
            );
        }
        foreach ([8, 6] as $days) {
            QueueTestKit::recordRun($this->db, 'T', QueueRecorder::HANDLED, 1, $this->ago($days), mode: QueueConsumerMode::Command);
        }

        (new PruneQueueHandler($this->transport))(new PruneQueue());

        $this->assertSame([29], array_map(fn (array $row): int => $this->clock->now()->diff($row['createdAt'])->days, $this->statistics->failed()));
        $this->assertSame(1, $this->statistics->pendingCount(), 'waiting work is never pruned, however old');
        $this->assertCount(2, $this->statistics->history(10), 'the runs are rows of the Journal, which prunes them itself (JournalTest)');
    }

    public function testTheKernelPrunesTheQueueEveryDay(): void
    {
        $kernel = (new KernelSchedule())->recurringMessages();
        $this->assertCount(2, $kernel, 'the queue prunes itself, and the Journal is pruned (MaintenanceTasksTest)');
        [$prune] = $kernel;
        $context = new MessageContext('yeswiki', $prune->getId(), $prune->getTrigger(), $this->clock->now());

        $this->assertSame('every 24 hours', (string)$prune->getTrigger());
        $this->assertEquals([new PruneQueue()], [...$prune->getMessages($context)]);
        $this->assertSame('queue-prune', (new PruneQueue())->taskId());
        $this->assertNotSame('QUEUE_TASK_PRUNE', (new PruneQueue())->label(), 'the label is translated');

        $ticker = self::getWiki()->services->get(QueueScheduleTicker::class);
        $this->assertInstanceOf(QueueScheduleTicker::class, $ticker);
        $this->assertContains(
            $prune->getId(),
            array_map(static fn (RecurringMessage $recurring): string => $recurring->getId(), $ticker->schedule()->getRecurringMessages()),
            'the kernel finds the providers by their tag'
        );
    }

    /**
     * The checkpoint row, written and read back, on the engines the wiki may run on.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('realServers')]
    public function testOnARealServer(array $params): void
    {
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $this->build(new DbService(new ParameterBag($params)));
        $this->schema->drop();
        $this->schema->create();

        try {
            $this->assertSame(0, $this->ticker()->tick());
            $this->clock->sleep(31 * 60);
            $this->assertSame(1, $this->ticker()->tick());
            $this->assertSame('2026-10-23 13:00:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));

            $this->checkpointDueAt(null);
            $this->assertSame(0, $this->ticker()->tick(), 'the state is read back as it was written');
            $this->assertTrue($this->ticker()->runNow('test-task'));
            $this->assertSame(1, $this->statistics->pendingCount());

            $recorder = new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock);
            $recorder->taskFinished('test-task', QueueRecorder::FAILED, 'boom');
            $recorder->taskFinished('test-task', QueueRecorder::FAILED, 'boom');
            $this->assertSame(['test-task', 2, 'failed', 'boom'], $this->summary('test-task'));
            $this->assertSame('2026-10-23 13:00:00', $this->statistics->tasks()[0]['nextDueAt']?->format('Y-m-d H:i:s'), 'running it by hand kept the next due time');
            $recorder->taskFinished('test-task', QueueRecorder::HANDLED, null);
            $this->assertSame(['test-task', 0, 'handled', null], $this->summary('test-task'));
        } finally {
            $this->schema->drop();
        }
    }

    /** @return array<string, array{array<string, string|bool>}> */
    public static function realServers(): array
    {
        return QueueTransportTest::realServers();
    }

    /** Points the test at $db, on a fresh clock. */
    private function build(DbService $db): void
    {
        $this->db = $db;
        $this->schema = new QueueSchema($db);
        $this->clock = new MockClock(self::START, 'UTC');
        $this->transport = new QueueTransport($db, $this->schema, clock: $this->clock);
        $this->statistics = new QueueStatistics($db, $this->schema, new JournalSchema($db), $this->transport, $this->clock);
    }

    /**
     * A ticker as each request or process gets one: nothing in memory, everything from the database.
     *
     * @param list<ProvidesSchedule> $more providers besides the test's own
     */
    private function ticker(array $more = []): QueueScheduleTicker
    {
        $provider = new class(fn (): array => $this->tasks) implements ProvidesSchedule {
            /** @param \Closure(): array<string, string> $tasks */
            public function __construct(private \Closure $tasks)
            {
            }

            public function recurringMessages(): array
            {
                $recurring = [];
                foreach (($this->tasks)() as $id => $every) {
                    $recurring[] = RecurringMessage::every($every, new QueueTestTask($id));
                }

                return $recurring;
            }
        };
        $journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));

        return new QueueScheduleTicker(
            [$provider, ...$more],
            new QueueBus($this->transport, new QueueHandlerLocator([], new ServiceLocator([])), $journal),
            new QueueCheckpoint($this->db, $this->schema, new RuntimeLock()),
            new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock),
            $this->clock,
            $journal,
        );
    }

    /** @return array{string, int, ?string, ?string} the task's id, consecutive failures, last status and last error */
    private function summary(string $taskId): array
    {
        foreach ($this->statistics->tasks() as $task) {
            if ($task['taskId'] === $taskId) {
                return [$task['taskId'], $task['consecutiveFailures'], $task['lastStatus'], $task['lastError']];
            }
        }
        $this->fail("no row for {$taskId}");
    }

    private function ago(int $days): string
    {
        return $this->clock->now()->modify("-{$days} days")->format('Y-m-d H:i:s');
    }

    /** Writes the checkpoint's due time as another process would, or takes it away. */
    private function checkpointDueAt(?string $dueAt): void
    {
        (new QueueTaskRows($this->db, $this->schema, $this->clock))->update(
            QueueCheckpoint::ROW,
            static fn (array $fields): array => ['next_due_at' => $dueAt] + $fields
        );
    }
}

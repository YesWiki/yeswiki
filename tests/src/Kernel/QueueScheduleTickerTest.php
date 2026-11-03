<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Message\PruneQueue;
use YesWiki\Kernel\Message\PruneQueueHandler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** The scheduler only feeds the queue: a task is due a trigger's run after its last dispatch, or after it first appeared, as the Journal tells. */
#[CoversClass(QueueScheduleTicker::class)]
#[CoversClass(QueueRecorder::class)]
#[CoversClass(ScheduleHistory::class)]
#[CoversClass(KernelSchedule::class)]
#[CoversClass(PruneQueueHandler::class)]
class QueueScheduleTickerTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private string $lockDir;
    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private ScheduleHistory $history;
    private RuntimeLock $locks;

    /** The wiki's status as the parameters hold it; the ticker reads the config file (setWikiStatus() writes both). */
    private ParameterBag $params;
    private string $configFile;
    private string|false $previousConfigFile;

    /** @var array<string, string> task id => interval: what the test provider declares, rebuilt at each call as a module's provider would */
    private array $tasks = ['test-task' => '30 minutes'];

    /** Whether the digests' provider of testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks throws. */
    private bool $digestsBroken = false;

    protected function setUp(): void
    {
        $this->params = new ParameterBag(['wiki_status' => 'running']);
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-ticker-locks-' . bin2hex(random_bytes(4));
        $this->locks = QueueTestKit::locksIn($this->lockDir);
        $this->build(new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            // The query log is how testATickWithNothingDueWritesNothing counts what a tick costs.
            'debug' => true,
        ])));
        $this->schema->create();
        (new JournalSchema($this->db))->create();
        $this->configFile = tempnam(sys_get_temp_dir(), 'yw-config-') . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $this->setWikiStatus('running');
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->history, $this->transport, $this->schema, $this->db);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        @unlink(substr($this->configFile, 0, -4));
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        @unlink(substr($this->file, 0, -3));
        foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
            @unlink($lock);
        }
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    public function testATaskSeenForTheFirstTimeIsDeclaredAndWaitsItsFirstSlot(): void
    {
        $ticker = $this->ticker();
        $this->assertSame(0, $ticker->tick());

        $this->assertSame(self::START, $this->history->of('test-task')?->declaredAt?->format('Y-m-d H:i:s'));
        $this->assertSame(0, $this->statistics->pendingCount(), 'its appearance is noted, nothing is sent');
        $this->assertSame('2026-10-23 12:30:00', $ticker->dueAt()?->format('Y-m-d H:i:s'));

        $this->clock->sleep(29 * 60);
        $this->assertSame(0, $this->ticker()->tick());
        $this->assertSame(1, $this->rows(QueueJournalEntry::TASK_DECLARED), 'noted once');
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
        $task = $this->task('test-task');
        $this->assertSame('Test task test-task', $task['label']);
        $this->assertSame(1800, $task['everySeconds']);
        $this->assertSame('2026-10-23 12:31:00', $task['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 13:01:00', $task['nextDueAt']?->format('Y-m-d H:i:s'), 'the interval counts from the dispatch');
        $this->assertSame('2026-10-23 13:01:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
    }

    /** A periodical trigger anchors on the first date it is asked about: whatever a ticker is asked first, the interval runs from the task's anchor. */
    public function testAnIntervalCountsFromTheAnchorWhateverAskedFirst(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(31 * 60);

        $ticker = $this->ticker();
        $ticker->tasks();
        $ticker->declaredTaskIds();
        $this->assertSame(1, $ticker->tick());

        $this->clock->sleep(60);
        $this->assertSame('2026-10-23 13:01:00', ($ticker->task('test-task') ?? $this->fail('no task'))['nextDueAt']?->format('Y-m-d H:i:s'));
        $this->clock->modify('2026-10-23 12:45:00');
        $this->assertSame(0, $ticker->tick());
    }

    public function testAMissedTaskIsCaughtUpOnlyOnce(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(5 * 3600);

        $this->assertSame(1, $ticker->tick(), 'ten missed runs, one catch-up');
        $this->assertSame(0, $ticker->tick());
        $this->clock->modify('2026-10-23 17:29:00');
        $this->assertSame(0, $this->ticker()->tick());
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testACronTaskWaitsForItsNextSlot(): void
    {
        $this->tasks = [];
        $daily = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessage::cron('0 7 * * *', new QueueTestTask('daily'), 'UTC')];
            }
        };

        $this->assertSame(0, $this->ticker([$daily])->tick());
        $this->assertNotNull($this->history->of('daily')?->declaredAt, 'the first tick notes the task');

        $this->clock->modify('2026-10-24 06:59:00');
        $this->assertSame(0, $this->ticker([$daily])->tick());
        $this->clock->modify('2026-10-24 07:00:00');
        $this->assertSame(1, $this->ticker([$daily])->tick());
    }

    /** Digests switched off then on again: the task finds its last dispatch, and a slot that passed meanwhile runs once. */
    public function testATaskThatComesBackRunsOnceIfItMissedASlot(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(31 * 60);
        $this->assertSame(1, $this->ticker()->tick());
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");

        $every = $this->tasks;
        $this->tasks = [];
        $this->clock->sleep(3600);
        $this->assertSame(0, $this->ticker()->tick());
        $this->clock->sleep(3600);
        $this->tasks = $every;

        $this->assertSame(1, $this->ticker()->tick());
        $this->assertSame(1, $this->rows(QueueJournalEntry::TASK_DECLARED), 'it is not seen anew');
    }

    /** What the user chose: a task run by hand counts its next run from then. */
    public function testRunNowShiftsTheSchedule(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(10 * 60);

        $ticker = $this->ticker();
        $this->assertTrue($ticker->runNow('test-task'));
        $this->assertFalse($ticker->runNow('no-such-task'));

        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 12:40:00', $this->task('test-task')['nextDueAt']?->format('Y-m-d H:i:s'));
        $this->clock->modify('2026-10-23 12:31:00');
        $this->assertSame(0, $this->ticker()->tick());
    }

    /** A run of the task still waits: nothing more is queued, but the Scheduler did send it then, and counts from it. */
    public function testAnAbsorbedDispatchStillMovesTheAnchor(): void
    {
        $this->ticker()->tick();
        $this->transport->send(new Envelope(new QueueTestTask()));
        $this->clock->sleep(10 * 60);

        $this->assertTrue($this->ticker()->runNow('test-task'));

        $this->assertSame(1, $this->statistics->pendingCount(), 'a run still waiting is not queued twice');
        $this->assertSame('2026-10-23 12:10:00', $this->history->of('test-task')?->lastDispatchedAt?->format('Y-m-d H:i:s'));
    }

    public function testOnlyOneProcessTicksAtATime(): void
    {
        $held = $this->locks->acquire(QueueScheduleTicker::LOCK);
        $this->assertNotNull($held);
        $this->assertTrue($this->locks->tryLock($held));

        try {
            $this->clock->sleep(3600);
            $this->assertSame(0, $this->ticker()->tick(), 'while another process ticks, this one does not');
            $this->assertSame(0, $this->rows(), 'nor writes anything');
        } finally {
            $this->locks->release($held);
        }

        $this->assertSame(0, $this->ticker()->tick());
        $this->assertSame('2026-10-23 13:30:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
        $this->clock->sleep(1801);
        $this->assertSame(1, $this->ticker()->tick(), 'a tick lets go of the lock when it is over');
    }

    /** The due time held in memory goes stale when another process dispatches: it is read again once a minute. */
    public function testALongLivedTickerSeesADispatchWrittenElsewhere(): void
    {
        $long = $this->ticker();
        $long->tick();
        $this->clock->sleep(31 * 60);
        $this->assertSame('2026-10-23 12:30:00', $long->dueAt()?->format('Y-m-d H:i:s'));

        $this->assertSame(1, $this->ticker()->tick(), 'another process ticks');
        $this->assertSame('2026-10-23 12:30:00', $long->dueAt()?->format('Y-m-d H:i:s'), 'held in memory');

        $this->clock->sleep(60);
        $this->assertSame('2026-10-23 13:01:00', $long->dueAt()?->format('Y-m-d H:i:s'));
        $this->assertSame(0, $long->tick());
    }

    public function testNoTaskDeclaredMeansNothingIsEverDue(): void
    {
        $this->tasks = [];

        $this->assertSame(0, $this->ticker()->tick());
        $this->clock->sleep(86400);
        $this->assertSame(0, $this->ticker()->tick());

        $this->assertSame(0, $this->rows());
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    /** Without a task id there is no anchor to find in the Journal: it would be sent at every tick. */
    public function testARecurringMessageThatIsNotAScheduledTaskIsJournaledAndSkipped(): void
    {
        $this->tasks = [];
        $plain = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessage::every('30 minutes', new QueueTestMessage('x'))];
            }
        };

        $this->assertSame(0, $this->ticker([$plain])->tick());
        $this->clock->sleep(31 * 60);
        $this->assertSame(0, $this->ticker([$plain])->tick());

        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertGreaterThan(0, $this->rows('queue.schedule_provider_failed'));
    }

    public function testTheDeclaredTasksAreWhatTheProvidersDeclareNow(): void
    {
        $this->tasks = ['test-task' => '30 minutes', 'other' => '1 hour'];
        $this->assertSame(['test-task', 'other'], $this->ticker()->declaredTaskIds());

        unset($this->tasks['other']);
        $this->assertSame(['test-task'], $this->ticker()->declaredTaskIds());
    }

    /** A deleted data source or a disabled extension: its task leaves the list, and its rows are the Journal's to prune. */
    public function testATaskThatLeftTheScheduleIsNoLongerListed(): void
    {
        $this->tasks = ['test-task' => '30 minutes', 'gone' => '30 minutes'];
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(31 * 60);
        $ticker->tick();
        $this->assertSame(['gone', 'test-task'], array_column($this->ticker()->tasks(), 'taskId'));
        $rows = $this->rows();

        unset($this->tasks['gone']);
        $this->clock->sleep(31 * 60);
        $this->ticker()->tick();

        $this->assertSame(['test-task'], array_column($this->ticker()->tasks(), 'taskId'));
        $this->assertNull($this->ticker()->task('gone'));
        $this->assertSame($rows + 1, $this->rows(), 'one more dispatch, and nothing deleted');
    }

    /**
     * A provider that throws (the digests' subscriptions unreadable, no triples table) is journaled and left out of that tick; the other tasks are still dispatched, and its tasks are still listed from the Journal, and still judged.
     *
     * Left out, they would leave nothing for `queue-task-failing` to find: the digests would stop with nothing to say so.
     */
    public function testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks(): void
    {
        $digests = new class(function (): array {
            if ($this->digestsBroken) {
                throw new \RuntimeException('no triples table');
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
        $tasks = $this->ticker([$digests])->tasks();
        $this->assertSame(['digest', 'test-task'], array_column($tasks, 'taskId'), 'the digest task is kept');
        $this->assertSame(['Test task digest', 0, null], [$tasks[0]['label'], $tasks[0]['everySeconds'], $tasks[0]['nextDueAt']], 'from the Journal, with no schedule to say more');
        $this->assertSame('2026-10-23 12:31:00', $tasks[0]['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $declared = $this->ticker([$digests])->declaredTaskIds();
        sort($declared);
        $this->assertSame(['digest', 'test-task'], $declared, 'and still judged by the health checks');
        $journaled = $this->db->loadAll('SELECT action, target, context FROM ' . (new JournalSchema($this->db))->table() . " WHERE action = 'queue.schedule_provider_failed'");
        $this->assertNotEmpty($journaled);
        $this->assertSame($digests::class, $journaled[0]['target']);
        $this->assertStringContainsString('no triples table', (string)$journaled[0]['context']);

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

    public function testConsecutiveFailuresAreCounted(): void
    {
        $this->tasks = ['x' => '30 minutes'];
        $recorder = $this->recorder();

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
        $recorder = $this->recorder();

        $recorder->messageFinished(1, QueueTestTask::class, 'test-task', QueueRecorder::KILLED, QueueConsumerMode::Request, (float)$this->clock->now()->format('U'), QueueTransport::killedCause('request'));
        $recorder->messageFinished(2, QueueTestMessage::class, null, QueueRecorder::FAILED, QueueConsumerMode::Request, (float)$this->clock->now()->format('U'), 'boom');

        $this->assertSame(['test-task', 1, 'killed', QueueTransport::killedCause('request')], $this->summary('test-task'));
        $this->assertSame(['test-task'], array_keys($this->history->all()), 'a message that is no task leaves no task row');
    }

    public function testATickWithNothingDueWritesNothing(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $queries = count($this->db->getQueryLog());

        $this->clock->sleep(30);
        $ticker->tick();
        $this->assertCount($queries, $this->db->getQueryLog(), 'a blocking consumer ticks every second: the due time is held in memory');

        $this->clock->sleep(31);
        $ticker->tick();
        $since = array_column(array_slice($this->db->getQueryLog(), $queries), 'query');
        $this->assertSame([], preg_grep('/^\s*(INSERT|UPDATE|DELETE)\b/i', $since), 'nothing is written');
        $journal = (new JournalSchema($this->db))->table();
        $this->assertLessThanOrEqual(1, count(array_filter($since, static fn (string $query): bool => str_contains($query, $journal))), 'once a minute the Journal is read again, and that is all');
    }

    /** A hibernated wiki writes nothing: the ticker neither dispatches nor notes anything, whoever calls it. */
    public function testAHibernatedWikiTicksNothing(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $before = $this->rows();
        $this->setWikiStatus('hibernate');
        $this->clock->sleep(31 * 60);

        $this->assertSame(0, $ticker->tick());
        $this->assertSame(0, $this->ticker()->tick(), 'nor does a ticker built while the wiki sleeps');

        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame($before, $this->rows());
        $this->assertNull($this->task('test-task')['lastDispatchedAt'], 'no task was sent');
    }

    /** Once the wiki wakes up, what it missed while asleep runs once per task, not once per missed run. */
    public function testAWokenWikiRunsEachMissedTaskOnce(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->setWikiStatus('hibernate');
        $this->clock->sleep(5 * 3600);
        $this->assertSame(0, $ticker->tick());

        $this->setWikiStatus('running');
        $this->clock->sleep(60);

        $this->assertSame(1, $ticker->tick(), 'ten runs missed while asleep, one catch-up');
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 17:31:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
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
            QueueTestKit::recordRun($this->db, 'T', QueueRecorder::HANDLED, 1, $this->ago($days), mode: QueueConsumerMode::Api);
        }

        (new PruneQueueHandler($this->transport))(new PruneQueue());

        $this->assertSame([29], array_map(fn (array $row): int => $this->clock->now()->diff($row['createdAt'])->days, $this->statistics->failed()));
        $this->assertSame(1, $this->statistics->pendingCount(), 'waiting work is never pruned, however old');
        $this->assertCount(2, $this->statistics->history(10), 'the runs are rows of the Journal, which prunes them itself (JournalTest)');
    }

    public function testTheKernelPrunesTheQueueEveryDay(): void
    {
        $ticker = self::getWiki()->services->get(QueueScheduleTicker::class);
        $kernel = (new KernelSchedule())->recurringMessages();
        $this->assertCount(2, $kernel, 'the queue prunes itself, and the Journal is pruned (MaintenanceTasksTest)');
        [$prune] = $kernel;
        $context = new MessageContext('yeswiki', $prune->getId(), $prune->getTrigger(), $this->clock->now());

        $this->assertSame('every 24 hours', (string)$prune->getTrigger());
        $this->assertEquals([new PruneQueue()], [...$prune->getMessages($context)]);
        $this->assertSame('queue-prune', (new PruneQueue())->taskId());
        $this->assertNotSame('QUEUE_TASK_PRUNE', (new PruneQueue())->label(), 'the label is translated');

        $this->assertInstanceOf(QueueScheduleTicker::class, $ticker);
        $this->assertContains(
            $prune->getId(),
            array_map(static fn (RecurringMessage $recurring): string => $recurring->getId(), $ticker->schedule()->getRecurringMessages()),
            'the kernel finds the providers by their tag'
        );
    }

    /**
     * The task rows, written and read back, on the engines the wiki may run on.
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
        $journal = new JournalSchema($this->db);
        $this->schema->drop();
        $journal->drop();
        $this->schema->create();
        $journal->create();

        try {
            $this->assertSame(0, $this->ticker()->tick());
            $this->clock->sleep(31 * 60);
            $this->assertSame(1, $this->ticker()->tick());
            $this->assertSame('2026-10-23 13:01:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
            $this->assertSame(0, $this->ticker()->tick(), 'the dispatch is read back as it was written');

            $this->clock->sleep(60);
            $this->assertTrue($this->ticker()->runNow('test-task'));
            $this->assertSame(1, $this->statistics->pendingCount());
            $this->assertSame('2026-10-23 13:02:00', $this->task('test-task')['nextDueAt']?->format('Y-m-d H:i:s'), 'running it by hand moved the next due time');

            $recorder = $this->recorder();
            $recorder->taskFinished('test-task', QueueRecorder::FAILED, 'boom');
            $recorder->taskFinished('test-task', QueueRecorder::FAILED, 'boom');
            $this->assertSame(['test-task', 2, 'failed', 'boom'], $this->summary('test-task'));
            $recorder->taskFinished('test-task', QueueRecorder::HANDLED, null);
            $this->assertSame(['test-task', 0, 'handled', null], $this->summary('test-task'));
        } finally {
            $this->schema->drop();
            $journal->drop();
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
        $this->statistics = new QueueStatistics($db, $this->schema, new JournalSchema($db), $this->transport, new QueueTaskRows($db, $this->schema, $this->clock), $this->clock);
        $this->history = new ScheduleHistory($db, new JournalSchema($db));
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
        $recorder = $this->recorder();

        return new QueueScheduleTicker(
            [$provider, ...$more],
            new QueueBus($this->transport, new QueueHandlerLocator([], new ServiceLocator([])), $journal, $recorder, QueueTestKit::noRequest()),
            $this->history,
            $recorder,
            $this->locks,
            $this->clock,
            new HibernationService($this->params),
            $journal,
        );
    }

    private function recorder(): QueueRecorder
    {
        return new QueueRecorder($this->db, new QueueTaskRows($this->db, $this->schema, $this->clock), new JournalSchema($this->db), $this->clock);
    }

    /**
     * That task as a fresh ticker lists it.
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}
     */
    private function task(string $taskId): array
    {
        return $this->ticker()->task($taskId) ?? $this->fail("no task {$taskId}");
    }

    /** @return array{string, int, ?string, ?string} the task's id, consecutive failures, last status and last error */
    private function summary(string $taskId): array
    {
        $task = $this->task($taskId);

        return [$task['taskId'], $task['consecutiveFailures'], $task['lastStatus'], $task['lastError']];
    }

    /** How many Journal rows there are, of that action or of any. */
    private function rows(?string $action = null): int
    {
        $table = (new JournalSchema($this->db))->table();

        return (int)$this->db->scalar("SELECT COUNT(*) FROM {$table}" . ($action === null ? '' : ' WHERE action = ?'), 0, $action === null ? [] : [$action]);
    }

    private function ago(int $days): string
    {
        return $this->clock->now()->modify("-{$days} days")->format('Y-m-d H:i:s');
    }

    /** Sets the wiki's status as an archive or the hibernation setting does: in the config file, which the ticker reads, and in the parameters. */
    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
        $this->params->set('wiki_status', $status);
    }
}

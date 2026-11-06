<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Message\PruneQueue;
use YesWiki\Kernel\Message\PruneQueueHandler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DatabaseClock;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleGrid;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** The scheduler only feeds the queue: a task is due on its grid's first slot after its last dispatch, or after it first appeared, as the Journal tells. */
#[CoversClass(QueueScheduleTicker::class)]
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

    /** @var array<string, string|false> the environment overdue() clears: a wiki loaded earlier in the run puts its .env there, and CI's says `api` */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach ([QueueSettings::ENV_MODE, QueueSettings::ENV_BINARY] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
        }
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
        foreach ($this->savedEnv as $name => $before) {
            putenv($before === false ? $name : "$name=$before");
        }
        $this->savedEnv = [];
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
        $this->assertSame('2026-10-23 13:00:00', $task['nextDueAt']?->format('Y-m-d H:i:s'), 'the next slot, not a period after the dispatch');
        $this->assertSame('2026-10-23 13:00:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
    }

    /** Ticks that come late do not add up: every 30 minutes stays on :00 and :30. */
    public function testALateTickDoesNotPushTheNextSlotBack(): void
    {
        $this->ticker()->tick();
        $this->clock->modify('2026-10-23 12:33:00');
        $this->assertSame(1, $this->ticker()->tick());
        $this->assertSame('2026-10-23 13:00:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");

        $this->clock->modify('2026-10-23 13:07:00');
        $this->assertSame(1, $this->ticker()->tick());
        $this->assertSame('2026-10-23 13:30:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");

        $this->clock->modify('2026-10-23 13:29:59');
        $this->assertSame(0, $this->ticker()->tick());
        $this->clock->modify('2026-10-23 13:30:00');
        $this->assertSame(1, $this->ticker()->tick());
    }

    public function testACronTaskWaitsForItsNextSlot(): void
    {
        $this->tasks = [];
        $daily = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('daily'), 'UTC')];
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

    /** A task run by hand waits for the slot after it: the grid does not move. */
    public function testRunNowKeepsTheGrid(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(10 * 60);

        $ticker = $this->ticker();
        $this->assertTrue($ticker->runNow('test-task'));
        $this->assertFalse($ticker->runNow('no-such-task'));

        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 12:30:00', $this->task('test-task')['nextDueAt']?->format('Y-m-d H:i:s'));
        $this->clock->modify('2026-10-23 12:29:00');
        $this->assertSame(0, $this->ticker()->tick());
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");
        $this->clock->modify('2026-10-23 12:30:00');
        $this->assertSame(1, $this->ticker()->tick());
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
        $this->assertSame('2026-10-23 13:00:00', $long->dueAt()?->format('Y-m-d H:i:s'));
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
                return [RecurringMessageTimezoned::every('30 minutes', new QueueTestMessage('x'))];
            }
        };

        $this->assertSame(0, $this->ticker([$plain])->tick());
        $this->clock->sleep(31 * 60);
        $this->assertSame(0, $this->ticker([$plain])->tick());

        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame(1, $this->rows('queue.schedule_provider_failed', QueueTestMessage::class), 'one row, that message');
        $this->assertSame(2, $this->repeats('queue.schedule_provider_failed'), 'said by each of the two ticks that send what is due');
        $ticker = $this->ticker([$plain]);
        $ticker->tasks();
        $ticker->declaredTaskIds();
        $this->assertSame(2, $this->repeats('queue.schedule_provider_failed'), 'the reads of the schedule are silent');
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
     * A provider that throws (the digests' subscriptions unreadable, no triples table) is journaled and left out of that tick; the other tasks are still dispatched, and its tasks are still listed from the Journal, with the period their rows carry.
     *
     * Left out, they would leave nothing for `queue-task-failing` or `queue-task-overdue` to find: the digests would stop with nothing to say so.
     */
    public function testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks(): void
    {
        $digests = $this->digests();
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
        $this->assertSame(['Test task digest', 1800, '2026-10-23 13:00:00'], [$tasks[0]['label'], $tasks[0]['everySeconds'], $tasks[0]['nextDueAt']?->format('Y-m-d H:i:s')], 'from the Journal: the slot of its period after its last dispatch');
        $this->assertSame('2026-10-23 12:31:00', $tasks[0]['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $declared = $this->ticker([$digests])->declaredTaskIds();
        sort($declared);
        $this->assertSame(['digest', 'test-task'], $declared, 'and still declared');
        $journaled = $this->db->loadAll('SELECT action, target, context FROM ' . (new JournalSchema($this->db))->table() . " WHERE action = 'queue.schedule_provider_failed'");
        $this->assertNotEmpty($journaled);
        $this->assertSame($digests::class, $journaled[0]['target']);
        $this->assertStringContainsString('no triples table', (string)$journaled[0]['context']);

        $this->digestsBroken = false;
        $this->assertSame(['test-task', 'digest'], $this->ticker([$digests])->declaredTaskIds(), 'back as soon as it declares again');
    }

    /** The digests' provider throws and nothing sends them any more: once a period past due, the health checks say so, as they would of a task that stopped. */
    public function testATaskWhoseProviderKeepsThrowingIsOverdueAfterAPeriod(): void
    {
        $digests = $this->digests();
        $this->ticker([$digests])->tick();
        $this->clock->sleep(31 * 60);
        $this->assertSame(2, $this->ticker([$digests])->tick());
        $this->digestsBroken = true;

        $this->clock->modify('2026-10-23 13:30:00');
        $this->ticker([$digests])->tick();
        $this->assertNull($this->overdue([$digests]), 'within one period of its due time');

        $this->clock->modify('2026-10-23 13:32:00');
        $this->ticker([$digests])->tick();
        $this->assertStringContainsString('Test task digest', (string)$this->overdue([$digests]));
        $this->assertStringNotContainsString('Test task test-task', (string)$this->overdue([$digests]), 'the task still dispatched is on time');
    }

    /** A provider that throws is said by the tick that sends what is due, the one path that writes: the jobs page, the admin badge and the end of each request read the schedule in silence. */
    public function testAProviderThatThrowsIsJournaledOnlyByTheTick(): void
    {
        $this->digestsBroken = true;
        $ticker = $this->ticker([$this->digests()]);

        $ticker->tasks();
        $ticker->task('digest');
        $ticker->dueAt();
        $ticker->declaredTaskIds();
        $ticker->runNow('test-task');
        $this->assertSame(0, $this->rows('queue.schedule_provider_failed'));

        $this->clock->sleep(31 * 60);
        $this->assertSame(1, $this->ticker([$this->digests()])->tick());
        $this->assertSame(1, $this->rows('queue.schedule_provider_failed'));
    }

    /** With nothing to schedule the next tick waits a minute like any other: a blocking consumer ticks every second. */
    public function testAnEmptyScheduleIsReadOnceAMinute(): void
    {
        $this->tasks = [];
        $ticker = $this->ticker();
        $ticker->tick();
        $queries = count($this->db->getQueryLog());

        $this->clock->sleep(30);
        $ticker->tick();

        $this->assertCount($queries, $this->db->getQueryLog());
    }

    /** Journal unreadable: the tick throws, and the next ones are silent for a minute instead of failing again every second of a blocking consumer. */
    public function testAFailingTickIsRetriedOnlyAfterAMinute(): void
    {
        $history = new class($this->db, new JournalSchema($this->db)) extends ScheduleHistory {
            public int $reads = 0;
            public bool $broken = true;

            public function all(): array
            {
                $this->reads++;
                if ($this->broken) {
                    throw new \RuntimeException('journal unreadable');
                }

                return parent::all();
            }
        };
        $ticker = $this->ticker([], $history);

        try {
            $ticker->tick();
            $this->fail('the failure is not swallowed');
        } catch (\RuntimeException $failed) {
            $this->assertSame('journal unreadable', $failed->getMessage());
        }
        $this->assertSame(1, $history->reads);

        $this->clock->sleep(30);
        $this->assertSame(0, $ticker->tick(), 'within the minute, it waits');
        $this->assertSame(1, $history->reads, 'and reads nothing');

        $this->clock->sleep(31);
        $this->assertThrows(static fn () => $ticker->tick(), 'journal unreadable');
        $this->assertSame(2, $history->reads, 'a minute on, it tries again');

        $history->broken = false;
        $this->clock->sleep(61);
        $this->assertSame(0, $ticker->tick());
        $this->assertNotNull($this->history->of('test-task')?->declaredAt, 'and works again once the Journal does');
    }

    public function testAScheduleLockThatCannotBeCreatedThrowsThenWaitsAMinute(): void
    {
        $locks = new class extends RuntimeLock {
            public int $asked = 0;

            public function acquire(string $path)
            {
                $this->asked++;

                return null;
            }
        };
        $ticker = $this->ticker([], null, $locks);

        $this->assertThrows(static fn () => $ticker->tick(), 'cannot be created');
        $this->assertSame(1, $locks->asked);
        $this->assertSame(0, $this->rows(), 'nothing was written');

        $this->clock->sleep(30);
        $this->assertSame(0, $ticker->tick());
        $this->assertSame(1, $locks->asked, 'the lock is not asked for again within the minute');

        $this->clock->sleep(31);
        $this->assertThrows(static fn () => $ticker->tick(), 'cannot be created');
        $this->assertSame(2, $locks->asked);
    }

    /** A due run the queue refuses (its table gone) is journaled, not counted, and tried again in a minute rather than at every tick. */
    public function testADueRunThatCannotBeQueuedIsRetriedInAMinute(): void
    {
        $ticker = $this->ticker();
        $ticker->tick();
        $this->clock->sleep(31 * 60);
        $this->schema->drop();

        $this->assertSame(0, $ticker->tick(), 'a run that was not queued is not counted');
        $this->assertSame(1, $this->rows('queue.dispatch_failed'));
        $this->assertNull($this->task('test-task')['lastDispatchedAt']);
        $this->assertSame('2026-10-23 12:32:00', $ticker->dueAt()?->format('Y-m-d H:i:s'), 'a minute from now, not the slot that has passed');

        $this->clock->sleep(30);
        $queries = count($this->db->getQueryLog());
        $this->assertSame(0, $ticker->tick());
        $this->assertCount($queries, $this->db->getQueryLog(), 'nothing is tried within the minute');

        $this->schema->create();
        $this->clock->sleep(31);
        $this->assertSame(1, $ticker->tick());
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    /**
     * A run whose dispatch the Journal refuses is not queued. Queued without it, the task's anchor would not move; and once the pass
     * took that run, which clears its dedup key, the next tick would queue the task again: a second digest to every subscriber.
     */
    public function testARunWhoseDispatchTheJournalRefusesIsNotQueuedSoItRunsOnce(): void
    {
        $this->ticker()->tick();
        $this->clock->sleep(31 * 60);
        $journal = (new JournalSchema($this->db))->table();
        $this->db->query("CREATE TRIGGER refuse_dispatch BEFORE INSERT ON {$journal} WHEN NEW.action = '" . QueueJournalEntry::TASK_DISPATCHED . "'"
            . " BEGIN SELECT RAISE(ABORT, 'database is locked'); END");

        $this->assertSame(0, $this->ticker()->tick(), 'a run whose dispatch was refused is not counted as sent');
        $this->transport->get();
        $this->db->query('DROP TRIGGER refuse_dispatch');
        $this->clock->sleep(60);

        $this->assertSame(1, $this->ticker()->tick(), 'the task is still due, and queued once the Journal takes it');
        $this->transport->get();
        $this->clock->sleep(60);
        $this->assertSame(0, $this->ticker()->tick(), 'its anchor moved: nothing is due before the next slot');

        $this->assertSame(1, (int)$this->db->scalar("SELECT COUNT(*) FROM {$this->schema->table()}"), 'one run of the task in all');
    }

    /** A task whose trigger has ended has no next run: it is never sent, and the schedule is read again in a minute rather than at every tick. */
    public function testATaskWhoseTriggerHasEndedIsNeverSent(): void
    {
        $this->tasks = [];
        $ended = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::trigger(new PeriodicalTrigger('30 minutes', '2026-10-23 10:00:00', '2026-10-23 11:00:00'), new QueueTestTask('ended'))];
            }
        };
        $ticker = $this->ticker([$ended]);

        $this->assertSame(0, $ticker->tick());
        $this->assertNotNull($this->history->of('ended')?->declaredAt);
        $this->assertSame('2026-10-23 12:01:00', $ticker->dueAt()?->format('Y-m-d H:i:s'), 'no next run: read again in a minute');

        $this->clock->sleep(3600);
        $this->assertSame(0, $this->ticker([$ended])->tick());
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertNull($this->task('ended', [$ended])['nextDueAt'], 'listed, with no next run');
    }

    /** The period of a cron task is the gap between its runs, as the Journal and the jobs page tell it, so the health checks can judge the digests. */
    public function testACronTasksPeriodIsTheGapBetweenItsRuns(): void
    {
        $this->tasks = [];
        $crons = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [
                    RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('daily'), 'UTC'),
                    RecurringMessageTimezoned::cron('0 7 1 * *', new QueueTestTask('monthly'), 'UTC'),
                ];
            }
        };
        $this->clock->modify('2026-01-15 08:00:00');

        $this->assertSame(86400, $this->task('daily', [$crons])['everySeconds']);
        $this->assertSame(28 * 86400, $this->task('monthly', [$crons])['everySeconds'], 'never seen: the gap between the next two runs, February');
        $this->ticker([$crons])->tick();
        $this->assertSame(28 * 86400, $this->history->of('monthly')?->everySeconds, 'the declaration carries it');

        $this->clock->modify('2026-02-01 07:00:00');
        $this->assertSame(2, $this->ticker([$crons])->tick());

        $this->assertSame(86400, $this->history->of('daily')?->everySeconds);
        $this->assertSame(28 * 86400, $this->history->of('monthly')?->everySeconds, 'the dispatch carries the gap from the slot it ran on, to the next');
        $this->assertSame(31 * 86400, $this->task('monthly', [$crons])['everySeconds'], 'listed since, the gap of the runs to come: March');
    }

    /** A task runs on the wall clock of the timezone it is declared in, whatever the clock's: midnight in Paris is 22:00 UTC in summer. */
    public function testATaskRunsOnTheWallClockOfItsTimezone(): void
    {
        $this->tasks = [];
        $paris = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::every('24 hours', new QueueTestTask('daily'), 'Europe/Paris')];
            }
        };
        $this->ticker([$paris])->tick();

        $this->assertSame('2026-10-23 22:00:00', $this->task('daily', [$paris])['nextDueAt']?->format('Y-m-d H:i:s'));
        $this->clock->modify('2026-10-23 21:59:00');
        $this->assertSame(0, $this->ticker([$paris])->tick());
        $this->clock->modify('2026-10-23 22:00:00');
        $this->assertSame(1, $this->ticker([$paris])->tick());
    }

    /**
     * A timezone that does not exist (the wiki's `timezone` mistyped) stops nothing: the task runs in PHP's timezone, the other
     * tasks run, and each tick says the name. Symfony's cron() threw on the first run asked for, which stopped the whole schedule.
     */
    public function testAnUnknownTimezoneStopsNothingAndIsSaidAtEachTick(): void
    {
        $mistyped = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('digest'), 'Europe/Pariss')];
            }
        };

        $this->assertNull($this->ticker([$mistyped])->dueAt(), 'never seen: due now, and no throw');
        $this->assertSame(0, $this->ticker([$mistyped])->tick());
        $this->assertNotNull($this->history->of('digest')?->declaredAt);
        $this->assertSame('2026-10-24 07:00:00', $this->task('digest', [$mistyped])['nextDueAt']?->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'), "at 7 in PHP's timezone");

        $this->clock->sleep(31 * 60);
        $this->assertSame(1, $this->ticker([$mistyped])->tick(), 'the other tasks run');
        $this->assertSame(2, $this->repeats('queue.schedule_provider_failed'), 'said at each tick');
        $table = (new JournalSchema($this->db))->table();
        $this->assertStringContainsString('Europe/Pariss', (string)$this->db->scalar("SELECT context FROM {$table} WHERE action = ?", null, ['queue.schedule_provider_failed']));
        $this->assertSame($mistyped::class, $this->db->scalar("SELECT target FROM {$table} WHERE action = ?", null, ['queue.schedule_provider_failed']));
    }

    /** A provider must say which timezone each task runs in: a bare RecurringMessage is journaled at the tick and left out, and the others run. */
    public function testABareRecurringMessageIsJournaledAndLeftOut(): void
    {
        $bare = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                /* @phpstan-ignore return.type (what the test checks: a provider that declares no timezone) */
                return [RecurringMessage::every('30 minutes', new QueueTestTask('bare'))];
            }
        };

        $this->assertSame(0, $this->ticker([$bare])->tick());

        $this->assertNull($this->history->of('bare'), 'not scheduled');
        $this->assertNotNull($this->history->of('test-task')?->declaredAt, 'the others are');
        $this->assertSame(1, $this->rows('queue.schedule_provider_failed', $bare::class));
        $this->assertNotContains('bare', $this->ticker([$bare])->declaredTaskIds());
    }

    /** A tick sends the tasks whose slot has come and only those: a task not due yet stays unsent while another one runs. */
    public function testATickSendsOnlyTheTasksWhoseSlotHasCome(): void
    {
        $this->tasks = ['often' => '30 minutes', 'daily' => '24 hours'];
        $this->ticker()->tick();
        $this->clock->sleep(31 * 60);

        $this->assertSame(1, $this->ticker()->tick(), 'often, not daily');
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertNull($this->task('daily')['lastDispatchedAt']);
    }

    /** What a request pays to know whether the schedule is due:the Journal read once, however many tasks, and nothing within the minute. */
    public function testKnowingWhetherTheScheduleIsDueReadsTheJournalOnce(): void
    {
        $this->tasks = ['a' => '30 minutes', 'b' => '1 hour', 'c' => '24 hours'];
        $this->ticker()->tick();
        $this->clock->sleep(120);

        $ticker = $this->ticker();
        $journal = (new JournalSchema($this->db))->table();
        $before = count($this->db->getQueryLog());
        $this->assertNotNull($ticker->dueAt(), 'every task has its history: the next run is a date');
        $reads = array_filter(array_slice(array_column($this->db->getQueryLog(), 'query'), $before), static fn (string $query): bool => str_contains($query, $journal));
        $this->assertCount(1, $reads, 'one read of the Journal for the three tasks');

        $before = count($this->db->getQueryLog());
        $ticker->dueAt();
        $this->assertCount($before, $this->db->getQueryLog(), 'asked again within the minute, it reads nothing');
    }

    /** The Scheduler refuses only a message declared twice as is; the same task under two triggers is accepted, and must still be one task. */
    public function testTheSameTaskIdUnderTwoTriggersIsScheduledOnce(): void
    {
        $twoTriggers = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [
                    RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('x')),
                    RecurringMessageTimezoned::every('1 hour', new QueueTestTask('x')),
                ];
            }
        };
        $this->ticker([$twoTriggers])->tick();
        $this->assertSame(1, $this->rows(QueueJournalEntry::TASK_DECLARED, 'x'), 'noted once');

        $this->clock->sleep(31 * 60);
        $this->assertSame(2, $this->ticker([$twoTriggers])->tick(), 'test-task and x, x once');
        $this->assertSame(2, $this->statistics->pendingCount());

        $ticker = $this->ticker([$twoTriggers]);
        $this->assertSame(['test-task', 'x'], array_column($ticker->tasks(), 'taskId'));
        $this->assertSame(1800, ($ticker->task('x') ?? $this->fail('no task x'))['everySeconds'], 'the first trigger declared wins');
        $declared = $ticker->declaredTaskIds();
        sort($declared);
        $this->assertSame(['test-task', 'x'], $declared);
    }

    /** A task the Scheduler has not seen yet is due at the next tick, which notes it: the jobs page shows it due, not with no date. */
    public function testATaskNeverSeenIsListedDueNow(): void
    {
        $this->assertSame(self::START, $this->task('test-task')['nextDueAt']?->format('Y-m-d H:i:s'));
        $this->assertSame(1800, $this->task('test-task')['everySeconds']);
    }

    /** The Scheduler refuses a message declared twice: refused for the whole schedule, every task of every provider would stop, the health checks with them. */
    public function testATaskDeclaredTwiceIsScheduledOnceAndStopsNothing(): void
    {
        $twice = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [
                    RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('digest')),
                    RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('digest')),
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

        $this->assertSame('2026-10-23 17:01:00', $ticker->dueAt()?->format('Y-m-d H:i:s'), 'a hibernated wiki is looked at again in a minute');

        $this->setWikiStatus('running');
        $this->assertSame(0, $ticker->tick(), 'awake, but not before the minute is up');
        $this->clock->sleep(60);

        $this->assertSame(1, $ticker->tick(), 'ten runs missed while asleep, one catch-up');
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame('2026-10-23 17:30:00', $this->ticker()->dueAt()?->format('Y-m-d H:i:s'));
        $this->clock->modify('2026-10-23 17:29:00');
        $this->assertSame(0, $this->ticker()->tick(), 'a ticker built later waits for the slot too');
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
        $pruning = array_values(array_filter(
            (new KernelSchedule())->recurringMessages(),
            fn (RecurringMessageTimezoned $recurring): bool => [...$recurring->getMessages(new MessageContext('yeswiki', $recurring->getId(), $recurring->getTrigger(), $this->clock->now()))] == [new PruneQueue()]
        ));
        $this->assertCount(1, $pruning, 'the queue prunes itself, once (the Journal is pruned by MaintenanceTasksTest)');
        [$prune] = $pruning;
        $context = new MessageContext('yeswiki', $prune->getId(), $prune->getTrigger(), $this->clock->now());

        $this->assertSame('every 24 hours', (string)$prune->getTrigger());
        $this->assertEquals([new PruneQueue()], [...$prune->getMessages($context)]);
        $this->assertSame('queue-prune', (new PruneQueue())->taskId());
        $this->assertNotSame('QUEUE_TASK_PRUNE', (new PruneQueue())->label(), 'the label is translated');

        $this->assertInstanceOf(QueueScheduleTicker::class, $ticker);
        $this->assertContains(
            $prune->getId(),
            array_map(static fn (RecurringMessageTimezoned $recurring): string => $recurring->getId(), $ticker->schedule()),
            'the kernel finds the providers by their tag'
        );
    }

    /**
     * The wiki's tasks run in its timezone and the queue goes by the database's clock: the wiring a hand-built ticker does not have.
     * A task that names no timezone runs in PHP's (RecurringMessageTimezoned::wikiTimezone()), which YesWikiInit set to the wiki's.
     */
    public function testTheKernelRunsTheTasksInTheWikisTimezoneAndGoesByTheDatabaseClock(): void
    {
        $services = self::getWiki()->services;

        $this->assertInstanceOf(DatabaseClock::class, $services->get(ClockInterface::class));
        $this->assertSame($services->get(ParameterBagInterface::class)->get('timezone'), RecurringMessageTimezoned::wikiTimezone()->getName());
    }

    /** Points the test at $db, on a fresh clock. */
    private function build(DbService $db): void
    {
        $this->db = $db;
        $this->schema = new QueueSchema($db);
        $this->clock = new MockClock(self::START, 'UTC');
        $this->transport = new QueueTransport($db, $this->schema, clock: $this->clock);
        $this->statistics = new QueueStatistics($db, $this->schema, new JournalSchema($db), $this->transport, $this->clock);
        $this->history = new ScheduleHistory($db, new JournalSchema($db));
    }

    /**
     * A ticker as each request or process gets one: nothing in memory, everything from the database.
     *
     * @param list<ProvidesSchedule> $more    providers besides the test's own
     * @param ?ScheduleHistory       $history the Journal's reader, when the test breaks it
     * @param ?RuntimeLock           $locks   the lock, when the test breaks it
     */
    private function ticker(array $more = [], ?ScheduleHistory $history = null, ?RuntimeLock $locks = null): QueueScheduleTicker
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
                    $recurring[] = RecurringMessageTimezoned::every($every, new QueueTestTask($id));
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
            $history ?? $this->history,
            $recorder,
            $locks ?? $this->locks,
            $this->clock,
            new HibernationService($this->params),
            $journal,
            new ScheduleGrid(),
        );
    }

    /** The digests' provider: every 30 minutes, or a throw while $digestsBroken. */
    private function digests(): ProvidesSchedule
    {
        return new class(function (): array {
            if ($this->digestsBroken) {
                throw new \RuntimeException('no triples table');
            }

            return [RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('digest'))];
        }) implements ProvidesSchedule {
            /** @param \Closure(): list<RecurringMessageTimezoned> $messages */
            public function __construct(private \Closure $messages)
            {
            }

            public function recurringMessages(): array
            {
                return ($this->messages)();
            }
        };
    }

    /**
     * What `queue-task-overdue` says, in worker mode, of the tasks a fresh ticker lists.
     *
     * @param list<ProvidesSchedule> $more
     */
    private function overdue(array $more = []): ?string
    {
        $settings = new QueueSettings(new ParameterBag(['queue_consumer' => 'worker']), static fn (): array => []);
        $hibernation = new HibernationService($this->params);
        $checks = new QueueHealthChecks(
            $settings,
            $this->statistics,
            $this->schema,
            $hibernation,
            $this->ticker($more),
            new QueueFallback($settings, $this->statistics, $this->ticker($more), $hibernation, $this->locks, $this->clock),
            $this->clock,
        );
        foreach ($checks->healthChecks() as $check) {
            if ($check->id() === 'queue-task-overdue') {
                return $check->failure();
            }
        }

        return $this->fail('no queue-task-overdue check');
    }

    private function recorder(): QueueRecorder
    {
        return new QueueRecorder($this->db, new JournalSchema($this->db), $this->clock);
    }

    /**
     * That task as a fresh ticker lists it.
     *
     * @param list<ProvidesSchedule> $more providers besides the test's own
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}
     */
    private function task(string $taskId, array $more = []): array
    {
        return $this->ticker($more)->task($taskId) ?? $this->fail("no task {$taskId}");
    }

    /** Runs that and expects a \Throwable whose message contains that text. */
    private function assertThrows(\Closure $run, string $message): void
    {
        try {
            $run();
        } catch (\Throwable $failed) {
            $this->assertStringContainsString($message, $failed->getMessage());

            return;
        }
        $this->fail("nothing was thrown, expected '{$message}'");
    }

    /** How many Journal rows there are, of that action (and that target) or of any. */
    private function rows(?string $action = null, ?string $target = null): int
    {
        $table = (new JournalSchema($this->db))->table();
        $where = array_filter(['action = ?' => $action, 'target = ?' => $target], static fn (?string $value): bool => $value !== null);

        return (int)$this->db->scalar("SELECT COUNT(*) FROM {$table}" . ($where === [] ? '' : ' WHERE ' . implode(' AND ', array_keys($where))), 0, array_values($where));
    }

    /** How many times the Journal says that action happened: its rows repeat while they are the same. */
    private function repeats(string $action): int
    {
        return (int)$this->db->scalar('SELECT COALESCE(SUM(' . $this->db->quoteIdentifier('repeat') . '), 0) FROM ' . (new JournalSchema($this->db))->table() . ' WHERE action = ?', 0, [$action]);
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

<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use YesWiki\Admin\Message\PurgeRevisions;
use YesWiki\Admin\Message\PurgeRevisionsHandler;
use YesWiki\Admin\Message\RunMaintenanceHooks;
use YesWiki\Admin\Message\RunMaintenanceHooksHandler;
use YesWiki\Admin\Scheduler\AdminSchedule;
use YesWiki\Identity\Message\PurgeActivationKeys;
use YesWiki\Identity\Message\PurgeActivationKeysHandler;
use YesWiki\Identity\Message\PurgeBotGuard;
use YesWiki\Identity\Message\PurgeBotGuardHandler;
use YesWiki\Identity\Message\PurgeRecoveryKeys;
use YesWiki\Identity\Message\PurgeRecoveryKeysHandler;
use YesWiki\Identity\Scheduler\IdentitySchedule;
use YesWiki\Kernel\Message\PruneJournal;
use YesWiki\Kernel\Message\PruneJournalHandler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\ScheduleGenerator;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Contact\InMemoryCheckpoint;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Contact/SendDigestTest.php';

/**
 * Each step of the old housekeeping sweep is a scheduled task of its own (messenger / scheduler, spec section 8).
 *
 * The schedules are the wiki's own services, ticked through the real ScheduleGenerator; the
 * queue is the kit's; the revisions live in a throw-away SQLite file.
 */
#[CoversClass(KernelSchedule::class)]
#[CoversClass(IdentitySchedule::class)]
#[CoversClass(AdminSchedule::class)]
#[CoversClass(PurgeRevisionsHandler::class)]
class MaintenanceTasksTest extends YesWikiTestCase
{
    private const NOW = '2026-10-23 12:00:00';

    private const STEPS = ['revisions', 'journal', 'recovery-keys', 'activation-keys', 'bot-guard', 'extension-hooks'];

    private ?QueueTestKit $kit = null;
    private ?string $file = null;

    protected function tearDown(): void
    {
        $this->kit?->dispose();
        if ($this->file !== null) {
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($this->file . $suffix);
            }
        }
    }

    public function testEveryStepIsItsOwnTask(): void
    {
        $plan = $this->plan($this->housekeeping());

        $this->assertEqualsCanonicalizing(self::STEPS, array_keys($plan));
        foreach ($plan as $taskId => $task) {
            $this->assertSame(1800, $task['every'], "{$taskId} runs every 30 minutes, as the sweep did");
            $this->assertStringNotContainsString('MAINTENANCE_TASK_', $task['message']->label(), "{$taskId} has a translated label");
        }
        $this->assertInstanceOf(PurgeRevisions::class, $plan['revisions']['message']);
        $this->assertInstanceOf(PruneJournal::class, $plan['journal']['message']);
        $this->assertInstanceOf(PurgeRecoveryKeys::class, $plan['recovery-keys']['message']);
        $this->assertInstanceOf(PurgeActivationKeys::class, $plan['activation-keys']['message']);
        $this->assertInstanceOf(PurgeBotGuard::class, $plan['bot-guard']['message']);
        $this->assertInstanceOf(RunMaintenanceHooks::class, $plan['extension-hooks']['message']);
    }

    /** A wiki that was not consumed for a day runs each step once when it is, not forty-eight times. */
    public function testAStepThatCameDueWhileNobodyConsumedRunsOnce(): void
    {
        $clock = new MockClock(self::NOW, 'UTC');
        $tick = $this->ticker($this->housekeeping(), $clock);

        $this->assertSame([], $tick(), 'the schedule starts now');
        $clock->sleep(29 * 60);
        $this->assertSame([], $tick(), 'nothing is due inside the interval');
        $clock->sleep(2 * 60);
        $this->assertEqualsCanonicalizing(self::STEPS, $tick(), 'every step is due at the first interval');
        $this->assertSame([], $tick(), 'and not twice');
        $clock->sleep(24 * 3600);
        $this->assertEqualsCanonicalizing(self::STEPS, $tick(), 'a day later: the missed runs are one');
    }

    public function testTheHandlersAndTheSchedulesAreDiscoveredOnTheRealContainer(): void
    {
        $services = $this->getWiki()->services;
        $handled = $services->get(QueueHandlerLocator::class)->handledTypes();

        foreach ([PurgeRevisions::class, PruneJournal::class, PurgeRecoveryKeys::class, PurgeActivationKeys::class, PurgeBotGuard::class, RunMaintenanceHooks::class] as $type) {
            $this->assertContains($type, $handled);
        }
        foreach ([PurgeRevisionsHandler::class, PruneJournalHandler::class, PurgeRecoveryKeysHandler::class, PurgeActivationKeysHandler::class, PurgeBotGuardHandler::class, RunMaintenanceHooksHandler::class] as $handler) {
            $this->assertInstanceOf($handler, $services->get($handler));
        }
        $declared = $services->get(QueueScheduleTicker::class)->declaredTaskIds();
        foreach (self::STEPS as $step) {
            $this->assertContains($step, $declared, "the ticker finds the schedule of {$step} by its tag");
        }
    }

    /** One step failing is one task failing: the five others are handled, and the failed one is tried again later like any message. */
    public function testAFailingStepDoesNotStopTheOthers(): void
    {
        $ran = [];
        $record = static function (ScheduledTask $task) use (&$ran): void {
            $ran[] = $task->taskId();
        };
        $this->kit = new QueueTestKit([
            PurgeRevisions::class => $record,
            PruneJournal::class => static fn () => throw new \RuntimeException('the journal table is locked'),
            PurgeRecoveryKeys::class => $record,
            PurgeActivationKeys::class => $record,
            PurgeBotGuard::class => $record,
            RunMaintenanceHooks::class => $record,
        ]);

        foreach (['revisions' => new PurgeRevisions(), 'journal' => new PruneJournal(), 'recovery-keys' => new PurgeRecoveryKeys(), 'activation-keys' => new PurgeActivationKeys(), 'bot-guard' => new PurgeBotGuard(), 'extension-hooks' => new RunMaintenanceHooks()] as $message) {
            $queued = $this->kit->bus->dispatch($message)->last(TransportMessageIdStamp::class);
            $this->assertNotNull($queued);
            $this->kit->consumer->handleById((int)$queued->getId(), QueueConsumerMode::Command);
        }

        $this->assertSame(['revisions', 'recovery-keys', 'activation-keys', 'bot-guard', 'extension-hooks'], $ran);
        $this->assertSame(1, $this->kit->statistics->pendingCount(), 'the journal step waits for its retry, nothing else does');
        $statuses = array_column($this->kit->statistics->tasks(), 'lastStatus', 'taskId');
        $this->assertSame(QueueRecorder::RETRIED, $statuses['journal']);
        foreach (['revisions', 'recovery-keys', 'activation-keys', 'bot-guard', 'extension-hooks'] as $step) {
            $this->assertSame(QueueRecorder::HANDLED, $statuses[$step], $step);
        }
    }

    public function testRevisionsAreNotPurgedWhileHibernated(): void
    {
        $db = $this->wikiDatabase();
        $this->revisions($db, 'OldPage', ['2020-01-01 10:00:00', '2020-02-01 10:00:00', '2020-03-01 10:00:00']);

        $this->purge($db, days: 30, hibernated: true);
        $this->assertSame(3, $this->countRevisions($db), 'a hibernated wiki (an archive, an update) is read-only');

        $this->purge($db, days: 30, hibernated: false);
        $this->assertSame(2, $this->countRevisions($db), 'awake: the oldest of the revisions from before the window goes, the newest of them and the current version stay');
        $this->assertSame(['2020-02-01 10:00:00', '2020-03-01 10:00:00'], array_column($db->loadAll('SELECT time FROM ' . $db->prefixTable('pages') . ' ORDER BY time'), 'time'));
    }

    public function testRevisionsAreKeptWhenTheWikiDoesNotPurge(): void
    {
        $db = $this->wikiDatabase();
        $this->revisions($db, 'OldPage', ['2020-01-01 10:00:00', '2020-02-01 10:00:00']);

        $this->purge($db, days: 0, hibernated: false);

        $this->assertSame(2, $this->countRevisions($db));
    }

    /** The window: a revision inside it, and the newest outside it, are the two that stay. */
    public function testRevisionsInsideTheWindowAndTheNewestOutsideStay(): void
    {
        $db = $this->wikiDatabase();
        $recent = (new \DateTimeImmutable('-2 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->revisions($db, 'OldPage', ['2020-01-01 10:00:00', '2020-02-01 10:00:00', $recent]);

        $this->purge($db, days: 30, hibernated: false);

        $this->assertSame(2, $this->countRevisions($db));
        $this->assertSame(
            ['2020-02-01 10:00:00', $recent],
            array_column($db->loadAll('SELECT time FROM ' . $db->prefixTable('pages') . ' ORDER BY time'), 'time')
        );
    }

    /**
     * The three schedules together, as the queue builds them.
     */
    private function housekeeping(): ProvidesSchedule
    {
        $services = $this->getWiki()->services;
        $schedules = [$services->get(KernelSchedule::class), $services->get(IdentitySchedule::class), $services->get(AdminSchedule::class)];

        return new class($schedules) implements ProvidesSchedule {
            /** @param list<ProvidesSchedule> $schedules */
            public function __construct(private readonly array $schedules)
            {
            }

            public function recurringMessages(): array
            {
                return array_merge(...array_map(static fn (ProvidesSchedule $schedule): array => $schedule->recurringMessages(), $this->schedules));
            }
        };
    }

    /**
     * One tick of the Scheduler over a schedule rebuilt each time, as the queue does: the ids of the tasks that come due.
     *
     * @return \Closure(): list<string>
     */
    private function ticker(ProvidesSchedule $provider, MockClock $clock): \Closure
    {
        $checkpoint = new InMemoryCheckpoint();

        return static function () use ($provider, $clock, $checkpoint): array {
            $schedule = (new Schedule())->processOnlyLastMissedRun(true);
            foreach ($provider->recurringMessages() as $recurring) {
                $schedule->add($recurring);
            }
            $ids = [];
            foreach ((new ScheduleGenerator($schedule, 'test', $clock, $checkpoint))->getMessages() as $message) {
                if ($message instanceof ScheduledTask && $message->taskId() !== 'queue-prune') {
                    $ids[] = $message->taskId();
                }
            }

            return $ids;
        };
    }

    /**
     * What a schedule declares, per task id: its message and the gap between its runs.
     *
     * @return array<string, array{message: ScheduledTask, every: int}>
     */
    private function plan(ProvidesSchedule $schedule): array
    {
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
        $plan = [];
        foreach ($schedule->recurringMessages() as $recurring) {
            $trigger = $recurring->getTrigger();
            $first = $trigger->getNextRunDate($now);
            $this->assertNotNull($first);
            $second = $trigger->getNextRunDate($first);
            $this->assertNotNull($second);
            foreach ($recurring->getMessages(new MessageContext('test', $recurring->getId(), $trigger, $now)) as $message) {
                $this->assertInstanceOf(ScheduledTask::class, $message);
                if ($message->taskId() === 'queue-prune') {
                    continue;
                }
                $plan[$message->taskId()] = ['message' => $message, 'every' => $second->getTimestamp() - $first->getTimestamp()];
            }
        }

        return $plan;
    }

    /** A throw-away database with the one table the purge reads. */
    private function wikiDatabase(): DbService
    {
        $this->file = sys_get_temp_dir() . '/yw-revisions-' . bin2hex(random_bytes(6)) . '.db';
        $db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywr_',
            'debug' => false,
        ]));
        $db->query('CREATE TABLE ' . $db->prefixTable('pages') . " (id INTEGER PRIMARY KEY AUTOINCREMENT, tag TEXT NOT NULL, time TEXT NOT NULL, latest TEXT NOT NULL DEFAULT 'N', body TEXT)");

        return $db;
    }

    /**
     * Revisions of one page, the newest of them being its current version.
     *
     * @param list<string> $times oldest first
     */
    private function revisions(DbService $db, string $tag, array $times): void
    {
        foreach ($times as $position => $time) {
            $latest = $position === count($times) - 1 ? 'Y' : 'N';
            $db->query('INSERT INTO ' . $db->prefixTable('pages') . ' (tag, time, latest, body) VALUES (?, ?, ?, ?)', [$tag, $time, $latest, 'revision ' . $position]);
        }
    }

    private function countRevisions(DbService $db): int
    {
        return (int)$db->scalar('SELECT COUNT(*) FROM ' . $db->prefixTable('pages'));
    }

    private function purge(DbService $db, int $days, bool $hibernated): void
    {
        $config = new RuntimeConfig();
        $values = ['pages_purge_time' => $days];
        $config->bind($values);
        $hibernation = new class(new ParameterBag(), $hibernated) extends HibernationService {
            public function __construct(ParameterBag $params, private readonly bool $asleep)
            {
                parent::__construct($params);
            }

            public function isWikiHibernated(): bool
            {
                throw new \LogicException('the status the container was built with is not the live one');
            }

            public function isWikiHibernatedNow(): bool
            {
                return $this->asleep;
            }
        };

        (new PurgeRevisionsHandler($config, $db, $hibernation))(new PurgeRevisions());
    }
}

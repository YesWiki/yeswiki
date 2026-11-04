<?php

namespace YesWiki\Test\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use YesWiki\Core\ApiResponse;
use YesWiki\Files\Service\Storage;
use YesWiki\Import\Api\ImportApiController;
use YesWiki\Import\Message\SyncDataSource;
use YesWiki\Import\Message\SyncDataSourceHandler;
use YesWiki\Import\Scheduler\ImportSchedule;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\SyncScheduler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Contact/SendDigestTest.php';

/**
 * The automatic import of data sources is a scheduled task, one per source flagged `syncOnMaintenance` (messenger / scheduler, spec 9.4).
 *
 * The state files live in a throw-away directory, the importer is a stub that succeeds or fails
 * as each test needs, and the clock is the test's: it starts at the real time, since the
 * `.log` that `recordRun()` writes is stamped by the file system.
 */
#[CoversClass(SyncDataSource::class)]
#[CoversClass(SyncDataSourceHandler::class)]
#[CoversClass(ImportSchedule::class)]
#[CoversClass(SyncScheduler::class)]
class SyncDataSourceTest extends YesWikiTestCase
{
    private const SOURCES = [
        'every_time' => ['importer' => 'Rss', 'syncOnMaintenance' => true],
        'once_a_day' => ['importer' => 'Rss', 'syncOnMaintenance' => true, 'syncIntervalInMin' => 1440],
        'on_demand' => ['importer' => 'Rss'],
    ];

    private string $root;
    private Storage $storage;
    private MockClock $clock;
    private ?QueueTestKit $kit = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/yeswiki-sync-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->storage = Storage::rootedAt($this->root);
        $this->clock = new MockClock('now', 'UTC');
    }

    protected function tearDown(): void
    {
        $this->kit?->dispose();
        $this->storage->deleteDirectory('cache/importer');
        @rmdir($this->root . '/cache');
        @rmdir($this->root);
    }

    public function testOnlySourcesFlaggedSyncOnMaintenanceAreScheduled(): void
    {
        $plan = $this->plan($this->schedule(self::SOURCES));

        $this->assertSame(['sync-every_time', 'sync-once_a_day'], array_keys($plan));
        $this->assertEquals(new SyncDataSource('every_time'), $plan['sync-every_time']['message']);
        $this->assertEquals(new SyncDataSource('once_a_day'), $plan['sync-once_a_day']['message']);
        $this->assertSame([], $this->schedule([])->recurringMessages());
    }

    /** The housekeeping pass ran every half hour and a source with no interval of its own went at each pass. */
    public function testTheIntervalIsTheSourcesOwnButNeverUnderHalfAnHour(): void
    {
        $plan = $this->plan($this->schedule(self::SOURCES + [
            'quick' => ['importer' => 'Rss', 'syncOnMaintenance' => true, 'syncIntervalInMin' => 5],
            'weekly' => ['importer' => 'Rss', 'syncOnMaintenance' => true, 'syncIntervalInMin' => '10080'],
        ]));

        $this->assertSame(1800, $plan['sync-every_time']['every']);
        $this->assertSame(86400, $plan['sync-once_a_day']['every']);
        $this->assertSame(1800, $plan['sync-quick']['every'], 'five minutes is under what the queue ticks for');
        $this->assertSame(604800, $plan['sync-weekly']['every']);
    }

    /** No state of its own: a source syncs on the slots of its interval, counted from midnight in the wiki's timezone, whatever its log says. */
    public function testASourceSyncsOnTheSlotsOfItsInterval(): void
    {
        $this->clock->modify('2026-11-03 10:00:00');
        $this->storage->storeDerived('cache/importer/once_a_day.log', 'done');
        $this->touchLog('once_a_day', '-23 hours');
        $tick = $this->ticker($this->schedule(self::SOURCES));

        $this->assertSame([], $tick(), 'the tick that first sees them runs nothing');
        $this->clock->sleep(29 * 60);
        $this->assertSame([], $tick(), 'neither source before its first slot');
        $this->clock->sleep(2 * 60);
        $this->assertSame(['sync-every_time'], $tick(), 'the 10:30 slot, a minute late');
        $this->clock->sleep(28 * 60);
        $this->assertSame([], $tick());
        $this->clock->sleep(60);
        $this->assertSame(['sync-every_time'], $tick(), 'on the hour, not half an hour after the late run');

        $midnight = new \DateTimeImmutable('2026-11-04 00:00:00', new \DateTimeZone(date_default_timezone_get()));
        $this->clock->sleep($midnight->getTimestamp() - $this->clock->now()->getTimestamp() - 1);
        $this->assertSame(['sync-every_time'], $tick(), 'synced 23 hours before it was first seen, the once-a-day source still waits for midnight');
        $this->clock->sleep(1);
        $this->assertSame(['sync-every_time', 'sync-once_a_day'], $tick(), 'at midnight in the wiki\'s timezone');
    }

    /** As for the digests: a sync run by hand (the button, `importer:sync`, `{{sync}}`, the API) moves nothing. */
    public function testASyncByHandDoesNotMoveTheScheduledOne(): void
    {
        $tick = $this->ticker($this->schedule(['every_time' => self::SOURCES['every_time']]));
        $tick();
        $this->clock->sleep(20 * 60);
        $this->scheduler()->recordRun('every_time', 'synced by hand');
        $this->touchLog('every_time', '+0 seconds');

        $this->clock->sleep(10 * 60);

        $this->assertSame(['sync-every_time'], $tick(), 'half an hour after the start, ten minutes after the sync by hand');
    }

    /** A source that always fails costs one attempt per interval, not one per tick: the schedule moves on whatever the handler did. */
    public function testAFailingSourceComesBackOneIntervalLaterAndNotAtEveryTick(): void
    {
        $this->clock->modify('2026-11-03 10:00:00');
        $handler = $this->handler($this->importer(static fn (): string => throw new \RuntimeException('feed unreachable')));
        $tick = $this->ticker($this->schedule(['every_time' => self::SOURCES['every_time']]));
        $tick();
        $this->clock->sleep(30 * 60);
        $this->assertSame(['sync-every_time'], $tick());

        try {
            $handler(new SyncDataSource('every_time'));
            $this->fail('the failure is the queue\'s to show');
        } catch (\RuntimeException) {
        }

        $this->clock->sleep(60);
        $this->assertSame([], $tick(), 'it failed a minute ago: not again at the next tick');
        $this->clock->sleep(28 * 60);
        $this->assertSame([], $tick(), 'nor before the interval');
        $this->clock->sleep(60);
        $this->assertSame(['sync-every_time'], $tick(), 'on the next slot, an interval after the last run');
    }

    /** Spec section 8: a wiki where nothing ticked for hours syncs once at the next tick, not once per interval missed. */
    public function testMissedIntervalsMakeOneSync(): void
    {
        $tick = $this->ticker($this->schedule(['every_time' => self::SOURCES['every_time']]));
        $tick();
        $this->clock->sleep(3 * 3600 + 60);

        $this->assertSame(['sync-every_time'], $tick());
        $this->assertSame([], $tick(), 'once');
    }

    /**
     * A source just set to sync itself does not wait a whole interval for its first sync: saving it queues one.
     *
     * Not at once, but a minute later: the pass at the end of the request that saved it, and a
     * worker that has not yet seen the configuration change, would read the source as it was.
     */
    public function testASourceJustSetToSyncItselfIsQueuedForAMinuteLater(): void
    {
        $clock = new MockClock('2026-11-03 10:00:00', 'UTC');
        $this->kit = new QueueTestKit([SyncDataSource::class => static fn (): null => null], $clock);
        $schedule = $this->schedule(self::SOURCES, $this->kit->bus);

        $schedule->sourceSaved('on_demand', self::SOURCES['on_demand'], ['importer' => 'Rss', 'syncOnMaintenance' => true]);
        $schedule->sourceSaved('new_feed', null, ['importer' => 'Rss', 'syncOnMaintenance' => true, 'syncIntervalInMin' => 1440]);

        $this->assertSame(2, $this->kit->statistics->waitingOfType(SyncDataSource::class));
        $availableAt = $this->kit->statistics->oldestPendingAt();
        $this->assertNotNull($availableAt);
        $this->assertGreaterThan($clock->now(), $availableAt, 'not for a pass that still reads the configuration as it was');
        $this->assertLessThanOrEqual($clock->now()->modify('+1 minute'), $availableAt, 'nor an interval later');
    }

    public function testASourceAlreadySyncingItselfOrNotSetToIsNotQueued(): void
    {
        $this->kit = new QueueTestKit();
        $schedule = $this->schedule(self::SOURCES, $this->kit->bus);

        $schedule->sourceSaved('once_a_day', self::SOURCES['once_a_day'], self::SOURCES['once_a_day'] + ['url' => 'https://example.org/feed']);
        $schedule->sourceSaved('every_time', self::SOURCES['every_time'], ['importer' => 'Rss', 'syncOnMaintenance' => false]);
        $schedule->sourceSaved('on_demand', self::SOURCES['on_demand'], self::SOURCES['on_demand']);
        $schedule->sourceSaved(str_repeat('x', 60), null, ['importer' => 'Rss', 'syncOnMaintenance' => true]);

        $this->assertSame(0, $this->kit->statistics->waitingOfType(SyncDataSource::class), 'the last one is not scheduled either: its name is too long for a task');
    }

    /** The queue keys a task by a 64-character id: a longer one would fail the whole tick. */
    public function testASourceWhoseTaskIdWouldNotFitTheQueueIsSkippedAndJournaled(): void
    {
        $journal = new RecordingJournal();
        $long = str_repeat('x', 60);
        $schedule = new ImportSchedule(
            new ParameterBag(['dataSources' => ['every_time' => self::SOURCES['every_time'], $long => ['importer' => 'Rss', 'syncOnMaintenance' => true]]]),
            $journal,
            new MessageBus(),
        );

        $this->assertSame(['sync-every_time'], array_keys($this->plan($schedule)));
        $this->assertCount(1, $journal->records);
        $this->assertSame('import.schedule_failed', $journal->records[0][2]['action']);
        $this->assertStringContainsString($long, $journal->records[0][1]);
    }

    public function testTheTaskIsNamedAfterItsSourceInTheTranslationsOfTheWiki(): void
    {
        $label = (new SyncDataSource('feed_1'))->label();

        $this->assertStringContainsString('feed_1', $label);
        $this->assertStringNotContainsString('IMPORTER_TASK_SYNC', $label, 'it has a translation');
        $this->assertSame('sync-feed_1', (new SyncDataSource('feed_1'))->taskId());
        $this->assertInstanceOf(ScheduledTask::class, new SyncDataSource('feed_1'));
    }

    /** A sync is idempotent: the default retry (three, with a growing delay) applies. */
    public function testASyncIsRetriedByTheDefaultPolicy(): void
    {
        $message = new SyncDataSource('every_time');

        $this->assertNotContains(RetryPolicy::class, class_implements($message));
        $this->assertTrue((new QueueRetryStrategy())->isRetryable(Envelope::wrap($message)->with(new RedeliveryStamp(0))));
    }

    public function testASuccessfulSyncIsRecordedForTheImportersPage(): void
    {
        $handler = $this->handler($this->importer(static function (): string {
            echo "Entrée \"Fête\" mise à jour.\n";

            return 'Source "every_time" synchronisée avec succès.';
        }));

        $handler(new SyncDataSource('every_time'));

        $last = $this->scheduler()->getLastSync('every_time');
        $this->assertNotNull($last);
        $this->assertStringContainsString('Fête', $last['output'], 'what the importer printed');
        $this->assertStringContainsString('synchronisée avec succès', $last['output']);
        $this->assertEqualsWithDelta(time(), $last['time'], 5);
    }

    public function testAFailedSyncIsRecordedForTheImportersPageAndRethrown(): void
    {
        $handler = $this->handler($this->importer(static function (): string {
            echo "Lecture du flux\n";

            throw new \RuntimeException('feed unreachable (en 0.01s)');
        }));
        $level = ob_get_level();

        try {
            $handler(new SyncDataSource('every_time'));
            $this->fail('a sync that failed must say so to the queue');
        } catch (\RuntimeException $failed) {
            $this->assertSame('feed unreachable (en 0.01s)', $failed->getMessage());
        }

        $this->assertSame($level, ob_get_level(), 'the capture of what the importer prints is closed');
        $last = $this->scheduler()->getLastSync('every_time');
        $this->assertNotNull($last, 'the importers page shows the failure');
        $this->assertStringContainsString('Lecture du flux', $last['output']);
        $this->assertStringContainsString('feed unreachable', $last['output']);
        $this->assertNull($this->scheduler()->getLastSync('once_a_day'), 'one log per source');
    }

    public function testASourceRemovedFromTheConfigIsNotRetried(): void
    {
        $handler = $this->handler($this->importer(static fn (): string => 'never'));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('gone');

        $handler(new SyncDataSource('gone'));
    }

    public function testAFailedSyncIsRetriedThenSucceeds(): void
    {
        $attempts = 0;
        $handler = $this->handler($this->importer(static function () use (&$attempts): string {
            if (++$attempts === 1) {
                throw new \RuntimeException('feed unreachable');
            }

            return 'ok';
        }));
        $this->kit = new QueueTestKit([SyncDataSource::class => static fn (SyncDataSource $message) => $handler($message)]);

        $queued = $this->kit->bus->dispatch(new SyncDataSource('every_time'))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($queued);
        $this->kit->consumer->handleById((int)$queued->getId(), QueueConsumerMode::Api);

        $this->assertSame(1, $attempts);
        $this->assertSame(1, $this->kit->statistics->pendingCount(), 'waits for its retry');
        $this->assertSame(0, $this->kit->statistics->failedCount());
    }

    public function testTheApiRouteStillAnswersWithTheErrorText(): void
    {
        $failing = $this->importer(static function (string $id): string {
            if ($id === 'broken') {
                throw new \RuntimeException('feed unreachable (en 0.01s)');
            }

            return "Source \"{$id}\" synchronisée avec succès. (en 0.01s)";
        });
        $sources = ['good' => ['importer' => 'Rss'], 'broken' => ['importer' => 'Rss']];

        $response = $this->callApi($failing, $sources, 'a-secret', 'a-secret');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['good' => 'Source "good" synchronisée avec succès. (en 0.01s)', 'broken' => 'feed unreachable (en 0.01s)'],
            json_decode((string)$response->getContent(), true),
            'a source that failed does not stop the others, and the text is what the route always answered'
        );
        $this->assertStringContainsString('feed unreachable', $this->scheduler()->getLastSync('broken')['output'] ?? '');
        $this->assertStringContainsString('synchronisée', $this->scheduler()->getLastSync('good')['output'] ?? '');
    }

    public function testTheApiRouteStillRefusesWithoutTheSecret(): void
    {
        $this->assertSame(401, $this->callApi($this->importer(static fn (): string => 'never'), self::SOURCES, 'a-secret', 'wrong')->getStatusCode());
    }

    /** The source is told apart from "the importer is unknown": that one is a message too, and the route shows it. */
    public function testAnUnknownImporterThrowsWithTheMessageItAlwaysHad(): void
    {
        $manager = $this->getWiki()->services->get(ImporterManager::class);

        try {
            $manager->syncSource('nothing', ['importer' => 'DoesNotExist']);
            $this->fail('an unknown importer is a failure');
        } catch (\RuntimeException $failed) {
            $this->assertSame('Importer DoesNotExist not found', $failed->getMessage());
        }
    }

    public function testNothingListensToMaintenanceAfterForSync(): void
    {
        $services = $this->getWiki()->services;

        $this->assertFileDoesNotExist('src/Import/Service/MaintenanceSyncSubscriber.php', 'the subscriber of the housekeeping pass is gone');
        $this->assertFalse($services->has('YesWiki\\Import\\Service\\MaintenanceSyncSubscriber'));
        foreach ($services->get(EventDispatcher::class)->getListeners('maintenance.after') as $listener) {
            $owner = is_array($listener) ? $listener[0] : $listener;
            if (is_object($owner)) {
                $this->assertStringStartsNotWith('YesWiki\\Import\\', $owner::class);
            }
        }
    }

    /** What makes the schedule and the message count: both are found on the container the wiki really runs on (delete cache/container/* after adding a service class). */
    public function testTheHandlerAndTheScheduleAreDiscoveredOnTheRealContainer(): void
    {
        $services = $this->getWiki()->services;

        self::assertHandled(SyncDataSource::class, $services->get(QueueHandlerLocator::class));
        $this->assertInstanceOf(SyncDataSourceHandler::class, $services->get(SyncDataSourceHandler::class));
        $this->assertInstanceOf(ProvidesSchedule::class, $services->get(ImportSchedule::class));
        $this->assertContains('queue-prune', $services->get(QueueScheduleTicker::class)->declaredTaskIds(), 'fixture: the ticker builds its schedule from every provider');
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     */
    private function callApi(ImporterManager $importer, array $sources, string $secret, string $sent): ApiResponse
    {
        $request = Request::create('/api/import/sync', 'GET');
        $request->headers->set('secret', $sent);
        $currentRequest = new CurrentRequest();
        $currentRequest->replace($request);

        $controller = new ImportApiController();
        $controller->setServices(new ServiceLocator([
            \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class => static fn () => new ParameterBag(['sync_secret' => $secret, 'dataSources' => $sources]),
            CurrentRequest::class => static fn (): CurrentRequest => $currentRequest,
            ImporterManager::class => static fn (): ImporterManager => $importer,
        ]));

        return $controller->sync();
    }

    /**
     * An importer manager whose syncSource() is the given closure: it returns the text of a success, or throws as the real one does.
     *
     * @param \Closure(string): string $sync
     */
    private function importer(\Closure $sync): ImporterManager
    {
        return new class($sync, $this->scheduler()) extends ImporterManager {
            /** @param \Closure(string): string $sync */
            public function __construct(private readonly \Closure $sync, SyncScheduler $scheduler)
            {
                // Where syncByHand() keeps the run, as the real one finds it in the container.
                $this->services = new ServiceLocator([SyncScheduler::class => static fn (): SyncScheduler => $scheduler]);
            }

            public function syncSource(string $source, array $sourceOptions): string
            {
                return ($this->sync)($source);
            }
        };
    }

    private function handler(ImporterManager $importer): SyncDataSourceHandler
    {
        return new SyncDataSourceHandler(new ParameterBag(['dataSources' => self::SOURCES]), $importer, $this->scheduler());
    }

    private function scheduler(): SyncScheduler
    {
        return new SyncScheduler(new ParameterBag(), $this->storage);
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     */
    private function schedule(array $sources, ?MessageBusInterface $bus = null): ImportSchedule
    {
        return new ImportSchedule(
            new ParameterBag(['dataSources' => $sources]),
            new Journal(new ServiceLocator([])),
            $bus ?? new MessageBus(),
        );
    }

    /** Pretend the source's last run happened at that time, relative to the test's clock. */
    private function touchLog(string $source, string $relative): void
    {
        touch($this->root . '/cache/importer/' . $source . '.log', $this->clock->now()->modify($relative)->getTimestamp());
    }

    /**
     * One tick of the kit's Scheduler over that provider, on the test's clock: the ids of the tasks that come due.
     *
     * @return \Closure(): list<string>
     */
    private function ticker(ProvidesSchedule $provider): \Closure
    {
        $this->kit = new QueueTestKit(clock: $this->clock, schedule: $provider);

        return $this->kit->tick(...);
    }

    /**
     * What a schedule declares: per task id, its message, the first run after the test's now and the gap between runs.
     *
     * @return array<string, array{message: SyncDataSource, first: \DateTimeImmutable, every: int}>
     */
    private function plan(ImportSchedule $schedule): array
    {
        $now = $this->clock->now();
        $plan = [];
        foreach ($schedule->recurringMessages() as $recurring) {
            $trigger = $recurring->getTrigger();
            $first = $trigger->getNextRunDate($now);
            $this->assertNotNull($first);
            $second = $trigger->getNextRunDate($first);
            $this->assertNotNull($second);
            foreach ($recurring->getMessages(new MessageContext('test', $recurring->getId(), $trigger, $now)) as $message) {
                $this->assertInstanceOf(SyncDataSource::class, $message);
                $plan[$message->taskId()] = [
                    'message' => $message,
                    'first' => $first,
                    'every' => $second->getTimestamp() - $first->getTimestamp(),
                ];
            }
        }

        return $plan;
    }
}

/** A journal that keeps what it is told instead of writing it anywhere. */
final class RecordingJournal extends Journal
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $records = [];

    public function __construct()
    {
        parent::__construct(new ServiceLocator([]));
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [(string)$level, (string)$message, $context];
    }
}

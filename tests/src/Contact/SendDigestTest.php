<?php

namespace YesWiki\Test\Contact;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Generator\CheckpointInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use YesWiki\Contact\Command\ContactDigestCommand;
use YesWiki\Contact\Message\SendDigest;
use YesWiki\Contact\Message\SendDigestHandler;
use YesWiki\Contact\Scheduler\ContactSchedule;
use YesWiki\Contact\Service\DigestMarkers;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\ScheduleGenerator;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;
use YesWiki\Test\Kernel\QueueTestTask;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/**
 * The mailing-list digests are scheduled tasks, one per period (messenger / scheduler, spec 9.3).
 *
 * The markers live in a throw-away directory, the queue is the kit's, and the subscriptions are
 * the wiki's own service with its sending replaced by what each test needs.
 */
#[CoversClass(SendDigest::class)]
#[CoversClass(SendDigestHandler::class)]
#[CoversClass(ContactSchedule::class)]
#[CoversClass(DigestMarkers::class)]
class SendDigestTest extends YesWikiTestCase
{
    private const NOW = '2026-10-23 12:00:00';

    private string $root;
    private DigestMarkers $markers;
    private ?QueueTestKit $kit = null;
    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/yeswiki-digests-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->markers = new DigestMarkers(Storage::rootedAt($this->root));
        $this->clock = new MockClock(self::NOW, 'UTC');
    }

    protected function tearDown(): void
    {
        $this->kit?->dispose();
        Storage::rootedAt($this->root)->deleteDirectory(DigestMarkers::DIRECTORY);
        @rmdir($this->root . '/private');
        @rmdir($this->root);
    }

    public function testEachEnabledPeriodIsScheduled(): void
    {
        $plan = $this->plan($this->schedule());

        $this->assertSame(['digest-day', 'digest-week', 'digest-month'], array_keys($plan));
        $this->assertSame(
            [82800, 604800, 2592000],
            array_column($plan, 'every'),
            'the floors the scheduler of the housekeeping pass used: 23 h, 7 d, 30 d'
        );
        foreach (MailSubscriptions::PERIODS as $period) {
            $this->assertEquals(new SendDigest($period), $plan['digest-' . $period]['message']);
        }
    }

    public function testDisabledDigestsAreNotScheduled(): void
    {
        foreach ([true, 1, '1', 'true'] as $disabled) {
            $this->assertSame([], $this->schedule(['contact_disable_periodic_digest' => $disabled])->recurringMessages(), var_export($disabled, true));
        }

        foreach ([false, 0, '0', 'false'] as $enabled) {
            $this->assertCount(3, $this->schedule(['contact_disable_periodic_digest' => $enabled])->recurringMessages(), var_export($enabled, true));
        }
    }

    public function testWithoutSubscriptionsNothingIsScheduled(): void
    {
        $this->assertSame([], $this->schedule([], subscribed: false)->recurringMessages());
    }

    /**
     * Subscriptions that cannot be read are said, not taken for "nobody subscribed": the queue journals it and keeps the digest tasks it knew, which then show late.
     *
     * Declaring nothing had their rows pruned at the next due tick, and digests stopped with nothing to say so.
     */
    public function testSubscriptionsThatCannotBeReadAreSaid(): void
    {
        $unreadable = $this->subscriptions(send: null, hasAny: static fn (): bool => throw new \RuntimeException('no triples table'));

        $this->expectExceptionMessage('no triples table');
        (new ContactSchedule(new ParameterBag(), $unreadable, $this->markers, $this->clock))->recurringMessages();
    }

    /** What an upgrade must not do: send again a digest that has just gone out. */
    public function testADigestThatJustWentOutWaitsItsFloorFromThen(): void
    {
        $this->markSentAt('day', '2026-10-23 11:00:00');
        $this->markSentAt('week', '2026-10-22 12:00:00');

        $plan = $this->plan($this->schedule());

        $this->assertSame('2026-10-24 10:00:00', $plan['digest-day']['first'], 'an hour ago + 23 h');
        $this->assertSame('2026-10-29 12:00:00', $plan['digest-week']['first'], 'yesterday + 7 d');
    }

    /** The later of the two markers counts: a digest that was tried and failed waits a floor from the try. */
    public function testATriedDigestWaitsItsFloorFromTheTry(): void
    {
        $this->markSentAt('day', '2026-10-20 12:00:00');
        $this->markTriedAt('day', '2026-10-23 10:00:00');

        $this->assertSame('2026-10-24 09:00:00', $this->plan($this->schedule())['digest-day']['first']);
    }

    public function testTheScheduledMessageCarriesTheMarkerItWasBuiltFrom(): void
    {
        $this->assertNull($this->plan($this->schedule())['digest-day']['message']->lastSentAt);

        $this->markSentAt('day', '2026-10-23 11:00:00');
        $this->markTriedAt('week', '2026-10-23 11:00:00');
        $plan = $this->plan($this->schedule());

        $this->assertSame(strtotime('2026-10-23 11:00:00 UTC'), $plan['digest-day']['message']->lastSentAt);
        $this->assertNull($plan['digest-week']['message']->lastSentAt, 'a try is not a send');
    }

    /** Spec section 8: a digest already overdue when the schedule is built (an upgrade, digests switched on, a marker lost) goes at the next tick, once, not at the next phase of a clock nobody anchored. */
    public function testAnOverdueDigestIsDueAtTheFirstTick(): void
    {
        $this->quiet('day', 'month');
        $this->markSentAt('week', '2026-10-15 12:00:00');
        $tick = $this->ticker($this->schedule(), $this->clock, new InMemoryCheckpoint());

        $this->assertSame([], $tick(), 'the tick that starts the Scheduler runs nothing, whatever the task');
        $this->clock->sleep(60);
        $this->assertSame(['digest-week'], $tick(), 'eight days since the last one');
        $this->assertSame([], $tick(), 'once');
    }

    public function testADigestThatNeverWentOutIsDueAtTheFirstTick(): void
    {
        $tick = $this->ticker($this->schedule(), $this->clock, new InMemoryCheckpoint());

        $tick();
        $this->clock->sleep(60);

        $this->assertSame(['digest-day', 'digest-week', 'digest-month'], $tick(), 'the old scheduler sent them all at its first pass');
    }

    /** A digest that failed (the handler stamped the try and threw) must wait a floor, or every tick would mail again those it had reached. */
    public function testAnOverdueDigestThatFailsComesBackOneFloorLater(): void
    {
        $this->quiet('week', 'month');
        $tick = $this->ticker($this->schedule(), $this->clock, new InMemoryCheckpoint());
        $tick();
        $this->clock->sleep(60);
        $this->assertSame(['digest-day'], $tick());

        $this->markTriedAt('day', $this->clock->now()->format('Y-m-d H:i:s'));
        $this->clock->sleep(30 * 60);
        $this->assertSame([], $tick(), 'no sent marker, and still not tried again at the next tick');
        $this->clock->sleep(21 * 3600);
        $this->assertSame([], $tick(), 'nor before the floor');
        $this->clock->sleep(2 * 3600);
        $this->assertSame(['digest-day'], $tick(), 'a floor after the try');
    }

    /** A hibernated wiki leaves no trace: the digest is due again at the next tick, without waiting a floor. */
    public function testADigestSkippedWhileHibernatedIsDueAgainAtTheNextTick(): void
    {
        $this->quiet('week', 'month');
        $tick = $this->ticker($this->schedule(), $this->clock, new InMemoryCheckpoint());
        $tick();
        $this->clock->sleep(60);
        $due = $tick();
        $this->assertSame(['digest-day'], $due);
        $subscriptions = $this->subscriptions(send: static fn (): int => 1);
        $this->handler($subscriptions, hibernated: true)($this->plan($this->schedule())['digest-day']['message']);
        $this->assertSame([], $subscriptions->sent);

        $this->clock->sleep(60);

        $this->assertSame($due, $tick(), 'nothing was noted, so nothing postpones it');
    }

    public function testTheTasksAreNamedInTheTranslationsOfTheWiki(): void
    {
        foreach (MailSubscriptions::PERIODS as $period) {
            $label = (new SendDigest($period))->label();
            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('CONTACT_TASK_DIGEST', $label, "{$period} has a translation");
        }
        $this->assertNotSame((new SendDigest('day'))->label(), (new SendDigest('week'))->label());
    }

    public function testAnUnknownPeriodIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SendDigest('year');
    }

    public function testTheMarkerIsWrittenOnlyAfterSuccess(): void
    {
        $failing = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('2 of 5 mails could not be sent'));
        try {
            $this->handler($failing)(new SendDigest('week'));
            $this->fail('a digest that failed must say so');
        } catch (\RuntimeException $failed) {
            $this->assertStringContainsString('2 of 5', $failed->getMessage());
        }
        $this->assertNull($this->markers->lastSentAt('week'), 'a period that failed is not a period that was served');

        $sending = $this->subscriptions(send: static fn (): int => 5);
        $this->handler($sending)(new SendDigest('week'));

        $this->assertSame(['week'], $sending->sent);
        $sentAt = $this->markers->lastSentAt('week');
        $this->assertNotNull($sentAt);
        $this->assertEqualsWithDelta(time(), $sentAt->getTimestamp(), 5);
        $this->assertNull($this->markers->lastSentAt('day'), 'one marker per period');
        $this->assertFileExists($this->root . '/private/digests/week.last');
    }

    public function testAHibernatedWikiSendsNothingAndIsNotMarked(): void
    {
        $subscriptions = $this->subscriptions(send: static fn (): int => 1);

        $this->handler($subscriptions, hibernated: true)(new SendDigest('day'));

        $this->assertSame([], $subscriptions->sent);
        $this->assertNull($this->markers->lastSentAt('day'));
    }

    public function testTheTryIsNotedBeforeTheSendAndTheSendAfterIt(): void
    {
        $seen = [];
        $subscriptions = $this->subscriptions(send: function () use (&$seen): int {
            $seen = [$this->markers->lastTriedAt('week') !== null, $this->markers->lastSentAt('week') !== null];

            return 1;
        });

        $this->handler($subscriptions)(new SendDigest('week'));

        $this->assertSame([true, false], $seen, 'tried when the send begins, sent when it is over');
        $this->assertNotNull($this->markers->lastSentAt('week'));
    }

    public function testAFailedDigestLeavesATryAndNoSend(): void
    {
        $subscriptions = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('smtp down'));

        try {
            $this->handler($subscriptions)(new SendDigest('week'));
            $this->fail('the failure is the queue\'s to show');
        } catch (\RuntimeException) {
        }

        $this->assertNotNull($this->markers->lastTriedAt('week'));
        $this->assertNull($this->markers->lastSentAt('week'));
    }

    /** The command (or a cron) sent the period while the message waited in the queue: mailing everybody again is the one thing to avoid. */
    public function testADigestSentMeanwhileIsNotSentAgain(): void
    {
        $message = $this->plan($this->schedule())['digest-day']['message'];
        $this->markers->markSent('day');
        $subscriptions = $this->subscriptions(send: static fn (): int => 3);

        $this->handler($subscriptions)($message);

        $this->assertSame([], $subscriptions->sent);
        $this->assertNull($this->markers->lastTriedAt('day'));
    }

    /** "Run now" on the jobs page and a retry from the failed queue both have to work. */
    public function testARunNowAndARetryStillSend(): void
    {
        $this->markSentAt('day', '2026-10-21 12:00:00');
        $subscriptions = $this->subscriptions(send: static fn (): int => 3);

        // Built fresh by the schedule, as QueueScheduleTicker::runNow() does: it carries the marker as it is now.
        $this->handler($subscriptions)($this->plan($this->schedule())['digest-day']['message']);
        $this->assertSame(['day'], $subscriptions->sent);

        // A message that failed once (a try, no new send) and is retried by an admin.
        $failed = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('smtp down'));
        $message = $this->plan($this->schedule())['digest-week']['message'];
        try {
            $this->handler($failed)($message);
        } catch (\RuntimeException) {
        }
        $retry = $this->subscriptions(send: static fn (): int => 3);
        $this->handler($retry)($message);
        $this->assertSame(['week'], $retry->sent);
    }

    public function testAFailedDigestIsNotRetried(): void
    {
        $attempts = 0;
        $subscriptions = $this->subscriptions(send: static function () use (&$attempts): int {
            $attempts++;
            throw new \RuntimeException('smtp down');
        });
        $handler = $this->handler($subscriptions);
        $this->kit = new QueueTestKit([SendDigest::class => static fn (SendDigest $message) => $handler($message)]);
        $message = new SendDigest('day');
        $this->assertInstanceOf(RetryPolicy::class, $message);
        $this->assertSame(0, $message->maxRetries(), 'a second run would mail again the subscribers the first one reached');
        $this->assertFalse((new QueueRetryStrategy())->isRetryable(Envelope::wrap($message)->with(new RedeliveryStamp(0))));

        $queued = $this->kit->bus->dispatch($message)->last(TransportMessageIdStamp::class);
        $this->assertNotNull($queued);
        $this->kit->consumer->handleById((int)$queued->getId(), QueueConsumerMode::Command);

        $this->assertSame(1, $attempts);
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'nothing is left to retry');
        $this->assertSame(1, $this->kit->statistics->failedCount(), 'the admin sees it in the failed queue');
        $this->assertSame([SendDigest::class], array_column($this->kit->statistics->failed(), 'type'));
        $this->assertNull($this->markers->lastSentAt('day'));
    }

    /**
     * The schedule is built anew at every due tick, from the markers as they are then: a digest that was sent must not come due again at the next tick of another task.
     */
    public function testASentDigestIsNotDueAgainAtTheNextTickOfAnotherTask(): void
    {
        $this->quiet('week', 'month');
        $clock = $this->clock;
        $provider = new class($this->schedule()) implements ProvidesSchedule {
            public function __construct(private readonly ContactSchedule $contact)
            {
            }

            public function recurringMessages(): array
            {
                return [RecurringMessage::every('30 minutes', new QueueTestTask('housekeeping')), ...$this->contact->recurringMessages()];
            }
        };
        $tick = $this->ticker($provider, $clock, new InMemoryCheckpoint());

        $this->assertSame([], $tick(), 'the schedule starts now');
        $clock->sleep(60);
        $this->assertSame(['digest-day'], $tick(), 'overdue: due at the first tick');
        $this->markSentAt('day', $clock->now()->modify('+90 seconds')->format('Y-m-d H:i:s'));
        $clock->sleep(30 * 60);
        $this->assertSame(['housekeeping'], $tick(), 'sent a moment ago: not again at the next tick of another task');
        $clock->sleep(30 * 60);
        $this->assertSame(['housekeeping'], $tick());

        $clock->sleep(22 * 3600 + 300);
        $this->assertSame(['housekeeping', 'digest-day'], $tick(), 'and due again a floor after the marker');
    }

    public function testNothingListensToMaintenanceAfterForDigests(): void
    {
        $services = $this->getWiki()->services;
        $dispatcher = $services->get(EventDispatcher::class);

        $this->assertFalse($services->has('YesWiki\\Contact\\Service\\ContactDigestScheduler'), 'the scheduler of the housekeeping pass is gone');
        foreach ($dispatcher->getListeners('maintenance.after') as $listener) {
            $owner = is_array($listener) ? $listener[0] : $listener;
            if (is_object($owner)) {
                $this->assertStringStartsNotWith('YesWiki\\Contact\\', $owner::class);
            }
        }
        foreach ($services->get(MailSubscriptions::class)::getSubscribedEvents() as $event => $unused) {
            $this->assertNotSame('maintenance.after', $event);
        }
        $this->assertInstanceOf(EventSubscriberInterface::class, $services->get(MailSubscriptions::class), 'fixture: the subscriptions still hear user.deleted');
    }

    /** What makes the schedule and the message count: both are found on the container the wiki really runs on (delete cache/container/* after adding a service class). */
    public function testTheHandlerAndTheScheduleAreDiscoveredOnTheRealContainer(): void
    {
        $services = $this->getWiki()->services;
        $subscriptions = $services->get(MailSubscriptions::class);
        $page = 'SendDigestTestPage';
        $user = 'SendDigestTestUser';

        $this->assertContains(SendDigest::class, $services->get(QueueHandlerLocator::class)->handledTypes());
        $this->assertInstanceOf(SendDigestHandler::class, $services->get(SendDigestHandler::class));
        $this->assertInstanceOf(ProvidesSchedule::class, $services->get(ContactSchedule::class));
        $subscriptions->subscribe($page, $user, 'week');
        try {
            $declared = $services->get(QueueScheduleTicker::class)->declaredTaskIds();
        } finally {
            $subscriptions->unsubscribe($page, $user);
        }
        $this->assertContains('digest-week', $declared, 'the ticker finds the Contact schedule by its tag');
        $this->assertContains('queue-prune', $declared);
    }

    /** `contact:send-digest` stays for a cron or a hand, and notes a digest the way the queue does: after it went out to everybody. */
    public function testTheCommandMarksAPeriodOnlyAfterItWentOut(): void
    {
        $failing = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('did not reach 1 of 2'));
        $failed = $this->runCommand($failing, ['--period' => 'week']);

        $this->assertSame(Command::FAILURE, $failed->getStatusCode());
        $this->assertStringContainsString('did not reach 1 of 2', $failed->getDisplay());
        $this->assertNull($this->markers->lastSentAt('week'));

        $sending = $this->subscriptions(send: static fn (): int => 2);
        $sent = $this->runCommand($sending, ['--period' => 'week']);

        $this->assertSame(Command::SUCCESS, $sent->getStatusCode());
        $this->assertStringContainsString('2 mail(s) sent', $sent->getDisplay());
        $this->assertNotNull($this->markers->lastSentAt('week'));
        $this->assertNull($this->markers->lastSentAt('day'));
        $this->assertSame(Command::INVALID, $this->runCommand($sending, ['--period' => 'year'])->getStatusCode());
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(MailSubscriptions $subscriptions, array $input): CommandTester
    {
        $services = new ServiceLocator([
            MailSubscriptions::class => static fn (): MailSubscriptions => $subscriptions,
            DigestMarkers::class => fn (): DigestMarkers => $this->markers,
            HibernationService::class => static fn (): HibernationService => new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
        ]);
        $tester = new CommandTester(new ContactDigestCommand($services));
        $tester->execute($input);

        return $tester;
    }

    /**
     * One tick of the Scheduler over a schedule rebuilt from the provider each time, as the queue does: the ids of the tasks that come due.
     *
     * @return \Closure(): list<string>
     */
    private function ticker(ProvidesSchedule $provider, MockClock $clock, InMemoryCheckpoint $checkpoint): \Closure
    {
        return static function () use ($provider, $clock, $checkpoint): array {
            $schedule = (new Schedule())->processOnlyLastMissedRun(true);
            foreach ($provider->recurringMessages() as $recurring) {
                $schedule->add($recurring);
            }
            $ids = [];
            foreach ((new ScheduleGenerator($schedule, 'test', $clock, $checkpoint))->getMessages() as $message) {
                if ($message instanceof ScheduledTask) {
                    $ids[] = $message->taskId();
                }
            }

            return $ids;
        };
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function schedule(array $parameters = [], bool $subscribed = true): ContactSchedule
    {
        return new ContactSchedule(new ParameterBag($parameters), $this->subscriptions(send: null, hasAny: static fn (): bool => $subscribed), $this->markers, $this->clock);
    }

    private function handler(MailSubscriptions $subscriptions, bool $hibernated = false): SendDigestHandler
    {
        $hibernation = new class(new ParameterBag(), $hibernated) extends HibernationService {
            public function __construct(ParameterBag $params, private readonly bool $asleep)
            {
                parent::__construct($params);
            }

            public function isWikiHibernatedNow(): bool
            {
                return $this->asleep;
            }
        };

        return new SendDigestHandler($subscriptions, $this->markers, $hibernation);
    }

    /**
     * The wiki's subscriptions with what is read and sent replaced.
     *
     * @param (\Closure(string): int)|null $send
     * @param (\Closure(): bool)|null      $hasAny
     */
    private function subscriptions(?\Closure $send, ?\Closure $hasAny = null): RecordingSubscriptions
    {
        $services = $this->getWiki()->services;

        return new RecordingSubscriptions($services->get(TripleStore::class), $services->get(DbService::class), $services, $send, $hasAny);
    }

    /**
     * What a schedule declares: per task id, its message, the run after NOW and the gap between runs.
     *
     * @return array<string, array{message: SendDigest, first: string, every: int}>
     */
    private function plan(ContactSchedule $schedule): array
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
                $this->assertInstanceOf(SendDigest::class, $message);
                $plan[$message->taskId()] = [
                    'message' => $message,
                    'first' => $first->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'every' => $second->getTimestamp() - $first->getTimestamp(),
                ];
            }
        }

        return $plan;
    }

    private function markSentAt(string $period, string $when): void
    {
        $this->markers->markSent($period);
        touch($this->root . '/private/digests/' . $period . '.last', (new \DateTimeImmutable($when, new \DateTimeZone('UTC')))->getTimestamp());
    }

    private function markTriedAt(string $period, string $when): void
    {
        $this->markers->markTried($period);
        touch($this->root . '/private/digests/' . $period . '.tried', (new \DateTimeImmutable($when, new \DateTimeZone('UTC')))->getTimestamp());
    }

    /** Periods that went out just now, so that a test about another one is not about them. */
    private function quiet(string ...$periods): void
    {
        foreach ($periods as $period) {
            $this->markSentAt($period, self::NOW);
        }
    }
}

/** The Scheduler's checkpoint, kept in memory: what the database one holds between ticks, without the database. */
final class InMemoryCheckpoint implements CheckpointInterface
{
    private ?\DateTimeImmutable $from = null;
    private \DateTimeImmutable $time;
    private int $index = -1;

    public function acquire(\DateTimeImmutable $now): bool
    {
        if ($this->from === null) {
            $this->from = $this->time = $now;
        }

        return true;
    }

    public function from(): \DateTimeImmutable
    {
        return $this->from ?? throw new \LogicException('The checkpoint was never acquired.');
    }

    public function time(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function save(\DateTimeImmutable $time, int $index): void
    {
        $this->time = $time;
        $this->index = $index;
    }

    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
    }
}

/** The wiki's subscriptions with the reading of "anybody?" and the sending replaced, and a note of the periods that were sent. */
final class RecordingSubscriptions extends MailSubscriptions
{
    /** @var list<string> */
    public array $sent = [];

    /**
     * @param (\Closure(string): int)|null $send
     * @param (\Closure(): bool)|null      $hasAnyOverride
     */
    public function __construct(
        TripleStore $triples,
        DbService $db,
        ContainerInterface $services,
        private readonly ?\Closure $send,
        private readonly ?\Closure $hasAnyOverride,
    ) {
        parent::__construct($triples, $db, $services);
    }

    public function hasAny(): bool
    {
        return $this->hasAnyOverride === null ? true : ($this->hasAnyOverride)();
    }

    public function send(string $period, string $subject = ''): int
    {
        $this->sent[] = $period;

        return $this->send === null ? 0 : ($this->send)($period);
    }
}

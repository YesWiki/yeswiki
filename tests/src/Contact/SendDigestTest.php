<?php

namespace YesWiki\Test\Contact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Contact\Command\ContactDigestCommand;
use YesWiki\Contact\Message\SendDigest;
use YesWiki\Contact\Message\SendDigestHandler;
use YesWiki\Contact\Scheduler\ContactSchedule;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;
use YesWiki\Test\Kernel\QueueTestTask;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/**
 * The mailing-list digests are scheduled tasks, one per period, on a cron in the wiki's own timezone (messenger / scheduler, spec 9.3).
 *
 * The queue and the Scheduler are the kit's, and the subscriptions are the wiki's
 * own service with its sending replaced by what each test needs. NOW is a Friday, two days
 * before Paris leaves summer time.
 */
#[CoversClass(SendDigest::class)]
#[CoversClass(SendDigestHandler::class)]
#[CoversClass(ContactSchedule::class)]
class SendDigestTest extends YesWikiTestCase
{
    private const NOW = '2026-10-23 12:00:00';

    private ?QueueTestKit $kit = null;
    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new MockClock(self::NOW, 'UTC');
    }

    protected function tearDown(): void
    {
        $this->kit?->dispose();
    }

    /** 7 a.m. in Paris is 05:00 UTC in summer time and 06:00 UTC once it is over, on Sunday the 25th. */
    public function testEachPeriodGoesOutAtSevenInTheMorningOfTheWiki(): void
    {
        $plan = $this->plan($this->schedule());

        $this->assertSame(['digest-day', 'digest-week', 'digest-month'], array_keys($plan));
        $this->assertSame('2026-10-24 05:00:00', $plan['digest-day']['first'], 'tomorrow, Saturday, at 7');
        $this->assertSame('2026-10-26 06:00:00', $plan['digest-week']['first'], 'on Monday at 7');
        $this->assertSame('2026-11-01 06:00:00', $plan['digest-month']['first'], 'on the first of the month at 7');
        foreach (MailSubscriptions::PERIODS as $period) {
            $this->assertEquals(new SendDigest($period), $plan['digest-' . $period]['message']);
        }
    }

    public function testSevenIsSevenInTheTimezoneTheWikiIsSetTo(): void
    {
        $this->assertSame('2026-10-24 11:00:00', $this->plan($this->schedule(['timezone' => 'America/New_York']))['digest-day']['first']);
    }

    /** A wiki whose configuration names no timezone (absent or empty) runs on PHP's, which is what its pages show. */
    #[DataProvider('noTimezone')]
    public function testWithoutATimezoneOfItsOwnTheWikiRunsOnPhps(?string $timezone): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
        try {
            $first = $this->plan($this->schedule(['timezone' => $timezone]))['digest-day']['first'];
        } finally {
            date_default_timezone_set($default);
        }

        $this->assertSame('2026-10-23 22:00:00', $first, '7 a.m. in Tokyo, tonight in UTC');
    }

    /** @return array<string, array{?string}> */
    public static function noTimezone(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    /**
     * The audit's finding: with a floor counted from the last run, every digest left a little earlier than the one before it.
     *
     * Ticks come every 47 minutes, so no run falls on the hour; whatever time one went at, the
     * next is still at the first tick after 7, and once a day.
     */
    public function testTheDigestsKeepTheirHourDayAfterDay(): void
    {
        $tick = $this->ticker($this->withHousekeeping($this->schedule()));
        $paris = new \DateTimeZone('Europe/Paris');
        $sent = [];

        $tick();
        while ($this->clock->now() < new \DateTimeImmutable('2026-10-28 00:00:00', $paris)) {
            $this->clock->sleep(47 * 60);
            foreach ($tick() as $taskId) {
                if ($taskId !== 'housekeeping') {
                    $sent[] = $taskId . ' ' . $this->clock->now()->setTimezone($paris)->format('D d H');
                }
            }
        }

        $this->assertSame([
            'digest-day Sat 24 07',
            'digest-day Sun 25 07',
            'digest-day Mon 26 07',
            'digest-week Mon 26 07',
            'digest-day Tue 27 07',
        ], $sent);
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
        (new ContactSchedule($this->params([]), $unreadable))->recurringMessages();
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

    public function testTheHandlerSendsThePeriodAndSaysWhenItFailed(): void
    {
        $sending = $this->subscriptions(send: static fn (): int => 5);
        $this->handler($sending)(new SendDigest('week'));
        $this->assertSame(['week'], $sending->sent);

        $failing = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('2 of 5 mails could not be sent'));
        $this->expectExceptionMessage('2 of 5');
        $this->handler($failing)(new SendDigest('week'));
    }

    /** A hibernated wiki (an archive, an update) is read-only and mails nothing; that is not a failure. */
    public function testAHibernatedWikiSendsNothing(): void
    {
        $subscriptions = $this->subscriptions(send: static fn (): int => 1);

        $this->handler($subscriptions, hibernated: true)(new SendDigest('day'));

        $this->assertSame([], $subscriptions->sent);
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

        $queued = $this->kit->bus->dispatch(new SendDigest('day'))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($queued);
        $this->kit->consumer->handleById((int)$queued->getId(), QueueConsumerMode::Api);

        $this->assertSame(1, $attempts);
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'nothing is left to retry: a second run would mail again the subscribers the first one reached');
        $this->assertSame(1, $this->kit->statistics->failedCount(), 'the admin sees it in the failed queue');
        $this->assertSame([SendDigest::class], array_column($this->kit->statistics->failed(), 'type'));
    }

    /** The markers the floors were counted from are gone from the wikis that kept them. */
    public function testTheMigrationDeletesTheOldMarkers(): void
    {
        $services = $this->getWiki()->services;
        $storage = $services->get(Storage::class);
        $storage->write('private/digests/day.last', '1761213600');

        require_once 'src/migrations/20261103090000_DigestMarkersGiveWayToTheSchedule.php';
        $migration = new \DigestMarkersGiveWayToTheSchedule();
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();
        $migration->run();

        $this->assertFalse($storage->fileExists('private/digests/day.last'));
        $this->assertFalse($storage->directoryExists('private/digests'));
    }

    /** The subscriptions hear user.deleted, and no longer the housekeeping pass: the digests are scheduled tasks. */
    public function testTheSubscriptionsHearUserDeletedAndNotTheHousekeepingPass(): void
    {
        $events = array_keys(MailSubscriptions::getSubscribedEvents());

        $this->assertContains('user.deleted', $events);
        $this->assertNotContains('maintenance.after', $events);
    }

    /** The ticker finds the Contact schedule by its tag on the container the wiki really runs on (delete cache/container/* after adding a service class). */
    public function testTheDigestScheduleIsDeclaredByTheTicker(): void
    {
        $services = $this->getWiki()->services;
        $subscriptions = $services->get(MailSubscriptions::class);
        $page = 'SendDigestTestPage';
        $user = 'SendDigestTestUser';

        $subscriptions->subscribe($page, $user, 'week');
        try {
            $declared = $services->get(QueueScheduleTicker::class)->declaredTaskIds();
        } finally {
            $subscriptions->unsubscribe($page, $user);
        }
        $this->assertContains('digest-week', $declared);
    }

    /** `contact:send-digest` stays for a cron or a hand: it sends, and says when that failed. */
    public function testTheCommandSendsThePeriodAndSaysWhenItFailed(): void
    {
        $failing = $this->subscriptions(send: static fn (): int => throw new \RuntimeException('did not reach 1 of 2'));
        $failed = $this->runCommand($failing, ['--period' => 'week']);

        $this->assertSame(Command::FAILURE, $failed->getStatusCode());
        $this->assertStringContainsString('did not reach 1 of 2', $failed->getDisplay());

        $sending = $this->subscriptions(send: static fn (): int => 2);
        $sent = $this->runCommand($sending, ['--period' => 'week']);

        $this->assertSame(Command::SUCCESS, $sent->getStatusCode());
        $this->assertStringContainsString('2 mail(s) sent', $sent->getDisplay());
        $this->assertSame(['week'], $sending->sent);
        $this->assertSame(Command::INVALID, $this->runCommand($sending, ['--period' => 'year'])->getStatusCode());
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(MailSubscriptions $subscriptions, array $input): CommandTester
    {
        $services = new ServiceLocator([
            MailSubscriptions::class => static fn (): MailSubscriptions => $subscriptions,
            HibernationService::class => static fn (): HibernationService => new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
        ]);
        $tester = new CommandTester(new ContactDigestCommand($services));
        $tester->execute($input);

        return $tester;
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

    /** The digests beside a task that ticks every half hour, as the wiki's housekeeping does. */
    private function withHousekeeping(ContactSchedule $contact): ProvidesSchedule
    {
        return new class($contact) implements ProvidesSchedule {
            public function __construct(private readonly ContactSchedule $contact)
            {
            }

            public function recurringMessages(): array
            {
                return [RecurringMessage::every('30 minutes', new QueueTestTask('housekeeping')), ...$this->contact->recurringMessages()];
            }
        };
    }

    /**
     * @param array<string, mixed> $parameters the wiki's, its timezone Paris unless they say otherwise
     */
    private function schedule(array $parameters = [], bool $subscribed = true): ContactSchedule
    {
        return new ContactSchedule($this->params($parameters), $this->subscriptions(send: null, hasAny: static fn (): bool => $subscribed));
    }

    /** @param array<string, mixed> $parameters */
    private function params(array $parameters): ParameterBag
    {
        return new ParameterBag($parameters + ['timezone' => 'Europe/Paris']);
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

        return new SendDigestHandler($subscriptions, $hibernation);
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
     * What a schedule declares: per task id, its message and its first run after NOW, in UTC.
     *
     * @return array<string, array{message: SendDigest, first: string}>
     */
    private function plan(ContactSchedule $schedule): array
    {
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
        $plan = [];
        foreach ($schedule->recurringMessages() as $recurring) {
            $trigger = $recurring->getTrigger();
            $first = $trigger->getNextRunDate($now);
            $this->assertNotNull($first);
            foreach ($recurring->getMessages(new MessageContext('test', $recurring->getId(), $trigger, $now)) as $message) {
                $this->assertInstanceOf(SendDigest::class, $message);
                $plan[$message->taskId()] = [
                    'message' => $message,
                    'first' => $first->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $plan;
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

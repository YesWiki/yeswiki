<?php

namespace YesWiki\Test\Contact;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Mail subscriptions to pages. */
class MailSubscriptionsTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'MailSubscriptionsTestPage';
    private const OTHER_PAGE_TAG = 'MailSubscriptionsOtherPage';
    private const ANN = 'MailSubscriptionsAnn';
    private const ANNA = 'MailSubscriptionsAnna';

    private YesWikiRuntime $wiki;
    private UserManager $userManager;
    private MailSubscriptions $subscriptions;

    /** @var list<string> */
    private array $createdGroups = [];

    /** @var list<string> the extra subscribers a test made */
    private array $bulkUsers = [];

    private ?Request $previousRequest = null;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $GLOBALS['yeswikiServices'] = $this->wiki->services;
        $this->userManager = $this->wiki->services->get(UserManager::class);
        $this->subscriptions = $this->wiki->services->get(MailSubscriptions::class);
        foreach ([self::ANN, self::ANNA] as $name) {
            if (!$this->userManager->getOneByName($name)) {
                $this->userManager->create($name, strtolower($name) . '@example.com', 'a-long-enough-password');
            }
        }
        $pageManager = $this->wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => "====== Mail subscriptions title ======\nMAIL_SUBSCRIPTIONS_MARKER\n{{mailperiod}}"], '', true);
        $pageManager->save(self::OTHER_PAGE_TAG, [PageBody::CONTENT => "====== Other subscriptions title ======\nOTHER_MARKER"], '', true);
        $current = $this->wiki->services->get(CurrentRequest::class);
        $this->previousRequest = $current->has() ? $current->get() : null;
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthenticationService::class)->logout();
        foreach ($this->createdGroups as $group) {
            if ($this->wiki->services->get(GroupManager::class)->groupExists($group)) {
                $this->wiki->services->get(GroupManager::class)->delete($group);
            }
        }
        $this->createdGroups = [];
        foreach ([self::ANN, self::ANNA, ...$this->bulkUsers] as $name) {
            $this->subscriptions->forgetUser($name);
            if ($user = $this->userManager->getOneByName($name)) {
                $this->userManager->delete($user);
            }
        }
        foreach ([self::PAGE_TAG, self::OTHER_PAGE_TAG] as $tag) {
            $this->subscriptions->unsubscribe($tag, self::ANN);
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $this->subscriptions->unsubscribe('MailSubscriptionsDeletedPage', self::ANN);
        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
    }

    public function testASubscriptionIsATripleNotAGroup(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'week');

        $this->assertSame('week', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertCount(1, $this->triplesOf(self::ANN));
        $this->assertFalse($this->wiki->services->get(GroupManager::class)->groupExists('Mail' . self::PAGE_TAG . 'Week'));
    }

    public function testChangingPeriodLeavesTheFormerOne(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'month');

        $this->assertSame('month', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame([], $this->subscriptions->subscribersAt('day')[self::PAGE_TAG] ?? []);
    }

    public function testUnsubscribingAUserKeepsTheOneWhoseNameContainsTheirs(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');

        $this->subscriptions->unsubscribe(self::PAGE_TAG, self::ANN);

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame('day', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANNA));
    }

    public function testAnUnknownPeriodIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'year');
    }

    public function testDeletingAUserDropsTheirSubscriptions(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANN, 'month');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');

        $this->wiki->services->get(UserOperationsService::class)->purge(self::requireUser($this->userManager->getOneByName(self::ANN)));

        $this->assertSame([], $this->triplesOf(self::ANN));
        $this->assertSame('day', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANNA));
    }

    public function testAWriteFromElsewhereIsSeenAtOnce(): void
    {
        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $db = $this->wiki->services->get(DbService::class);
        $db->query(
            'INSERT INTO ' . $db->prefixTable('triples') . ' (resource, property, value) VALUES (?, ?, ?)',
            [self::PAGE_TAG, MailSubscriptions::PROPERTY_PREFIX . 'week', self::ANN]
        );

        $this->assertSame('week', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertTrue($this->subscriptions->hasAny());
    }

    public function testEachPageIsSentToItsSubscribersWithItsOwnTitle(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANN, 'week');
        $this->subscriptions->subscribe('MailSubscriptionsDeletedPage', self::ANN, 'day');

        $mails = array_values(array_filter(
            $this->sendCapturing('day'),
            fn (array $mail) => str_contains($mail['email'], 'mailsubscriptions')
        ));

        $this->assertCount(3, $mails);
        $byRecipientAndTitle = array_map(fn ($mail) => $mail['email'] . ' ' . (str_contains($mail['subject'], 'Other') ? 'other' : 'page'), $mails);
        sort($byRecipientAndTitle);
        $this->assertSame([
            strtolower(self::ANN) . '@example.com page',
            strtolower(self::ANNA) . '@example.com other',
            strtolower(self::ANNA) . '@example.com page',
        ], $byRecipientAndTitle);
        $pageMail = array_values(array_filter($mails, fn ($mail) => !str_contains($mail['subject'], 'Other')))[0];
        $this->assertStringContainsString('Mail subscriptions title', $pageMail['subject']);
        $this->assertStringContainsString('MAIL_SUBSCRIPTIONS_MARKER', $pageMail['html']);
        $this->assertStringNotContainsString('mail-period', $pageMail['html']);
    }

    /**
     * A digest that cannot reach everybody must say so, after it has tried everybody: the caller does not mark the period as served, and the mails that left must not be lost to the ones that did not.
     */
    public function testASendTriesEveryoneBeforeItSaysWhoWasNotReached(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANNA, 'day');
        $reached = [];

        try {
            $this->sendCapturing('day', function (string $email, string $subject) use (&$reached): bool {
                if (!str_contains($email, 'mailsubscriptions')) {
                    return true;
                }
                if ($email === strtolower(self::ANN) . '@example.com') {
                    throw new \RuntimeException('mailbox full');
                }
                $reached[] = $email . ' ' . (str_contains($subject, 'Other') ? 'other' : 'page');

                return true;
            });
            $this->fail('a digest some recipients did not get must throw');
        } catch (\RuntimeException $failed) {
            $this->assertStringContainsString("'day' digest did not reach 1 of 3 recipient(s): ", $failed->getMessage());
            $this->assertStringContainsString(self::PAGE_TAG . ' (' . self::ANN . ')', $failed->getMessage());
            $this->assertStringContainsString('mailbox full', $failed->getMessage());
            $this->assertStringNotContainsString('@example.com', $failed->getMessage(), 'user names, not addresses, end up in the journal');
        }

        sort($reached);
        $this->assertSame([
            strtolower(self::ANNA) . '@example.com other',
            strtolower(self::ANNA) . '@example.com page',
        ], $reached, 'the failure of one did not stop the others');
    }

    public function testAMailTheMailerRefusesIsAFailureToo(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $mailer = $this->createStub(Mailer::class);
        $mailer->method('lastError')->willReturn('SMTP said: mailbox unavailable');

        try {
            $this->sendCapturing('day', static fn (string $email): bool => !str_contains($email, 'mailsubscriptions'), [Mailer::class => $mailer]);
            $this->fail('a mail the mailer refuses must be reported');
        } catch (\RuntimeException $failed) {
            $this->assertStringContainsString(self::PAGE_TAG . ' (' . self::ANN . ')', $failed->getMessage());
            $this->assertStringContainsString('. SMTP said: mailbox unavailable', $failed->getMessage(), 'the reason the mailer gave');
        }
    }

    /** Only ten names are listed, the others are counted, and the total is what was attempted. */
    public function testALongListOfFailuresIsCutAfterTenNames(): void
    {
        $this->subscribeBulk(12);

        try {
            $this->sendCapturing('day', static fn (string $email): bool => !str_contains($email, 'mailsubscriptions'));
            $this->fail('twelve refused mails must be reported');
        } catch (\RuntimeException $failed) {
            $message = $failed->getMessage();
            $this->assertStringContainsString('did not reach 12 of 12 recipient(s)', $message);
            $this->assertSame(1, preg_match('/' . self::PAGE_TAG . ' \(([^)]*)\)/', $message, $names));
            $this->assertCount(10, explode(', ', $names[1] ?? ''), 'ten names are listed');
            $this->assertStringContainsString(') and 2 more', $message);
        }
    }

    /** A subscriber with no mail address (or gone) is neither attempted nor a failure. */
    public function testASubscriberWithoutAnAddressIsLeftOut(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, 'MailSubscriptionsNobody', 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');

        try {
            $this->sendCapturing('day', static fn (string $email): bool => !str_contains($email, 'mailsubscriptions'));
            $this->fail('the refused mail must be reported');
        } catch (\RuntimeException $failed) {
            $this->assertStringContainsString('did not reach 1 of 1 recipient(s)', $failed->getMessage());
            $this->assertStringNotContainsString('MailSubscriptionsNobody', $failed->getMessage());
        } finally {
            $this->subscriptions->unsubscribe(self::PAGE_TAG, 'MailSubscriptionsNobody');
        }
    }

    /** At most three reasons are given, each once, in the order they came. */
    public function testTheReasonsAreMergedAndCutAfterThree(): void
    {
        $this->subscribeBulk(5);
        $count = 0;

        try {
            $this->sendCapturing('day', static function (string $email) use (&$count): bool {
                if (!str_contains($email, 'mailsubscriptions')) {
                    return true;
                }
                throw new \RuntimeException('reason ' . ($count++ % 4));
            });
            $this->fail('refused mails must be reported');
        } catch (\RuntimeException $failed) {
            $this->assertStringEndsWith('. reason 0; reason 1; reason 2', $failed->getMessage());
        }
    }

    /** A page that cannot be rendered fails its own subscribers with the reason, the other pages are still sent, and the next send is not left "sending". */
    public function testAPageThatCannotBeRenderedFailsAloneAndLeavesTheSendingFlag(): void
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANNA, 'day');
        $real = $this->wiki->services->get(MarkdownFormatterService::class);
        $formatter = $this->createStub(MarkdownFormatterService::class);
        $formatter->method('format')->willReturnCallback(static function (string $markup) use ($real): string {
            if (str_contains($markup, self::OTHER_PAGE_TAG)) {
                throw new \RuntimeException('include loop');
            }

            return $real->format($markup);
        });
        $mails = [];

        try {
            $this->sendCapturing('day', null, [MarkdownFormatterService::class => $formatter], $mails);
            $this->fail('a page that cannot be rendered must be reported');
        } catch (\RuntimeException $failed) {
            $this->assertStringContainsString('did not reach 1 of 2 recipient(s): ' . self::OTHER_PAGE_TAG . ' (' . self::ANNA . ')', $failed->getMessage());
            $this->assertStringContainsString('. include loop', $failed->getMessage());
        }

        $this->assertContains(strtolower(self::ANN) . '@example.com', array_column($mails, 'email'), 'the other page went out');
        $this->assertNotContains(strtolower(self::ANNA) . '@example.com', array_column($mails, 'email'));
        $this->assertFalse($this->subscriptions->isSending(), 'the flag that silences the subscribe buttons is down again');
    }

    public function testTheMigrationMovesSubscriptionGroupsToTriples(): void
    {
        $this->createGroup('Mail' . self::PAGE_TAG . 'Week', [self::ANN, self::ANNA, '@admins']);
        $this->createGroup('Mail' . self::PAGE_TAG . 'Day', [self::ANN]);
        $this->createGroup('MailSubscriptionsNoSuchPageDay', [self::ANNA]);

        require_once 'src/migrations/20261007140000_MailSubscriptionsLeaveTheGroups.php';
        $migration = new \MailSubscriptionsLeaveTheGroups();
        $migration->setServices($this->wiki->services);
        $migration->run();

        $groups = $this->wiki->services->get(GroupManager::class);
        $this->assertSame('day', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame('week', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANNA));
        $this->assertSame([self::ANNA], $this->subscriptions->subscribersAt('week')[self::PAGE_TAG]);
        $this->assertFalse($groups->groupExists('Mail' . self::PAGE_TAG . 'Week'));
        $this->assertFalse($groups->groupExists('Mail' . self::PAGE_TAG . 'Day'));
        $this->assertTrue($groups->groupExists('MailSubscriptionsNoSuchPageDay'));
    }

    public function testAGetRequestDoesNotSubscribe(): void
    {
        $this->runAction(Request::create('/?' . self::PAGE_TAG . '&subscribe=day', 'GET', ['subscribe' => 'day']));

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
    }

    public function testAPostWithoutATokenDoesNotSubscribe(): void
    {
        $output = $this->runAction(Request::create('/?' . self::PAGE_TAG, 'POST', ['subscribe' => 'day']));

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertStringContainsString('yw-alert--danger', $output);
    }

    public function testThePeriodButtonsArePostButtonsCarryingAToken(): void
    {
        $output = $this->runAction(Request::create('/?' . self::PAGE_TAG));

        $this->assertStringNotContainsString('subscribe=', $output);
        $this->assertMatchesRegularExpression('/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/', $output);
        $this->assertMatchesRegularExpression('/<button type="submit" name="subscribe" value="day"/', $output);
    }

    /** @return list<array<string, mixed>> */
    private function triplesOf(string $userName): array
    {
        $db = $this->wiki->services->get(DbService::class);

        return $db->loadAll(
            'SELECT * FROM ' . $db->prefixTable('triples') . ' WHERE property LIKE ? AND value = ?',
            [MailSubscriptions::PROPERTY_PREFIX . '%', $userName]
        );
    }

    /**
     * @param (\Closure(string, string): bool)|null                     $outcome  whether a mail is delivered, or throws; every mail is by default
     * @param array<string, object>                                     $replaced services the send reads instead of the wiki's, by class name
     * @param list<array{email: string, subject: string, html: string}> $captured filled with the mails even when the send throws
     *
     * @return list<array{email: string, subject: string, html: string}>
     */
    private function sendCapturing(string $period, ?\Closure $outcome = null, array $replaced = [], array &$captured = []): array
    {
        $real = $this->wiki->services;
        $services = new class($real, $replaced) implements ContainerInterface {
            /** @param array<string, object> $replaced */
            public function __construct(private readonly ContainerInterface $real, private readonly array $replaced)
            {
            }

            public function get(string $id): mixed
            {
                return $this->replaced[$id] ?? $this->real->get($id);
            }

            public function has(string $id): bool
            {
                return $this->real->has($id);
            }
        };
        $service = new class($real->get(TripleStore::class), $real->get(DbService::class), $services, $outcome) extends MailSubscriptions {
            /** @var list<array{email: string, subject: string, html: string}> */
            public array $mails = [];

            public function __construct(TripleStore $tripleStore, DbService $db, ContainerInterface $services, private readonly ?\Closure $outcome)
            {
                parent::__construct($tripleStore, $db, $services);
            }

            protected function deliver(string $email, string $subject, string $text, string $html): bool
            {
                $this->mails[] = compact('email', 'subject', 'html');

                return $this->outcome === null || ($this->outcome)($email, $subject);
            }
        };
        try {
            $service->send($period);
        } finally {
            $captured = $service->mails;
        }

        return $service->mails;
    }

    /** Subscribes that many extra users with an address to the page, at a day. */
    private function subscribeBulk(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $name = sprintf('MailSubscriptionsBulk%02d', $i);
            $this->bulkUsers[] = $name;
            if (!$this->userManager->getOneByName($name)) {
                $this->userManager->create($name, strtolower($name) . '@example.com', 'a-long-enough-password');
            }
            $this->subscriptions->subscribe(self::PAGE_TAG, $name, 'day');
        }
    }

    /** @param list<string> $members */
    private function createGroup(string $name, array $members): void
    {
        $this->createdGroups[] = $name;
        $this->wiki->services->get(GroupManager::class)->create($name, $members);
    }

    private function runAction(Request $request): string
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->wiki->services->get(AuthenticationService::class)->login(self::requireUser($this->userManager->getOneByName(self::ANN)));
        $this->wiki->services->get(CurrentRequest::class)->replace($request);
        $this->wiki->services->get(PageContext::class)->setTag(self::PAGE_TAG);

        return (string)$this->wiki->services->get(ActionRunner::class)->action('mailperiod');
    }
}

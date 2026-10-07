<?php

namespace YesWiki\Test\Contact;

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
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\ActionRunner;
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
        foreach ([self::ANN, self::ANNA] as $name) {
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

    /** @return list<array{email: string, subject: string, html: string}> */
    private function sendCapturing(string $period): array
    {
        $service = new class($this->wiki->services->get(TripleStore::class), $this->wiki->services->get(DbService::class), $this->wiki->services) extends MailSubscriptions {
            /** @var list<array{email: string, subject: string, html: string}> */
            public array $mails = [];

            protected function deliver(string $email, string $subject, string $text, string $html): bool
            {
                $this->mails[] = compact('email', 'subject', 'html');

                return true;
            }
        };
        $service->send($period);

        return $service->mails;
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

<?php

namespace YesWiki\Test\Contact;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Mail subscriptions to a page: stored as triples, one period at a time, exact names, sent per page, and nothing done on a GET or without a token.
 */
class MailSubscriptionsTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'MailSubscriptionsTestPage';
    private const OTHER_PAGE_TAG = 'MailSubscriptionsOtherPage';
    private const ANN = 'MailSubscriptionsAnn';
    private const ANNA = 'MailSubscriptionsAnna';
    private const ADMIN = 'MailSubscriptionsAdmin';

    private $wiki;
    private $userManager;
    private $groupController;
    private $subscriptions;
    private array $createdGroups = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->userManager = $this->wiki->services->get(UserManager::class);
        $this->groupController = $this->wiki->services->get(GroupController::class);
        $this->subscriptions = $this->wiki->services->get(MailSubscriptions::class);
        foreach ([self::ANN, self::ANNA, self::ADMIN] as $name) {
            if (!$this->userManager->getOneByName($name)) {
                $this->userManager->create([
                    'name' => $name,
                    'email' => strtolower($name) . '@example.com',
                    'password' => 'a-long-enough-password',
                ]);
            }
        }
        $pageManager = $this->wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, "======Mail subscriptions title======\nMAIL_SUBSCRIPTIONS_MARKER\n{{mailperiod}}", '', true);
        $pageManager->save(self::OTHER_PAGE_TAG, "======Other subscriptions title======\nOTHER_MARKER", '', true);
        foreach ([self::PAGE_TAG, self::OTHER_PAGE_TAG] as $tag) {
            $this->wiki->services->get(AclService::class)->save($tag, 'read', '*');
        }
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthController::class)->logout();
        foreach ($this->createdGroups as $group) {
            if ($this->groupController->groupExists($group)) {
                $this->groupController->delete($group);
            }
        }
        $this->createdGroups = [];
        if (in_array(self::ADMIN, $this->groupController->getMembers(ADMIN_GROUP), true)) {
            $this->wiki->services->get(\YesWiki\Core\Service\GroupManager::class)->removeMembers(ADMIN_GROUP, [self::ADMIN]);
        }
        foreach ([self::ANN, self::ANNA, self::ADMIN] as $name) {
            $this->subscriptions->forgetUser($name);
            if ($user = $this->userManager->getOneByName($name)) {
                $this->userManager->delete($user);
            }
        }
        foreach ([self::PAGE_TAG, self::OTHER_PAGE_TAG] as $tag) {
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->wiki->request = Request::create('/');
    }

    public function testASubscriptionIsATripleNotAGroup()
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'week');

        $this->assertSame('week', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertCount(1, $this->wiki->services->get(TripleStore::class)->getMatching(self::PAGE_TAG, MailSubscriptions::PROPERTY_PREFIX . 'week', self::ANN, '=', '=', '='));
        $this->assertFalse($this->groupController->groupExists('Mail' . self::PAGE_TAG . 'Week'));
    }

    public function testChangingPeriodLeavesTheFormerOne()
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'month');

        $this->assertSame('month', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame([], $this->subscriptions->subscribersAt('day')[self::PAGE_TAG] ?? []);
    }

    public function testUnsubscribingAUserKeepsTheOneWhoseNameContainsTheirs()
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');

        $this->subscriptions->unsubscribe(self::PAGE_TAG, self::ANN);

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame('day', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANNA));
    }

    public function testAnUnknownPeriodIsRefused()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'year');
    }

    public function testDeletingAUserDropsTheirSubscriptions()
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANN, 'month');
        $this->wiki->services->get(\YesWiki\Core\Service\GroupManager::class)->addMembers(ADMIN_GROUP, [self::ADMIN]);
        $this->wiki->services->get(AuthController::class)->login($this->userManager->getOneByName(self::ADMIN));

        $this->wiki->services->get(UserController::class)->delete($this->userManager->getOneByName(self::ANN));

        $this->assertSame([], $this->wiki->services->get(TripleStore::class)->getMatching(null, MailSubscriptions::PROPERTY_PREFIX . '%', self::ANN, '=', 'LIKE', '='));
    }

    public function testEachPageIsSentToItsSubscribersWithItsOwnTitle()
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANN, 'day');
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANNA, 'day');
        $this->subscriptions->subscribe(self::OTHER_PAGE_TAG, self::ANN, 'week');
        $this->subscriptions->subscribe('MailSubscriptionsDeletedPage', self::ANN, 'day');

        $mails = $this->sendCapturing('day');

        $this->assertCount(3, $mails);
        $byRecipientAndTitle = array_map(fn ($mail) => $mail['email'] . ' ' . (str_contains($mail['subject'], 'Other') ? 'other' : 'page'), $mails);
        sort($byRecipientAndTitle);
        $this->assertSame([
            strtolower(self::ANN) . '@example.com page',
            strtolower(self::ANNA) . '@example.com other',
            strtolower(self::ANNA) . '@example.com page',
        ], $byRecipientAndTitle);
        $pageMail = array_values(array_filter($mails, fn ($mail) => !str_contains($mail['subject'], 'Other')))[0];
        $this->assertStringContainsString('MAIL_SUBSCRIPTIONS_MARKER', $pageMail['html']);
        $this->assertStringNotContainsString('mail-period', $pageMail['html']);
    }

    private function sendCapturing(string $period): array
    {
        $service = new class($this->wiki->services->get(TripleStore::class), $this->wiki->services->get(PageManager::class), $this->userManager, $this->wiki) extends MailSubscriptions {
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

    public function testTheMigrationMovesSubscriptionGroupsToTriples()
    {
        $this->createGroup('Mail' . self::PAGE_TAG . 'Week', [self::ANN, self::ANNA, '@admins']);
        $this->createGroup('Mail' . self::PAGE_TAG . 'Day', [self::ANN]);
        $this->createGroup('MailSubscriptionsNoSuchPageDay', [self::ANNA]);

        require_once 'tools/contact/migrations/20261005120000_ContactMailSubscriptionsFromGroups.php';
        $migration = new \ContactMailSubscriptionsFromGroups();
        $migration->setWiki($this->wiki);
        $migration->setDbService($this->wiki->services->get(DbService::class));
        $migration->setParams($this->wiki->services->get(ParameterBagInterface::class));
        $migration->run();

        $this->assertSame('day', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertSame('week', $this->subscriptions->periodOf(self::PAGE_TAG, self::ANNA));
        $this->assertSame([self::ANNA], $this->subscriptions->subscribersAt('week')[self::PAGE_TAG]);
        $this->assertFalse($this->groupController->groupExists('Mail' . self::PAGE_TAG . 'Week'));
        $this->assertFalse($this->groupController->groupExists('Mail' . self::PAGE_TAG . 'Day'));
        $this->assertTrue($this->groupController->groupExists('MailSubscriptionsNoSuchPageDay'));
    }

    private function createGroup(string $name, array $members): void
    {
        $this->createdGroups[] = $name;
        $this->wiki->services->get(\YesWiki\Core\Service\GroupManager::class)->create($name, $members);
    }

    private function runAction(Request $request): string
    {
        $this->subscriptions->subscribe(self::PAGE_TAG, self::ANNA, 'day');
        $this->wiki->services->get(AuthController::class)->login($this->userManager->getOneByName(self::ANN));
        $this->wiki->request = $request;
        $this->wiki->tag = self::PAGE_TAG;

        return $this->wiki->Action('mailperiod');
    }

    public function testAGetRequestDoesNotSubscribe()
    {
        $this->runAction(Request::create('/?' . self::PAGE_TAG . '&subscribe=day', 'GET', ['subscribe' => 'day']));

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
    }

    public function testAPostWithoutATokenDoesNotSubscribe()
    {
        $output = $this->runAction(Request::create('/?' . self::PAGE_TAG, 'POST', ['subscribe' => 'day']));

        $this->assertNull($this->subscriptions->periodOf(self::PAGE_TAG, self::ANN));
        $this->assertStringContainsString('alert-danger', $output);
    }

    public function testThePeriodButtonsArePostButtonsCarryingAToken()
    {
        $output = $this->runAction(Request::create('/?' . self::PAGE_TAG));

        $this->assertStringNotContainsString('subscribe=', $output);
        $this->assertMatchesRegularExpression('/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/', $output);
        $this->assertMatchesRegularExpression('/<button type="submit" name="subscribe" value="day"/', $output);
    }
}

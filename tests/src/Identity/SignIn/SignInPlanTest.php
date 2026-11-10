<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\SignInService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class SignInPlanTest extends YesWikiTestCase
{
    private const TAGS = ['SsoPlanLocal', 'SsoPlanHere', 'SsoPlanOther', 'SsoPlanOld', 'SsoPlanTaken', 'SsoPlanGroups', 'jdupont', 'jmartin', 'nomail', 'CaseUser'];
    private const GROUPS = ['Editors', 'Hand', 'Readers', 'A', 'B'];

    private $services;
    private UserManager $users;
    private AccountLinks $links;
    private SignInService $service;
    /** @var list<string> groups this test created */
    private array $createdGroups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
        $this->users = $this->services->get(UserManager::class);
        $this->links = $this->services->get(AccountLinks::class);
        $this->service = $this->services->get(SignInService::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        self::actAs(null);
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $groups = $this->services->get(GroupManager::class);
        foreach (self::GROUPS as $group) {
            if (in_array($group, $this->createdGroups, true) && $groups->groupExists($group)) {
                $groups->delete($group);
            }
        }
        $this->createdGroups = [];
        foreach (self::TAGS as $tag) {
            $user = $this->users->getOneByName($tag);
            if ($user) {
                $this->users->delete($user);
            }
            $this->services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function makeUser(string $tag, string $email): User
    {
        return $this->users->create($tag, $email, 'Aa1!aaaaRegression');
    }

    private function config(array $settings = []): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', array_merge(['type' => 'oidc'], $settings), [
            'id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups',
        ]);
    }

    private function prepareFixture(string $fixture): void
    {
        match ($fixture) {
            'none' => null,
            'local' => $this->makeUser('SsoPlanLocal', 'local@x'),
            'linked-here-s' => $this->links->link($this->makeUser('SsoPlanHere', 'a@x'), 'acme', 's'),
            'linked-other' => $this->links->link($this->makeUser('SsoPlanOther', 'other@x'), 'other', 'z'),
            'linked-here-old' => $this->links->link($this->makeUser('SsoPlanOld', 'here@x'), 'acme', 'old'),
        };
    }

    /**
     * @param list<string>                         $memberOf the account's groups before the sign-in
     * @param list<array{provider: string, wiki: string}> $mapping
     * @param list<string>                         $sent     the groups the provider sends
     */
    private function planWithGroups(array $memberOf, array $mapping, array $sent): \YesWiki\Identity\SignIn\SignInPlan
    {
        $groups = $this->services->get(GroupManager::class);
        $user = $this->makeUser('SsoPlanGroups', 'groups@x');
        $this->links->link($user, 'acme', 's');
        foreach ($memberOf as $group) {
            $groups->create($group, ['SsoPlanGroups']);
            $this->createdGroups[] = $group;
        }

        return $this->service->plan(
            $this->config(['group_mapping' => $mapping]),
            new ProviderIdentity(['sub' => 's', 'email' => 'groups@x', 'groups' => $sent])
        );
    }

    private function countUserRevisions(): int
    {
        $db = $this->services->get(DbService::class);
        $row = $db->loadSingle("SELECT COUNT(*) AS total FROM {$db->prefixTable('pages')} WHERE {$db->quoteIdentifier('type')} = 'user'");

        return (int)$row['total'];
    }

    public static function branches(): array
    {
        return [
            'no user id'               => [[],                                  ['email' => 'a@x'],                'none',              PlanAction::Refuse, FailureReason::MissingAttribute],
            'no email'                 => [[],                                  ['sub' => 's'],                    'none',              PlanAction::Refuse, FailureReason::MissingAttribute],
            'linked here, provider stopped sending email' => [[], ['sub' => 's'], 'linked-here-s', PlanAction::Refuse, FailureReason::MissingAttribute],
            'linked here'              => [[],                                  ['sub' => 's', 'email' => 'a@x'],  'linked-here-s',     PlanAction::UseLinked, null],
            'unknown, creation on'     => [[],                                  ['sub' => 's', 'email' => 'new@x'], 'none',             PlanAction::Create, null],
            'unknown, creation off'    => [['create_accounts' => false],        ['sub' => 's', 'email' => 'new@x'], 'none',             PlanAction::Refuse, FailureReason::NoAccount],
            'email taken, matching off' => [[],                                 ['sub' => 's', 'email' => 'local@x'], 'local',          PlanAction::Refuse, FailureReason::EmailConflict],
            'email taken, matching on' => [['match_by_email' => true],          ['sub' => 's', 'email' => 'local@x'], 'local',          PlanAction::LinkExisting, null],
            'unverified email'         => [['match_by_email' => true],          ['sub' => 's', 'email' => 'local@x', 'email_verified' => false], 'local', PlanAction::Refuse, FailureReason::EmailConflict],
            'unverified email, string false' => [['match_by_email' => true],    ['sub' => 's', 'email' => 'local@x', 'email_verified' => 'false'], 'local', PlanAction::Refuse, FailureReason::EmailConflict],
            'unverified email, string 0' => [['match_by_email' => true],        ['sub' => 's', 'email' => 'local@x', 'email_verified' => '0'], 'local', PlanAction::Refuse, FailureReason::EmailConflict],
            'verified email'           => [['match_by_email' => true],          ['sub' => 's', 'email' => 'local@x', 'email_verified' => true], 'local', PlanAction::LinkExisting, null],
            'tied to another provider' => [['match_by_email' => true],          ['sub' => 's', 'email' => 'other@x'], 'linked-other',   PlanAction::Refuse, FailureReason::LinkedElsewhere],
            'new server, same provider' => [['match_by_email' => true],         ['sub' => 'new-id', 'email' => 'here@x'], 'linked-here-old', PlanAction::ReplaceSubject, null],
            'new server, matching off' => [[],                                  ['sub' => 'new-id', 'email' => 'here@x'], 'linked-here-old', PlanAction::Refuse, FailureReason::EmailConflict],
        ];
    }

    #[DataProvider('branches')]
    public function testBranch(array $settings, array $attributes, string $fixture, PlanAction $action, ?FailureReason $reason): void
    {
        $this->prepareFixture($fixture);
        $plan = $this->service->plan($this->config($settings), new ProviderIdentity($attributes));
        $this->assertSame($action, $plan->action);
        $this->assertSame($reason, $plan->refusal);
    }

    public function testMissingAttributeNamesWhatWasExpectedAndWhatCame(): void
    {
        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 's', 'nickname' => 'n']));
        $this->assertStringContainsString('email', $plan->refusalDetail);
        $this->assertStringContainsString('nickname', $plan->refusalDetail);
    }

    public function testNameMatchOnlyForAnUpgradedProviderBeforeItsDate(): void
    {
        $this->makeUser('jdupont', 'jd@x');
        $on = $this->config(['match_by_name_until' => '2099-01-01']);
        $this->assertSame(PlanAction::LinkExisting, $this->service->plan($on, new ProviderIdentity(['sub' => 'jdupont', 'email' => 'other@y']))->action);
        $past = $this->config(['match_by_name_until' => '2000-01-01']);
        $this->assertSame(PlanAction::Create, $this->service->plan($past, new ProviderIdentity(['sub' => 'jdupont', 'email' => 'other@y']))->action);
        $this->assertSame(PlanAction::Create, $this->service->plan($this->config(), new ProviderIdentity(['sub' => 'jdupont', 'email' => 'other@y']))->action);
    }

    public function testNameMatchNeverTakesALinkedAccount(): void
    {
        $this->links->link($this->makeUser('jmartin', 'jm@x'), 'other', 'z');
        $on = $this->config(['match_by_name_until' => '2099-01-01']);
        $plan = $this->service->plan($on, new ProviderIdentity(['sub' => 'jmartin', 'email' => 'new@y']));
        $this->assertNotSame('jmartin', $plan->accountTag);
    }

    public function testUpgradedDirectoryMaySignInWithoutEmailOnlyWithAnExistingAccount(): void
    {
        $this->makeUser('nomail', 'stored@x');
        $on = $this->config(['match_by_name_until' => '2099-01-01']);
        $found = $this->service->plan($on, new ProviderIdentity(['sub' => 'nomail']));
        $this->assertSame(PlanAction::LinkExisting, $found->action);
        $this->assertArrayNotHasKey('email', $found->profile);
        $this->assertNotEmpty($found->warnings);
        $this->assertSame('email_missing_upgraded', $found->warnings[0]['kind']);
        $unknown = $this->service->plan($on, new ProviderIdentity(['sub' => 'nobody']));
        $this->assertSame(FailureReason::MissingAttribute, $unknown->refusal);
    }

    public function testGroupsAreComputedFromTheMappingOnly(): void
    {
        $plan = $this->planWithGroups(['Editors', 'Hand'], [['provider' => 'wiki-editors', 'wiki' => 'Editors'], ['provider' => 'wiki-readers', 'wiki' => 'Readers']], ['wiki-readers', 'staff']);
        $this->assertSame(['Readers'], $plan->groupsToAdd);
        $this->assertSame(['Editors'], $plan->groupsToRemove);
        $this->assertSame(['staff'], $plan->groupsIgnored);
    }

    public function testOneProviderGroupCanFeedSeveralWikiGroups(): void
    {
        $plan = $this->planWithGroups([], [['provider' => 'staff', 'wiki' => 'A'], ['provider' => 'staff', 'wiki' => 'B']], ['staff']);
        $this->assertSame(['A', 'B'], $plan->groupsToAdd);
    }

    public function testAnEmailUsedElsewhereKeepsTheOldOneAndWarns(): void
    {
        $this->links->link($this->makeUser('SsoPlanHere', 'a@x'), 'acme', 's');
        $this->makeUser('SsoPlanTaken', 'taken@x');
        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 's', 'email' => 'taken@x', 'given_name' => 'Ann']));
        $this->assertSame(PlanAction::UseLinked, $plan->action);
        $this->assertSame('SsoPlanHere', $plan->accountTag);
        $this->assertArrayNotHasKey('email', $plan->profile);
        $this->assertSame('Ann', $plan->profile['first_name']);
        $this->assertSame('email_collision', $plan->warnings[0]['kind']);
        $this->assertNotSame('', $plan->warnings[0]['message']);
    }

    public function testTheLastAdminIsNotRemovedFromAdminsAndWarns(): void
    {
        $groups = $this->services->get(GroupManager::class);
        $original = $groups->getMembers('admins');
        $this->links->link($this->makeUser('SsoPlanGroups', 'groups@x'), 'acme', 's');
        try {
            $groups->updateMembers('admins', ['SsoPlanGroups']);
            $plan = $this->service->plan(
                $this->config(['group_mapping' => [['provider' => 'boss', 'wiki' => 'admins']]]),
                new ProviderIdentity(['sub' => 's', 'email' => 'groups@x', 'groups' => ['other']])
            );
            $this->assertNotContains('admins', $plan->groupsToRemove);
            $this->assertSame('last_admin', $plan->warnings[0]['kind']);
        } finally {
            $groups->updateMembers('admins', $original);
        }
    }

    public function testANewEmailNobodyUsesIsAccepted(): void
    {
        $this->links->link($this->makeUser('SsoPlanHere', 'a@x'), 'acme', 's');
        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 's', 'email' => 'fresh@x']));
        $this->assertSame('fresh@x', $plan->profile['email']);
        $this->assertSame([], $plan->warnings);
    }

    public function testPlanningWritesNothing(): void
    {
        $this->prepareFixture('local');
        $before = $this->countUserRevisions();
        $plan = $this->service->plan($this->config(['match_by_email' => true]), new ProviderIdentity(['sub' => 's', 'email' => 'local@x']));
        $this->assertSame(PlanAction::LinkExisting, $plan->action);
        $this->assertSame($before, $this->countUserRevisions());
        $this->assertNull($this->users->getOneByName('SsoPlanLocal')->provider());
        $this->assertNull($this->links->find('acme', 's'));
    }

    public function testAnEmailDifferingByCaseIsTheSameAccount(): void
    {
        $this->makeUser('CaseUser', 'marie.dupont@acme.example');
        $plan = $this->service->plan($this->config(['match_by_email' => true]), new ProviderIdentity(['sub' => 's', 'email' => 'Marie.Dupont@ACME.example']));
        $this->assertSame(PlanAction::LinkExisting, $plan->action);
        $this->assertSame('CaseUser', $plan->accountTag);
    }

    private function localConfig(): ProviderConfig
    {
        return ProviderConfig::fromArray('local', ['type' => 'local']);
    }

    public function testTheLocalTypeNamesTheAccountAndChangesNothing(): void
    {
        $this->prepareFixture('local');
        $before = $this->countUserRevisions();

        $plan = $this->service->plan($this->localConfig(), new ProviderIdentity(['local_account' => 'SsoPlanLocal']));
        $applied = $this->service->apply($plan);

        $this->assertSame(PlanAction::UseLocal, $plan->action);
        $this->assertSame('SsoPlanLocal', $plan->accountTag);
        $this->assertSame('SsoPlanLocal', $applied->user->getName());
        $this->assertSame($before, $this->countUserRevisions(), 'a local sign-in writes nothing');
    }

    public function testTheLocalTypeWithoutAnAccountIsRefused(): void
    {
        $plan = $this->service->plan($this->localConfig(), new ProviderIdentity([]));

        $this->assertSame(PlanAction::Refuse, $plan->action);
        $this->assertSame(FailureReason::MissingAttribute, $plan->refusal);
    }

    public function testAnotherTypeCannotClaimAnAccountByTheLocalAttribute(): void
    {
        $this->prepareFixture('local');

        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 's', 'email' => 'new@x', 'local_account' => 'SsoPlanLocal']));
        $this->assertNotSame(PlanAction::UseLocal, $plan->action);
        $this->assertNotSame('SsoPlanLocal', $plan->accountTag, 'the attribute is just an attribute for a provider that is not local');

        $alone = $this->service->plan($this->config(), new ProviderIdentity(['local_account' => 'SsoPlanLocal']));
        $this->assertSame(PlanAction::Refuse, $alone->action);
        $this->assertNull($alone->accountTag);
    }

    public function testALocalPlanCannotTakeAnAccountThatBelongsToAProvider(): void
    {
        $this->links->link($this->makeUser('SsoPlanHere', 'a@x'), 'acme', 's');
        $plan = $this->service->plan($this->localConfig(), new ProviderIdentity(['local_account' => 'SsoPlanHere']));

        $this->expectException(\YesWiki\Identity\SignIn\Contract\ProviderFailure::class);
        $this->service->apply($plan);
    }
}

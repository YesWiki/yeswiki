<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\AccountNamer;
use YesWiki\Identity\Service\SignIn\GroupSync;
use YesWiki\Identity\Service\SignIn\ProfileSync;
use YesWiki\Identity\Service\SignIn\SignInService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\AppliedSignIn;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Lock\Locks;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class SignInApplyTest extends YesWikiTestCase
{
    private const EMAILS = ['apply1@x', 'apply2@x', 'apply3@x', 'apply3b@x', 'apply4@x', 'apply5@x', 'apply6@x', 'apply7@x', 'apply8@x', 'old@x', 'hibnew@x', 'winner@x', 'racer@x', 'hibfresh@x'];
    private const TAGS = ['SsoApplyAcct', 'Hib', 'winner-account'];
    private const GROUPS = ['Editors', 'Hand', 'Readers', 'SsoNewGroup'];

    private $services;
    private UserManager $users;
    private AccountLinks $links;
    private GroupManager $groups;
    private SignInService $service;
    /** @var list<string> */
    private array $createdGroups = [];
    private array $adminsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
        $this->users = $this->services->get(UserManager::class);
        $this->links = $this->services->get(AccountLinks::class);
        $this->groups = $this->services->get(GroupManager::class);
        $this->service = $this->services->get(SignInService::class);
        $this->adminsBefore = $this->groups->getMembers('admins');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        self::actAs(null);
        $this->groups->updateMembers('admins', $this->adminsBefore);
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach (self::GROUPS as $group) {
            if ($this->groups->groupExists($group) && (in_array($group, $this->createdGroups, true) || $group === 'SsoNewGroup')) {
                $this->groups->delete($group);
            }
        }
        $this->createdGroups = [];
        $tags = self::TAGS;
        foreach (self::EMAILS as $email) {
            $user = $this->users->getOneByEmail($email);
            if ($user) {
                $tags[] = $user->getName();
            }
        }
        foreach (array_unique($tags) as $tag) {
            $user = $this->users->getOneByName($tag);
            if ($user) {
                $this->users->delete($user);
            }
            $this->services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function config(array $settings = []): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', array_merge(['type' => 'oidc'], $settings), [
            'id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups',
        ]);
    }

    private function applied(array $attrs, array $settings = [], ?SignInService $service = null): AppliedSignIn
    {
        $config = $this->config($settings);
        $plan = ($service ?? $this->service)->plan($config, new ProviderIdentity($attrs));

        return ($service ?? $this->service)->apply($plan);
    }

    private function apply(array $attrs, array $settings = []): User
    {
        return $this->applied($attrs, $settings)->user;
    }

    private function serviceWith(?Locks $locks, ?HibernationService $hibernation): SignInService
    {
        return new SignInService(
            $this->links,
            $this->users,
            $this->groups,
            $this->services->get(AccountNamer::class),
            $this->services->get(ProfileSync::class),
            $this->services->get(GroupSync::class),
            $locks ?? $this->services->get(Locks::class),
            $hibernation ?? $this->services->get(HibernationService::class)
        );
    }

    private function revisionsOf(string $email): int
    {
        $tag = $this->users->getOneByEmail($email)->getName();
        $db = $this->services->get(DbService::class);
        $row = $db->loadSingle("SELECT COUNT(*) AS total FROM {$db->prefixTable('pages')} WHERE tag = ?", [$tag]);

        return (int)$row['total'];
    }

    private function bodyOf(User $user): array
    {
        return $this->services->get(PageManager::class)->getOne($user->getName(), null, true, true)['body'];
    }

    private function newGroup(string $name, array $members): void
    {
        $this->groups->create($name, $members);
        $this->createdGroups[] = $name;
    }

    private function refusedPlan(FailureReason $reason): SignInPlan
    {
        return new SignInPlan('acme', 's', PlanAction::Refuse, null, $reason, 'detail', [], [], [], [], [], [], [], 'who');
    }

    public function testCreatesAndLinks(): void
    {
        $user = $this->apply(['sub' => 'a1', 'email' => 'apply1@x', 'given_name' => 'Ann', 'family_name' => 'Lee']);
        $this->assertSame('apply1@x', $user->getEmail());
        $this->assertSame($user->getName(), $this->links->find('acme', 'a1')?->getName());
        $this->assertSame('Ann', $this->bodyOf($user)['first_name']);
    }

    public function testAnUnchangedSignInWritesNoRevision(): void
    {
        $this->apply(['sub' => 'a2', 'email' => 'apply2@x', 'given_name' => 'Ann']);
        $before = $this->revisionsOf('apply2@x');
        $this->apply(['sub' => 'a2', 'email' => 'apply2@x', 'given_name' => 'Ann']);
        $this->assertSame($before, $this->revisionsOf('apply2@x'));
    }

    public function testTheProviderWinsOnAChange(): void
    {
        $this->apply(['sub' => 'a3', 'email' => 'apply3@x', 'given_name' => 'Ann']);
        $user = $this->apply(['sub' => 'a3', 'email' => 'apply3b@x', 'given_name' => 'Anna']);
        $this->assertSame('apply3b@x', $user->getEmail());
        $this->assertSame('Anna', $this->bodyOf($user)['first_name']);
    }

    public function testExtraAttributesFillAccountFields(): void
    {
        $user = $this->apply(
            ['sub' => 'a4', 'email' => 'apply4@x', 'department' => 'Marketing'],
            ['extra_fields' => ['motto' => 'department']]
        );
        $this->assertSame('Marketing', $this->bodyOf($user)['motto']);
        $again = $this->apply(
            ['sub' => 'a4', 'email' => 'apply4@x', 'department' => 'Sales'],
            ['extra_fields' => ['motto' => 'department']]
        );
        $this->assertSame('Sales', $this->bodyOf($again)['motto']);
    }

    public function testGroupsMoveAndOthersStay(): void
    {
        $this->newGroup('Editors', ['SsoApplyAcct']);
        $this->newGroup('Hand', ['SsoApplyAcct']);
        $this->newGroup('Readers', ['someone-else']);
        $this->apply(['sub' => 'a5', 'email' => 'apply5@x']);
        $tag = $this->links->find('acme', 'a5')->getName();
        $this->groups->addMembers('Editors', [$tag]);
        $this->groups->addMembers('Hand', [$tag]);
        $user = $this->apply(['sub' => 'a5', 'email' => 'apply5@x', 'groups' => ['wiki-readers']], [
            'group_mapping' => [['provider' => 'wiki-editors', 'wiki' => 'Editors'], ['provider' => 'wiki-readers', 'wiki' => 'Readers']],
        ]);
        $this->assertContains($user->getName(), $this->groups->getMembers('Readers'));
        $this->assertNotContains($user->getName(), $this->groups->getMembers('Editors'));
        $this->assertContains($user->getName(), $this->groups->getMembers('Hand'));
    }

    public function testAMissingWikiGroupIsCreated(): void
    {
        $user = $this->apply(['sub' => 'a6', 'email' => 'apply6@x', 'groups' => ['new']], [
            'group_mapping' => [['provider' => 'new', 'wiki' => 'SsoNewGroup']],
        ]);
        $this->assertTrue($this->groups->groupExists('SsoNewGroup'));
        $this->assertContains($user->getName(), $this->groups->getMembers('SsoNewGroup'));
    }

    public function testAdminsIsNeverLeftEmpty(): void
    {
        $user = $this->apply(['sub' => 'a7', 'email' => 'apply7@x']);
        $this->groups->updateMembers('admins', [$user->getName()]);
        $mapping = ['group_mapping' => [['provider' => 'boss', 'wiki' => 'admins']]];

        // through the plan: it keeps admins out of the removals and says so, once
        $applied = $this->applied(['sub' => 'a7', 'email' => 'apply7@x', 'groups' => ['other']], $mapping);
        $this->assertContains($user->getName(), $this->groups->getMembers('admins'));
        $this->assertSame(['last_admin'], array_column($applied->warnings, 'kind'));

        // a stale plan that still asks for the removal meets the guard at apply time
        $warnings = $this->services->get(GroupSync::class)->apply($user, [], ['admins']);
        $this->assertContains($user->getName(), $this->groups->getMembers('admins'));
        $this->assertSame(['last_admin'], array_column($warnings, 'kind'));

        // with another member left, the removal goes through
        $this->groups->updateMembers('admins', [$user->getName(), 'SomeoneElse']);
        $this->assertSame([], $this->services->get(GroupSync::class)->apply($user, [], ['admins']));
        $this->assertNotContains($user->getName(), $this->groups->getMembers('admins'));
    }

    public function testAStalePlanDoesNotDuplicateTheLastAdminWarning(): void
    {
        $user = $this->apply(['sub' => 'a7', 'email' => 'apply7@x']);
        $this->groups->updateMembers('admins', [$user->getName()]);
        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 'a7', 'email' => 'apply7@x']));
        $stale = new SignInPlan($plan->providerId, $plan->subject, $plan->action, $plan->accountTag, null, '', [], [], ['admins'], [], [
            ['kind' => 'last_admin', 'message' => 'from the plan'],
        ], [], [], $plan->who);
        $applied = $this->service->apply($stale);
        $this->assertSame(['last_admin'], array_column($applied->warnings, 'kind'));
        $this->assertContains($user->getName(), $this->groups->getMembers('admins'));
    }

    public function testAFailedGroupUpdateWarnsAndDoesNotBlock(): void
    {
        $user = $this->apply(['sub' => 'a8', 'email' => 'apply8@x']);
        $groups = new class($this->groups) extends GroupManager {
            public function __construct(private GroupManager $real)
            {
            }

            public function groupExists(string $group_name): bool
            {
                throw new \RuntimeException('boom');
            }
        };
        $sync = new GroupSync($groups);
        $warnings = $sync->apply($user, ['SsoNewGroup'], []);
        $this->assertCount(1, $warnings);
        $this->assertSame('group_failed', $warnings[0]['kind']);
        $this->assertStringContainsString('SsoNewGroup', $warnings[0]['message']);
    }

    public function testALostLockRetriesThenFindsTheLink(): void
    {
        $plan = $this->service->plan($this->config(), new ProviderIdentity(['sub' => 'race', 'email' => 'racer@x']));
        $this->assertSame(PlanAction::Create, $plan->action);
        $self = $this;
        $locks = new class($self) implements Locks {
            public int $tries = 0;
            public array $released = [];

            public function __construct(private SignInApplyTest $test)
            {
            }

            public function tryAcquire(string $name, bool &$heldElsewhere = false): bool
            {
                $heldElsewhere = true;
                if (++$this->tries === 3) {
                    $this->test->winnerSignsIn();
                }

                return false;
            }

            public function release(string $name): void
            {
                $this->released[] = $name;
            }

            public function probe(string $name): ?bool
            {
                return null;
            }

            public function forget(string $name): void
            {
            }
        };
        $applied = $this->serviceWith($locks, null)->apply($plan);
        $this->assertSame('winner-account', $applied->user->getName());
        $this->assertSame(3, $locks->tries);
        $this->assertSame('winner-account', $this->users->getOneByEmail('racer@x')->getName());
    }

    /** Called by the lock fake: another request finishes the first sign-in. */
    public function winnerSignsIn(): void
    {
        $this->links->link($this->users->create('winner-account', 'winner@x', 'Aa1!aaaaRegression'), 'acme', 'race');
    }

    public function testTheLockIsTakenForACreationAndReleased(): void
    {
        $locks = new class implements Locks {
            public array $taken = [];
            public array $released = [];

            public function tryAcquire(string $name, bool &$heldElsewhere = false): bool
            {
                $this->taken[] = $name;

                return true;
            }

            public function release(string $name): void
            {
                $this->released[] = $name;
            }

            public function probe(string $name): ?bool
            {
                return false;
            }

            public function forget(string $name): void
            {
            }
        };
        $service = $this->serviceWith($locks, null);
        $this->applied(['sub' => 'lk1', 'email' => 'apply1@x'], [], $service);
        $this->assertSame(['sign-in:acme:' . hash('sha256', 'lk1')], $locks->taken);
        $this->assertSame($locks->taken, $locks->released);
        // a known account needs no lock
        $this->applied(['sub' => 'lk1', 'email' => 'apply1@x'], [], $service);
        $this->assertCount(1, $locks->taken);
    }

    private function hibernation(bool $on): HibernationService
    {
        return new class($on) extends HibernationService {
            public function __construct(private bool $on)
            {
            }

            public function isWikiHibernated(): bool
            {
                return $this->on;
            }
        };
    }

    public function testHibernationSignsAKnownAccountWithoutWriting(): void
    {
        $this->links->link($this->users->create('Hib', 'old@x', 'Aa1!aaaaRegression'), 'acme', 'hib');
        $before = $this->revisionsOf('old@x');
        $service = $this->serviceWith(null, $this->hibernation(true));
        $applied = $this->applied(['sub' => 'hib', 'email' => 'hibnew@x', 'given_name' => 'Changed'], [], $service);
        $this->assertSame('Hib', $applied->user->getName());
        $this->assertSame('old@x', $this->users->getOneByName('Hib')->getEmail());
        $this->assertSame($before, $this->revisionsOf('old@x'));
    }

    public function testHibernationRefusesANewAccount(): void
    {
        $service = $this->serviceWith(null, $this->hibernation(true));
        try {
            $this->applied(['sub' => 'hibfresh', 'email' => 'hibfresh@x'], [], $service);
            $this->fail('expected a refusal');
        } catch (ProviderFailure $f) {
            $this->assertSame(FailureReason::AccountCreationFailed, $f->reason);
        }
        $this->assertNull($this->users->getOneByEmail('hibfresh@x'));
    }

    public function testRefusalThrowsItsReason(): void
    {
        try {
            $this->service->apply($this->refusedPlan(FailureReason::NoAccount));
            $this->fail('expected a refusal');
        } catch (ProviderFailure $f) {
            $this->assertSame(FailureReason::NoAccount, $f->reason);
        }
    }
}

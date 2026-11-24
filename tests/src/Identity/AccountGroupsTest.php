<?php

namespace YesWiki\Test\Identity;

use YesWiki\Content\Controller\FormController;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AccountGroups;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The User form's `account_groups` puts an account in groups when it is created and every time it is edited. */
class AccountGroupsTest extends YesWikiTestCase
{
    private string $suffix;

    /** @var array<string, mixed>|null */
    private ?array $userForm = null;

    /** @var list<string> */
    private array $userNames = [];

    /** @var list<string> */
    private array $groupNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = bin2hex(random_bytes(4));
        $this->userForm = $this->getWiki()->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($this->userForm, 'the User form should exist -- run ./yeswicli migrate');
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $groupManager = $services->get(GroupManager::class);
        foreach ($this->groupNames as $group) {
            if ($groupManager->groupExists($group)) {
                $groupManager->delete($group);
            }
        }
        $userManager = $services->get(UserManager::class);
        foreach ($this->userNames as $name) {
            $user = $userManager->getOneByName($name);
            if ($user) {
                $userManager->delete($user);
            }
            $services->get(PageManager::class)->deleteOrphaned($name);
        }
        if ($this->userForm !== null) {
            $services->get(FormManager::class)->update([
                'id' => $this->userForm['id'],
                'label' => $this->userForm['label'],
                'template' => $this->userForm['template'],
                AccountGroups::FORM_PROPERTY => '',
            ]);
        }
        parent::tearDown();
    }

    /** @param list<array<string, string>> $extraFields */
    private function configureUserForm(string $accountGroups, array $extraFields = []): void
    {
        $this->assertNotNull($this->userForm);
        $this->getWiki()->services->get(FormManager::class)->update([
            'id' => $this->userForm['id'],
            'label' => $this->userForm['label'],
            'template' => array_merge($this->userForm['template'], $extraFields),
            AccountGroups::FORM_PROPERTY => $accountGroups,
        ]);
    }

    private function group(string $label, bool $create = true): string
    {
        $name = 'AccountGroups' . $label . $this->suffix;
        $this->groupNames[] = $name;
        if ($create) {
            $this->getWiki()->services->get(GroupManager::class)->create($name, []);
        }

        return $name;
    }

    private function createAccount(): string
    {
        $name = 'AccountGroupsUser' . $this->suffix;
        $this->userNames[] = $name;
        $this->getWiki()->services->get(UserManager::class)->create($name, strtolower($name) . '@example.tld', 'Aa1!aaaaAccountGroups');

        return $name;
    }

    private function isMember(string $group, string $name): bool
    {
        return $this->getWiki()->services->get(UserManager::class)->isInGroup($group, $name, false);
    }

    public function testTheUserFormKeepsTheProperty(): void
    {
        $this->configureUserForm('@membres');

        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertSame('@membres', $form[AccountGroups::FORM_PROPERTY] ?? null);

        $stripped = ContentTypeSchema::stripInapplicableProperties([AccountGroups::FORM_PROPERTY => '@membres'], ContentTypeSchema::TYPE_ENTRY);
        $this->assertArrayNotHasKey(AccountGroups::FORM_PROPERTY, $stripped, 'only the User form has accounts to put in groups');
    }

    public function testOnlyTheUserFormDesignerOffersTheGroups(): void
    {
        $services = $this->getWiki()->services;
        $aclService = $services->get(AclService::class);
        $admin = current(array_filter($services->get(UserManager::class)->getAll(), fn ($user) => $aclService->isAdmin($user['name'])));
        $this->assertNotFalse($admin, 'need an existing admin to render the designer');
        $pageForm = $services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $this->assertNotNull($pageForm);
        $this->assertNotNull($this->userForm);

        $services->get(AuthenticationService::class)->login($admin);
        try {
            $userDesigner = $services->get(FormController::class)->update($this->userForm['id']);
            $pageDesigner = $services->get(FormController::class)->update($pageForm['id']);
        } finally {
            $services->get(AuthenticationService::class)->logout();
        }

        $this->assertStringContainsString('name="account_groups"', $userDesigner);
        $this->assertStringNotContainsString('name="account_groups"', $pageDesigner);
    }

    public function testANewAccountJoinsTheGroupsTheFormNames(): void
    {
        $existing = $this->group('Existing');
        $created = $this->group('Created', false);
        $missing = $this->group('Missing', false);
        $this->configureUserForm("@{$existing}, +@{$created}, @{$missing}");

        $name = $this->createAccount();

        $this->assertTrue($this->isMember($existing, $name));
        $this->assertTrue($this->isMember($created, $name), 'a + creates the group it names');
        $this->assertFalse(
            $this->getWiki()->services->get(GroupManager::class)->groupExists($missing),
            'without a +, a group that does not exist is not created'
        );
    }

    public function testEditingTheAccountAddsTheGroupsItsFieldNamesAndNeverRemovesOne(): void
    {
        $first = $this->group('First');
        $second = $this->group('Second');
        $this->configureUserForm('bf_groupes', [['type' => 'texte', 'name' => 'bf_groupes', 'label' => 'Groupes']]);
        $name = $this->createAccount();
        $userManager = $this->getWiki()->services->get(UserManager::class);

        $user = $userManager->getOneByName($name);
        $this->assertNotNull($user);
        $userManager->update($user, ['bf_groupes' => $first]);
        $this->assertTrue($this->isMember($first, $name));

        $user = $userManager->getOneByName($name);
        $this->assertNotNull($user);
        $userManager->update($user, ['bf_groupes' => $second]);
        $this->assertTrue($this->isMember($second, $name));
        $this->assertTrue($this->isMember($first, $name), 'the account stays in a group its field no longer names');
    }

    public function testTheFormCannotMakeAnAccountAnAdmin(): void
    {
        $this->assertFalse($this->getWiki()->services->get(AclService::class)->isAdmin(), 'nobody is signed in');
        $this->configureUserForm('@admins');

        $name = $this->createAccount();

        $this->assertFalse($this->isMember('admins', $name));
    }
}

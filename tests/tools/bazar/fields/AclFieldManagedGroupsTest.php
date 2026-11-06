<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The acls field keeps groups in step with the people listed on an entry and derives rights from linked entries.
 */
class AclFieldManagedGroupsTest extends YesWikiTestCase
{
    private const ALICE = 'AclManagedAlice';
    private const BOB = 'AclManagedBob';

    private $wiki;
    private $entries;
    private $forms;
    private $groups;
    private $acl;
    private $users;
    private string $peopleForm;
    private string $orgForm;
    private string $memberForm;
    private array $tags = [];
    private string $aliceEntry;
    private string $bobEntry;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entries = $this->wiki->services->get(EntryManager::class);
        $this->forms = $this->wiki->services->get(FormManager::class);
        $this->groups = $this->wiki->services->get(GroupManager::class);
        $this->acl = $this->wiki->services->get(AclService::class);
        $this->users = $this->wiki->services->get(UserManager::class);
        foreach ([self::ALICE, self::BOB] as $name) {
            if (!$this->users->getOneByName($name)) {
                $this->users->create($name, strtolower($name) . '@example.org', 'Secret-Pass-123');
            }
        }
        $this->peopleForm = $this->forms->create(['bn_label_nature' => 'Managed groups people', 'bn_condition' => '', 'bn_template' => implode("\n", [
            'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'texte***nomwiki***Compte*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***',
        ])]);
        $this->orgForm = $this->forms->create(['bn_label_nature' => 'Managed groups organisations', 'bn_condition' => '', 'bn_template' => implode("\n", [
            'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
            "checkboxfiche***{$this->peopleForm}***Contributeurs*** *** *** ***bf_contributeur*** ***0*** *** *** * *** * *** *** *** ***",
            "checkboxfiche***{$this->peopleForm}***Admins*** *** *** ***bf_admin_structure*** ***0*** *** *** * *** * *** *** *** ***",
            'acls*** * ***%***comments-closed*** ***non***bf_acls***0*** *** *** *** *** ***admin=bf_admin_structure,contributeurs=bf_contributeur*** ***@admin{id_fiche}*** ***',
        ])]);
        $this->memberForm = $this->forms->create(['bn_label_nature' => 'Managed groups memberships', 'bn_condition' => '', 'bn_template' => implode("\n", [
            'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
            "listefiche***{$this->orgForm}***Organisation*** *** *** ***bf_adherent*** ***0*** *** *** * *** * *** *** *** ***",
            'acls***%***%***comments-closed*** ***non***bf_acls***0*** *** *** *** *** *** ***@contributeurs{bf_adherent},@admin{bf_adherent}*** *** ***',
        ])]);
        $this->aliceEntry = $this->create($this->peopleForm, ['bf_titre' => 'Managed Alice', 'nomwiki' => self::ALICE])['id_fiche'];
        $this->bobEntry = $this->create($this->peopleForm, ['bf_titre' => 'Managed Bob', 'nomwiki' => self::BOB])['id_fiche'];
        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthController::class)->logout();
        foreach (array_reverse($this->tags) as $tag) {
            if ($this->entries->getOne($tag, false, null, false, true)) {
                $this->entries->delete($tag, true);
            }
            $this->acl->delete($tag);
        }
        foreach ([$this->memberForm, $this->orgForm, $this->peopleForm] as $form) {
            $this->forms->delete($form);
        }
        foreach ([self::ALICE, self::BOB] as $name) {
            $user = $this->users->getOneByName($name);
            if ($user) {
                $this->users->delete($user);
            }
        }
    }

    private function login(string $name): void
    {
        $this->wiki->services->get(AuthController::class)->login($this->users->getOneByName($name));
    }

    private function create(string $form, array $data): array
    {
        $entry = $this->entries->create($form, array_merge(['antispam' => 1], $data));
        $this->tags[] = $entry['id_fiche'];

        return $entry;
    }

    private function organisation(string $title, array $admins = [], array $contributors = []): array
    {
        return $this->create($this->orgForm, ['bf_titre' => $title, 'bf_admin_structure' => implode(',', $admins), 'bf_contributeur' => implode(',', $contributors)]);
    }

    private function update(array $entry, array $changes): array
    {
        return $this->entries->update($entry['id_fiche'], array_merge(['antispam' => 1], array_intersect_key($entry, array_flip(['bf_titre', 'bf_admin_structure', 'bf_contributeur', 'bf_adherent'])), $changes), false, true);
    }

    public function testPeopleListedOnAnEntryJoinItsGroups()
    {
        $tag = $this->organisation('Managed Org Join', [$this->aliceEntry], [$this->bobEntry])['id_fiche'];

        $this->assertSame([self::ALICE], $this->groups->getMembers("admin$tag"));
        $this->assertSame([self::BOB], $this->groups->getMembers("contributeurs$tag"));
    }

    public function testNoGroupIsCreatedForAnEntryWithoutPeople()
    {
        $tag = $this->organisation('Managed Org Empty')['id_fiche'];

        $this->assertFalse($this->groups->groupExists("admin$tag"));
    }

    public function testAGroupThatDisappearedIsRecreated()
    {
        $org = $this->organisation('Managed Org Gone', [$this->aliceEntry]);
        $this->groups->delete('admin' . $org['id_fiche']);

        $this->login(self::ALICE);
        $this->acl->save($org['id_fiche'], 'write', '*');
        $this->update($org, []);

        $this->assertSame([self::ALICE], $this->groups->getMembers('admin' . $org['id_fiche']));
    }

    public function testChangingThePeopleUpdatesTheGroups()
    {
        $org = $this->organisation('Managed Org Change', [$this->aliceEntry], []);

        $this->login(self::ALICE);
        $this->update($org, ['bf_admin_structure' => $this->bobEntry]);

        $this->assertSame([self::BOB], $this->groups->getMembers('admin' . $org['id_fiche']));
    }

    public function testAnEntryWithoutAccountIsLeftOutOfTheGroup()
    {
        $nobody = $this->create($this->peopleForm, ['bf_titre' => 'Managed Nobody'])['id_fiche'];

        $tag = $this->organisation('Managed Org Nobody', [$nobody, $this->aliceEntry])['id_fiche'];

        $this->assertSame([self::ALICE], $this->groups->getMembers("admin$tag"));
    }

    public function testTheAdminGroupGetsTheWriteRightOfItsEntry()
    {
        $tag = $this->organisation('Managed Org Write', [$this->aliceEntry])['id_fiche'];

        $this->assertSame("%\n@admin$tag", $this->acl->load($tag, 'write', false)['list']);
        $this->assertTrue($this->acl->check($this->acl->load($tag, 'write', false)['list'], self::ALICE, false, $tag));
        $this->assertFalse($this->acl->check($this->acl->load($tag, 'write', false)['list'], self::BOB, false, $tag));
    }

    public function testRightsFollowTheLinkedEntry()
    {
        $first = $this->organisation('Managed Org First')['id_fiche'];
        $second = $this->organisation('Managed Org Second')['id_fiche'];
        $member = $this->create($this->memberForm, ['bf_titre' => 'Managed Membership', 'bf_adherent' => $first]);

        $this->assertSame("%\n@contributeurs$first\n@admin$first", $this->acl->load($member['id_fiche'], 'read', false)['list']);

        $this->acl->save($member['id_fiche'], 'write', '*');
        $this->update($member, ['bf_adherent' => $second]);

        $this->assertSame("%\n@contributeurs$second\n@admin$second", $this->acl->load($member['id_fiche'], 'read', false)['list']);
    }

    public function testDeletingTheEntryRemovesItsGroups()
    {
        $tag = $this->organisation('Managed Org Delete', [$this->aliceEntry])['id_fiche'];

        $this->entries->delete($tag, true);

        $this->assertFalse($this->groups->groupExists("admin$tag"));
        $this->assertFalse($this->groups->groupExists("contributeurs$tag"));
    }

    public function testAnEntryCannotTakeOverAnExistingGroup()
    {
        $before = $this->groups->getMembers('admins');

        $this->create($this->orgForm, ['id_fiche' => 's', 'bf_titre' => 'S', 'bf_admin_structure' => $this->aliceEntry]);

        $this->assertSame($before, $this->groups->getMembers('admins'));
    }

    public function testAGroupOnlyOpensEntriesNamingExactlyThatGroup()
    {
        $tag = $this->organisation('Managed Org Exact', [], [$this->aliceEntry])['id_fiche'];
        $near = $this->organisation('Managed Org Exact2')['id_fiche'];
        $own = $this->create($this->memberForm, ['bf_titre' => 'Managed Own', 'bf_adherent' => $tag])['id_fiche'];
        $other = $this->create($this->memberForm, ['bf_titre' => 'Managed Near', 'bf_adherent' => $near])['id_fiche'];
        $this->acl->save($other, 'read', "@contributeurs{$tag}2");
        $this->login(self::ALICE);

        $found = array_column($this->entries->search(['formsIds' => [$this->memberForm]], true), 'id_fiche');

        $this->assertContains($own, $found);
        $this->assertNotContains($other, $found);
    }

    public function testNestedGroupsStillOpenEntries()
    {
        $tag = $this->organisation('Managed Org Nested')['id_fiche'];
        $this->groups->updateMembers("contributeurs$tag", ['@ManagedOuter']);
        $this->groups->create('ManagedOuter', [self::ALICE]);
        $own = $this->create($this->memberForm, ['bf_titre' => 'Managed Nested', 'bf_adherent' => $tag])['id_fiche'];
        $this->login(self::ALICE);

        $found = array_column($this->entries->search(['formsIds' => [$this->memberForm]], true), 'id_fiche');
        $this->wiki->services->get(AuthController::class)->logout();
        $this->groups->delete('ManagedOuter');

        $this->assertContains($own, $found);
    }
}

<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A linked-entry field with a group prefix keeps, for each entry, a group of the accounts behind the entries it holds. */
class EntryMembersGroupsTest extends YesWikiTestCase
{
    private const PEOPLE_FORM = '999961';
    private const TEAMS_FORM = '999962';

    /** @var list<string> */
    private array $accounts = [];

    /** @var list<string> */
    private array $tags = [];

    private string $admin = '';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $admin = $services->get(AuthenticationService::class)->connectFirstAdmin();
        if ($admin === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->admin = (string)$admin['name'];
        $forms = $services->get(FormManager::class);
        $forms->create([
            'id' => self::PEOPLE_FORM,
            'label' => 'Members groups people',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Nom']]),
            'condition' => '',
        ]);
        $forms->create([
            'id' => self::TEAMS_FORM,
            'label' => 'Members groups teams',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Équipe'],
                ['type' => 'checkboxfiche', 'linked_object' => self::PEOPLE_FORM, 'name' => 'bf_membres', 'label' => 'Membres', 'members_group' => 'Equipe', 'members_group_owner' => '1'],
            ]),
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $entries = $services->get(EntryManager::class);
        foreach (array_reverse($this->tags) as $tag) {
            if ($entries->isEntry($tag)) {
                $entries->delete($tag, true);
            }
        }
        $users = $services->get(UserManager::class);
        foreach ($this->accounts as $name) {
            $user = $users->getOneByName($name);
            if ($user) {
                $users->delete($user);
            }
        }
        $services->get(FormManager::class)->delete(self::TEAMS_FORM);
        $services->get(FormManager::class)->delete(self::PEOPLE_FORM);
        $services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    private function person(string $name): string
    {
        $this->getWiki()->services->get(UserManager::class)->create($name, strtolower($name) . '@example.tld', 'Aa1!aaaaMembersGroups');
        $this->accounts[] = $name;
        $entry = $this->getWiki()->services->get(EntryManager::class)->create(self::PEOPLE_FORM, ['antispam' => 1, 'bf_titre' => $name . ' fiche', 'nomwiki' => $name]);
        $this->tags[] = (string)$entry['tag'];

        return (string)$entry['tag'];
    }

    /** @return list<string> */
    private function members(string $group): array
    {
        return array_values($this->getWiki()->services->get(GroupManager::class)->getMembers($group));
    }

    public function testTheGroupFollowsTheChosenPeopleAndGoesWithTheEntry(): void
    {
        $services = $this->getWiki()->services;
        $entries = $services->get(EntryManager::class);
        $groups = $services->get(GroupManager::class);
        $alice = $this->person('AliceMembersGroups');
        $bob = $this->person('BobMembersGroups');

        $team = $entries->create(self::TEAMS_FORM, ['antispam' => 1, 'bf_titre' => 'Une équipe test', 'bf_membres' => $alice . ',' . $bob]);
        $tag = (string)$team['tag'];
        $this->tags[] = $tag;
        $this->assertSame(['AliceMembersGroups', 'BobMembersGroups'], $this->members('Equipe' . $tag), 'the account behind each chosen entry');

        $entries->update($tag, ['antispam' => 1, 'bf_titre' => 'Une équipe test', 'bf_membres' => $bob, 'form_id' => self::TEAMS_FORM], false, true);
        $this->assertSame(['BobMembersGroups'], $this->members('Equipe' . $tag), 'someone taken off the field leaves the group');

        $entries->update($tag, ['antispam' => 1, 'bf_titre' => 'Une équipe test', 'bf_membres' => '', 'form_id' => self::TEAMS_FORM], false, true);
        $this->assertSame([$this->admin], $this->members('Equipe' . $tag), 'with nobody chosen, the author holds the group');

        $entries->delete($tag, true);
        $this->assertFalse($groups->groupExists('Equipe' . $tag), 'the group goes with its entry');
    }

    public function testAGroupTheEntryDidNotMakeIsLeftAlone(): void
    {
        $services = $this->getWiki()->services;
        $groups = $services->get(GroupManager::class);
        $alice = $this->person('AliceMembersGroups');
        $tag = 'UneEquipeDejaLa';
        $groups->create('Equipe' . $tag, ['SomeoneElse']);

        try {
            $team = $services->get(EntryManager::class)->create(self::TEAMS_FORM, ['antispam' => 1, 'bf_titre' => 'Une équipe déjà là', 'tag' => $tag, 'bf_membres' => $alice]);
            $this->tags[] = (string)$team['tag'];

            $this->assertSame(['SomeoneElse'], $this->members('Equipe' . $tag));
        } finally {
            $groups->delete('Equipe' . $tag);
        }
    }
}

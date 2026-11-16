<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Content\Exception\ListInUseException;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\ListOverview;
use YesWiki\Content\Service\ListUsage;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 20: a list that form fields take their options from cannot be deleted, and the lists screen says which fields. */
#[CoversClass(ListUsage::class)]
#[CoversClass(ListManager::class)]
class ListUsageTest extends YesWikiTestCase
{
    private const LIST_ID = 'ListeUsageTest';
    private const FORM_ID = '999913';

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $admins = $services->get(GroupManager::class)->getMembers('admins');
        if ($admins === []) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        self::actAs((string)$admins[0]);
        $services->get(ListManager::class)->create('Usage test list', [['id' => 'a', 'label' => 'A']], self::LIST_ID);
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Usage test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'liste', 'linked_object' => self::LIST_ID, 'name' => 'bf_choix', 'label' => 'Choix'],
                ['type' => 'listefiche', 'linked_object' => '1', 'name' => 'bf_lien', 'label' => 'Lien'],
            ]),
        ]);
        $services->get(ListUsage::class)->startNewRequest();
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        $services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
        self::actAs(null);
        parent::tearDown();
    }

    public function testAFieldUsingTheListIsFoundAndAFieldNamingAFormIsNot(): void
    {
        $usage = self::getWiki()->services->get(ListUsage::class);

        $this->assertSame([['formId' => self::FORM_ID, 'formLabel' => 'Usage test form', 'fieldName' => 'bf_choix', 'fieldLabel' => 'Choix']], $usage->fieldsUsing(self::LIST_ID));
        $this->assertNotContains(self::FORM_ID, array_column($usage->fieldsUsing('1'), 'formId'), 'a field linking to entries of a form names a form, not a list');

        $overview = self::getWiki()->services->get(ListOverview::class)->all();
        $this->assertCount(1, $overview['lists'][self::LIST_ID]['usedBy'] ?? []);
    }

    public function testAListInUseIsNotDeletedUntilTheFieldGoes(): void
    {
        $services = self::getWiki()->services;
        $lists = $services->get(ListManager::class);

        try {
            $lists->delete(self::LIST_ID);
            $this->fail('a list in use is refused');
        } catch (ListInUseException $refused) {
            $this->assertStringContainsString('Usage test form', $refused->getMessage());
        }
        $this->assertNotNull($lists->getOne(self::LIST_ID));

        $services->get(FormManager::class)->update([
            'id' => self::FORM_ID,
            'label' => 'Usage test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']]),
        ]);
        $services->get(ListUsage::class)->startNewRequest();

        $lists->delete(self::LIST_ID);
        $this->assertNull($lists->getOne(self::LIST_ID));
    }
}

<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The list and enum-field-name migrations, and the repair that reruns them. */
class RepairListsAndEnumFieldNamesTest extends YesWikiTestCase
{
    private const LIST_TAG = 'ListeTestRepairProtected';
    private const ENTRY_TAG = 'TestRepairEnumEntry';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261004100000_RepairListsAndEnumFieldNames.php';
    }

    public function testAReadProtectedListIsConverted(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::LIST_TAG, '2026-01-01 00:00:00', json_encode(['titre_liste' => 'Protected', 'label' => ['a' => 'Alpha']]), '', '', 'Y', 'list', '']
        );
        $wiki->services->get(AclService::class)->save(self::LIST_TAG, 'read', '@admins');

        try {
            $this->runMigration(new \RepairListsAndEnumFieldNames());
            $wiki->services->get(PageManager::class)->forget(self::LIST_TAG);
            $page = $wiki->services->get(PageManager::class)->getOne(self::LIST_TAG, null, false, true);
            if ($page === null) {
                $this->fail('the protected list is gone');
            }
            $body = $page['body'] ?? null;
            $this->assertEquals(['title' => 'Protected', 'nodes' => [['id' => 'a', 'label' => 'Alpha']]], $body);

            $revisions = count($dbService->loadAll("SELECT id FROM {$pages} WHERE tag = ?", [self::LIST_TAG]));
            $this->runMigration(new \RefactorListStruture());
            $this->assertCount($revisions, $dbService->loadAll("SELECT id FROM {$pages} WHERE tag = ?", [self::LIST_TAG]), 'an unchanged list got a new revision');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::LIST_TAG]);
            $wiki->services->get(AclService::class)->delete(self::LIST_TAG);
        }
    }

    public function testEnumFieldsGetTheNameTheirEntriesUseAndActivityPubIsKept(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');
        $formManager = $wiki->services->get(FormManager::class);
        $id = 9820;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $formManager->create([
            'id' => (string)$id,
            'label' => 'RepairEnumFieldNamesTest',
            'activitypub_enable' => '1',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'checkbox', 'linked_object' => 'ListeFoo', 'name' => 'bf_full', 'label' => 'Full'],
                ['type' => 'checkbox', 'linked_object' => 'ListeFoo', 'name' => 'bf_short', 'label' => 'Short'],
            ],
        ]);
        $created = $formManager->getOne($id);
        if ($created === null) {
            $this->fail('the form was not created');
        }
        $tag = $created['tag'];
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::ENTRY_TAG, '2026-01-01 00:00:00', json_encode([
                'form_id' => (string)$id,
                'bf_titre' => 'x',
                'checkboxListeFoobf_full' => 'a',
                'bf_short' => 'a',
                'checkboxListeFoobf_short' => 'b',
            ]), '', '', 'Y', 'entry', '']
        );

        try {
            $this->runMigration(new \RefactorEnumFieldPropertyName());
            $this->runMigration(new \RepairListsAndEnumFieldNames());

            $formManager->startNewRequest();
            $form = $formManager->getOne((string)$id);
            if ($form === null) {
                $this->fail('the form is gone after the migrations');
            }
            $this->assertSame(['bf_titre', 'checkboxListeFoobf_full', 'bf_short'], array_column($form['template'] ?? [], 'name'));
            $this->assertSame('1', $form['activitypub_enable'] ?? null);
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag IN (?, ?)", [self::ENTRY_TAG, $tag]);
            $formManager->startNewRequest();
        }
    }

    private function runMigration(YesWikiMigration $migration): void
    {
        $wiki = $this->getWiki();
        $migration->setServices($wiki->services);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }
}

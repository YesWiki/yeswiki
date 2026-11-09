<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A Doryphore wiki whose forms are already pages keeps them, and its `nature` table survives until every form has a page with fields. */
class MigrateNatureToPagesTest extends YesWikiTestCase
{
    private const TITLE_ROW = 'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***';

    private DbService $db;
    private FormManager $forms;
    private string $nature;
    /** @var list<string> */
    private array $formIds = [];
    /** @var list<string> */
    private array $tags = [];

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260723000002_MigrateNatureToPages.php';
        require_once 'src/migrations/20260723000003_DropNatureTable.php';
        require_once 'src/migrations/20260727000000_ConvertFormTemplatesToJson.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->getWiki()->services->get(DbService::class);
        $this->forms = $this->getWiki()->services->get(FormManager::class);
        $this->nature = trim($this->db->prefixTable('nature'));
        $this->assertFalse($this->db->schema()->columnExists('nature', 'bn_id_nature'), "{$this->nature} already exists on this database; the test will not touch it");
        $this->db->query("CREATE TABLE {$this->nature} (bn_id_nature INT, bn_label_nature TEXT, bn_template TEXT, bn_description TEXT, bn_condition TEXT, bn_sem_template TEXT, bn_sem_reverse_template TEXT, bn_only_one_entry VARCHAR(1), bn_only_one_entry_message TEXT, bn_ce_i18n VARCHAR(5), bn_activitypub_enable VARCHAR(1))");
    }

    protected function tearDown(): void
    {
        $this->db->query("DROP TABLE IF EXISTS {$this->nature}");
        foreach ($this->formIds as $id) {
            $this->forms->delete($id);
        }
        $pages = $this->db->prefixTable('pages');
        foreach ($this->tags as $tag) {
            $this->db->query("DELETE FROM {$pages} WHERE tag = ?", [$tag]);
        }
        $this->forms->startNewRequest();
        parent::tearDown();
    }

    private function natureRow(string $id, string $label): void
    {
        $this->db->query(
            "INSERT INTO {$this->nature} (bn_id_nature, bn_label_nature, bn_template, bn_description, bn_condition, bn_sem_template, bn_sem_reverse_template, bn_only_one_entry, bn_only_one_entry_message, bn_ce_i18n, bn_activitypub_enable) VALUES (?, ?, ?, '', '', '', '', 'N', '', 'fr-FR', '0')",
            [$id, $label, self::TITLE_ROW]
        );
    }

    private function freeId(): string
    {
        $id = (string)$this->forms->findNewId();
        $this->formIds[] = $id;

        return $id;
    }

    private function migration(YesWikiMigration $migration): YesWikiMigration
    {
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);

        return $migration;
    }

    public function testAFormPageWithFieldsIsKeptAndOneWithoutFieldsIsRebuiltFromTheRow(): void
    {
        $kept = $this->freeId();
        $this->forms->create(['id' => $kept, 'label' => 'Kept form', 'template' => self::TITLE_ROW . "\n" . 'texte***bf_lieu***Lieu*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***']);
        $this->natureRow($kept, 'Kept form in nature');

        $empty = $this->freeId();
        $emptyTag = 'migrate-nature-empty-' . $empty;
        $this->tags[] = $emptyTag;
        $pages = $this->db->prefixTable('pages');
        $this->db->query(
            "INSERT INTO {$pages} (tag, {$this->db->quoteIdentifier('time')}, body, owner, {$this->db->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, '', '', 'Y', ?, '')",
            [$emptyTag, '2026-01-01 00:00:00', PageBody::encode(['id' => $empty, 'title' => 'Page without fields', 'fields' => ['bf_titre' => ['label' => 'Titre']]]), PageType::FORM]
        );
        $this->natureRow($empty, 'Rebuilt form');
        $this->forms->startNewRequest();

        $this->migration(new \MigrateNatureToPages())->run();
        $this->forms->startNewRequest();

        $keptForm = $this->forms->getOne($kept);
        $this->assertIsArray($keptForm);
        $this->assertSame('Kept form', $keptForm['label'] ?? null);
        $this->assertCount(2, $keptForm['template'] ?? []);
        $rebuilt = $this->forms->getOne($empty);
        $this->assertIsArray($rebuilt);
        $this->assertSame($emptyTag, $rebuilt['tag'] ?? null);
        $this->assertSame('Rebuilt form', $rebuilt['label'] ?? null);
        $this->assertNotEmpty($rebuilt['template'] ?? []);
    }

    public function testTheNatureTableIsKeptWhileAFormHasNoPageWithFields(): void
    {
        $orphan = $this->freeId();
        $this->natureRow($orphan, 'Not yet a page');

        try {
            $this->migration(new \DropNatureTable())->run();
            $this->fail('DropNatureTable dropped a table holding a form with no page');
        } catch (\Exception $e) {
            $this->assertStringContainsString($orphan, $e->getMessage());
        }
        $this->assertTrue($this->db->schema()->columnExists('nature', 'bn_id_nature'));

        $this->migration(new \MigrateNatureToPages())->run();
        $this->forms->startNewRequest();
        $this->migration(new \DropNatureTable())->run();

        $this->assertFalse($this->db->schema()->columnExists('nature', 'bn_id_nature'));
        $this->assertNotEmpty($this->forms->getOne($orphan)['template'] ?? []);
    }

    public function testAFormPageAlreadyHoldingATemplateIsNotGivenAnEmptyOne(): void
    {
        $id = $this->freeId();
        $this->forms->create(['id' => $id, 'label' => 'Already JSON', 'template' => self::TITLE_ROW]);
        $tag = (string)($this->forms->getOne($id)['tag'] ?? '');
        $pages = $this->db->prefixTable('pages');
        $before = $this->db->loadSingle("SELECT body FROM {$pages} WHERE tag = ? AND latest = 'Y'", [$tag])['body'] ?? '';

        $this->migration(new \ConvertFormTemplatesToJson())->run();

        $after = $this->db->loadSingle("SELECT body FROM {$pages} WHERE tag = ? AND latest = 'Y'", [$tag])['body'] ?? '';
        $this->assertSame(PageBody::decode((string)$before), PageBody::decode((string)$after));
        $this->assertArrayNotHasKey('bn_template', PageBody::decode((string)$after));
    }
}

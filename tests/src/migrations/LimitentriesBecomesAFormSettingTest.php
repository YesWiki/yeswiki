<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{limitentries}}` hands its limit and messages to the form, and leaves the entry form in its place. */
class LimitentriesBecomesAFormSettingTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261006120000_LimitentriesBecomesAFormSetting.php';
    }

    public function testTheSmallestLimitAndTheFirstMessagesAreKept(): void
    {
        $content = "{{limitentries id=\"2\" limit=\"30\" message_max=\"Plein (%{limit})\"}}\n"
            . "{{limitentries id=\"2\" limit=\"20\" message_count=\"%{nb}/%{limit}\"}}\n"
            . "{{limitentries id=\"3\" limit=\"5\" message_count=\"\"}}\n"
            . "{{limitentries id=\"4\"}}\n"
            . '{{limitentries id="5" limit="9"}}';

        $this->assertSame(
            [
                '2' => ['limit' => 20, 'message' => 'Plein ({limit})', 'count' => '{nb}/{limit}'],
                '3' => ['limit' => 5, 'message' => '', 'count' => ''],
                '5' => ['limit' => 9, 'message' => '', 'count' => null],
            ],
            \LimitentriesBecomesAFormSetting::settingsFrom($content, [])
        );
    }

    public function testEachCallBecomesTheEntryForm(): void
    {
        $this->assertSame(
            "Avant\n{{bazar id=\"2\" showmenu=\"0\" view=\"saisir\"}}\nentre  et après",
            \LimitentriesBecomesAFormSetting::rewrite("Avant\n{{limitentries id=\"2\" limit=\"30\"}}\nentre {{limitentries limit=\"3\" message_max=\"%{limit}\"}} et après")
        );
    }

    public function testTheMigrationLimitsTheFormAndRewritesThePage(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $formManager = $services->get(FormManager::class);
        $pages = trim($db->prefixTable('pages'));
        $formManager->create(['id' => '999932', 'label' => 'Limitentries migration test form', 'template' => '']);
        $db->query(
            "INSERT INTO {$pages} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ['TestLimitentriesMigration', '2020-01-01 00:00:00', PageBody::encode(['content' => '{{limitentries id="999932" limit="12" message_max="Plein (%{limit})"}}']), '', '', 'Y', 'page', '']
        );

        try {
            $migration = new \LimitentriesBecomesAFormSetting();
            $migration->setServices($services);
            $migration->setDbService($db);
            $migration->run();

            $form = $formManager->getOne('999932');
            $this->assertSame('12', $form['max_entries'] ?? null);
            $this->assertSame('Plein ({limit})', $form['max_entries_message'] ?? null);
            $this->assertSame(_t('FORM_MAX_ENTRIES_DEFAULT_COUNT_MESSAGE'), $form['max_entries_count_message'] ?? null);
            $row = $db->loadSingle("SELECT body FROM {$pages} WHERE tag = ?", ['TestLimitentriesMigration']);
            $this->assertSame('{{bazar id="999932" showmenu="0" view="saisir"}}', PageBody::content(PageBody::decode((string)($row['body'] ?? ''))));
        } finally {
            $db->query("DELETE FROM {$pages} WHERE tag = ?", ['TestLimitentriesMigration']);
            $formManager->delete('999932');
        }
    }
}

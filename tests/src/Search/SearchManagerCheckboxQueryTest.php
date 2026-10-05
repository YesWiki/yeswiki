<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Database\MySqlDialect;
use YesWiki\Kernel\Database\PostgreSqlDialect;
use YesWiki\Kernel\Database\SqliteDialect;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A query on a checkbox field splits its comma-separated keys in SQL, with functions every supported database has. */
class SearchManagerCheckboxQueryTest extends YesWikiTestCase
{
    private const FORM_ID = '999962';
    private const LIST_ID = 'ListeCheckboxQueryTest';

    /** @var list<string> */
    private static array $tags = [];

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $services->get(ListManager::class)->create(
            'Checkbox query test list',
            array_map(static fn (string $id): array => ['id' => $id, 'label' => "Option $id"], ['1', '2', '3', '13']),
            self::LIST_ID,
        );
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Checkbox query test',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1'],
                ['type' => 'checkbox', 'linked_object' => self::LIST_ID, 'name' => 'bf_x', 'label' => 'X'],
            ]),
            'condition' => '',
        ]);
        $entryManager = $services->get(EntryManager::class);
        foreach (['C1' => '1,3', 'C2' => '2', 'C3' => '3', 'C4' => '13', 'C5' => '2,13,1'] as $title => $value) {
            $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => $title, 'bf_x' => $value, 'tag' => $title]);
            self::$tags[] = $title;
        }
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $entryManager = $services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        $services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $rows = $this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::FORM_ID],
            'queries' => $query,
        ]);
        $titles = array_values(array_filter(array_map(static fn ($r) => $r['bf_titre'] ?? null, $rows)));
        sort($titles);

        return $titles;
    }

    public function testAKeyMatchesWhereverItSitsInTheList(): void
    {
        $this->assertSame(['C1', 'C3'], $this->titles('bf_x=3'));
        $this->assertSame(['C1', 'C5'], $this->titles('bf_x=1'));
        $this->assertSame(['C4', 'C5'], $this->titles('bf_x=13'));
    }

    public function testSeveralKeysOrTogether(): void
    {
        $this->assertSame(['C1', 'C2', 'C3', 'C5'], $this->titles('bf_x=2,3'));
    }

    public function testTheSplitUsesNoMySqlOnlyFunction(): void
    {
        $params = ['formsIds' => [self::FORM_ID], 'queries' => 'bf_x=3'];
        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params)->sql;
        $this->assertStringContainsString('bf_x_multiple', $sql);
        $this->assertStringNotContainsStringIgnoringCase('SUBSTRING_INDEX', $sql);
        $this->assertStringContainsString($this->getWiki()->services->get(DbService::class)->strpos('rest', "','"), $sql);
        foreach ([new MySqlDialect(), new SqliteDialect()] as $dialect) {
            $this->assertSame("INSTR(bf_x, ',')", $dialect->strpos('bf_x', "','"));
        }
        $this->assertSame("STRPOS(bf_x, ',')", (new PostgreSqlDialect())->strpos('bf_x', "','"));
    }
}

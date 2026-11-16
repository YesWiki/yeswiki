<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Queries and keywords filter entries on a wiki whose MySQL connection is not utf8mb4 (no `db_charset` in its config). */
class SearchManagerConnectionCharsetTest extends YesWikiTestCase
{
    private static string $formId;
    private static string $listId;

    /** @var list<string> */
    private static array $tags = [];

    private string $savedCollation;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $entryManager = $wiki->services->get(EntryManager::class);
        self::$listId = $wiki->services->get(ListManager::class)->create('Connection charset test list', [
            ['id' => 'financements', 'label' => 'Financements'],
            ['id' => 'droits', 'label' => 'Droits'],
        ], 'ListConnectionCharset' . bin2hex(random_bytes(4)));
        self::$formId = $wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Connection charset test',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'radio***' . self::$listId . '***Parcours*** *** *** ***bf_parcours*** ***0*** *** *** * *** * *** *** *** ***',
                'checkbox***' . self::$listId . '***Thèmes*** *** *** ***bf_themes*** ***0*** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
        foreach ([
            ['bf_titre' => 'Co-construire', 'bf_parcours' => 'financements', 'bf_themes' => 'financements,droits'],
            ['bf_titre' => 'Anticiper', 'bf_parcours' => 'droits', 'bf_themes' => 'droits'],
        ] as $data) {
            self::$tags[] = $entryManager->create(self::$formId, ['antispam' => 1] + $data)['id_fiche'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $wiki->services->get(FormManager::class)->delete(self::$formId);
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::$listId);
        $wiki->services->get(TripleStore::class)->delete(self::$listId, TripleStore::TYPE_URI, null, '', '');
    }

    protected function setUp(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $this->savedCollation = $dbService->getCollation();
        mysqli_query($dbService->getLink(), 'SET NAMES utf8mb3');
        $this->setCollation('utf8_unicode_ci');
    }

    protected function tearDown(): void
    {
        mysqli_query($this->getWiki()->services->get(DbService::class)->getLink(), 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->setCollation($this->savedCollation);
    }

    private function setCollation(string $collation): void
    {
        $property = new \ReflectionProperty(DbService::class, 'collation');
        $property->setValue($this->getWiki()->services->get(DbService::class), $collation);
    }

    /** @return list<string> */
    private function titles(array $params): array
    {
        $titles = array_column($this->getWiki()->services->get(EntryManager::class)->search(
            ['formsIds' => [self::$formId]] + $params
        ), 'bf_titre');
        sort($titles);

        return $titles;
    }

    public function testATextFieldQueryMatches()
    {
        $this->assertSame(['Co-construire'], $this->titles(['queries' => 'bf_titre=Co-construire']));
    }

    public function testARadioFieldQueryMatches()
    {
        $this->assertSame(['Co-construire'], $this->titles(['queries' => 'bf_parcours=financements']));
        $this->assertSame(['Anticiper'], $this->titles(['queries' => 'bf_parcours!=financements']));
    }

    public function testACheckboxFieldQueryMatches()
    {
        $this->assertSame(['Anticiper', 'Co-construire'], $this->titles(['queries' => 'bf_themes=droits']));
    }

    public function testARegExpQueryMatches()
    {
        $this->assertSame(['Anticiper'], $this->titles(['queries' => 'bf_titre=/^Anti/']));
    }

    public function testKeywordsMatch()
    {
        $this->assertSame(['Anticiper'], $this->titles(['keywords' => 'anticip']));
    }
}

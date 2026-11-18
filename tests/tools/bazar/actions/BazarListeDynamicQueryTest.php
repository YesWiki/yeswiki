<?php

namespace YesWiki\Test\Bazar\Actions;

use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A dynamic bazarliste hands its params to the browser, which sends them back to the API: the AND/OR grammar must survive that trip. */
class BazarListeDynamicQueryTest extends YesWikiTestCase
{
    private static string $formId;

    /** @var list<string> */
    private static array $tags = [];

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $wiki->services->get(AuthController::class)->logout();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        self::$formId = $formManager->create([
            'bn_label_nature' => 'Dynamic list query test',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_a***A***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . 'texte***bf_b***B***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        foreach ([['E1', 'toto', 'x'], ['E2', 'x', 'tata'], ['E3', 'toto', 'tata'], ['E4', 'x', 'x']] as [$title, $a, $b]) {
            $tag = $entryManager->create(self::$formId, ['antispam' => 1, 'bf_titre' => $title, 'bf_a' => $a, 'bf_b' => $b])['id_fiche'];
            $wiki->services->get(AclService::class)->save($tag, 'read', '*');
            self::$tags[] = $tag;
        }
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
            $wiki->services->get(AclService::class)->delete($tag);
        }
        self::$tags = [];
        $wiki->services->get(FormManager::class)->delete(self::$formId);
    }

    /** @return list<string> titles the API returns for the params the action wrote into the page */
    private function titlesThroughTheBrowser(string $query): array
    {
        $html = $this->getWiki()->Format('{{bazarliste id="' . self::$formId . '" template="card" dynamic="true" query="' . $query . '"}}');
        $this->assertSame(1, preg_match('/data-params="([^"]+)"/', $html, $matches), 'no dynamic params in the output');
        $params = json_decode(html_entity_decode($matches[1]), true);
        $titles = array_column($this->getWiki()->services->get(BazarListService::class)->getEntries($params), 'bf_titre');
        sort($titles);

        return $titles;
    }

    public function testAndReachesTheApi()
    {
        $this->assertSame(['E3'], $this->titlesThroughTheBrowser('bf_a=toto AND bf_b=tata'));
    }

    public function testAndAfterAnEmptyValueReachesTheApi()
    {
        $this->assertSame(['E2', 'E3'], $this->titlesThroughTheBrowser('bf_a!= AND bf_b=tata'));
    }

    public function testOrReachesTheApi()
    {
        $this->assertSame(['E1', 'E2', 'E3'], $this->titlesThroughTheBrowser('bf_a=toto OR bf_b=tata'));
    }

    public function testParenthesesReachTheApi()
    {
        $this->assertSame(['E2', 'E3'], $this->titlesThroughTheBrowser('(bf_a=toto OR bf_a=x) AND bf_b=tata'));
    }

    public function testLegacyPipeStillReachesTheApi()
    {
        $this->assertSame(['E3'], $this->titlesThroughTheBrowser('bf_a=toto|bf_b=tata'));
    }

    public function testAggregatingKeepsEachQueryWhole()
    {
        $searchManager = $this->getWiki()->services->get(SearchManager::class);
        $query = $searchManager->aggregateQueries('bf_a=toto OR bf_a=x', 'bf_b=tata');
        $titles = array_column($this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::$formId],
            'queries' => $query,
        ]), 'bf_titre');
        sort($titles);
        $this->assertSame(['E2', 'E3'], $titles);
    }
}

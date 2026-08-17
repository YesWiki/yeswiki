<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Api\TagApiController;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 10 (tags absorbed into core): - TagsManager::getAll($page) used to silently ignore $page, always reading Wiki::GetPageTag() instead (only ever worked by coincidence at its one real call site) -- it now honors the argument.
 */
class TagsManagerAndApiTest extends YesWikiTestCase
{
    private const TAG_A = 'TagsManagerRegressionPageA';
    private const TAG_B = 'TagsManagerRegressionPageB';

    private static ?TripleStore $tripleStore = null;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        self::$tripleStore = $wiki->services->get(TripleStore::class);

        $pageManager = $wiki->services->get(PageManager::class);
        $tagsManager = $wiki->services->get(TagsManager::class);
        $pageManager->save(self::TAG_A, [PageBody::CONTENT => 'page a'], '', true);
        $pageManager->save(self::TAG_B, [PageBody::CONTENT => 'page b'], '', true);
        $tagsManager->save(self::TAG_A, 'regressionapple,regressionapricot');
        $tagsManager->save(self::TAG_B, 'regressionbanana');
    }

    public static function tearDownAfterClass(): void
    {
        self::$tripleStore->delete(self::TAG_A, TagsManager::TAG_PROPERTY, null, '', '');
        self::$tripleStore->delete(self::TAG_B, TagsManager::TAG_PROPERTY, null, '', '');
        $pageManager = self::getWiki()->services->get(PageManager::class);
        $pageManager->deleteOrphaned(self::TAG_A);
        $pageManager->deleteOrphaned(self::TAG_B);
    }

    public function testGetAllHonorsThePageArgument(): void
    {
        $tagsManager = $this->getWiki()->services->get(TagsManager::class);

        $values = array_column($tagsManager->getAll(self::TAG_A), 'value');
        sort($values);

        $this->assertSame(['regressionapple', 'regressionapricot'], $values);
    }

    public function testGetAllWithNoPageStillDumpsEverything(): void
    {
        $tagsManager = $this->getWiki()->services->get(TagsManager::class);

        $values = array_column($tagsManager->getAll(), 'value');

        $this->assertContains('regressionbanana', $values);
    }

    public function testSearchFiltersAndPaginates(): void
    {
        $tagsManager = $this->getWiki()->services->get(TagsManager::class);

        $result = $tagsManager->search('regressiona', 20, 0);
        sort($result['tags']);

        $this->assertSame(['regressionapple', 'regressionapricot'], $result['tags']);
        $this->assertSame(2, $result['total']);

        $paged = $tagsManager->search('regressiona', 1, 0);
        $this->assertCount(1, $paged['tags']);
        $this->assertSame(2, $paged['total'], 'total must reflect the full match count, not just this page');
    }

    public function testApiTagsRouteReturnsJsonMatchingSearch(): void
    {
        $wiki = $this->getWiki();
        $controller = $wiki->services->get(TagApiController::class);
        $request = Request::create('/api/tags', 'GET', ['search' => 'regressionban']);

        $response = $controller->getTags($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['regressionbanana'], $data['tags']);
        $this->assertSame(1, $data['total']);
    }
}

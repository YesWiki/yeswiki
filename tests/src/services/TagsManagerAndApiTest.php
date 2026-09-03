<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Search\Api\TagApiController;
use YesWiki\Search\Service\SearchIndexer;
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

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();

        $pageManager = $wiki->services->get(PageManager::class);
        // A keyword is set by saving the page that carries it, and the index follows (ticket 62).
        $pageManager->save(self::TAG_A, [
            PageBody::CONTENT => 'page a',
            PageBody::KEYWORDS => ['regressionapple', 'regressionapricot'],
        ], '', true);
        $pageManager->save(self::TAG_B, [
            PageBody::CONTENT => 'page b',
            PageBody::KEYWORDS => ['regressionbanana'],
        ], '', true);
        $wiki->services->get(SearchIndexer::class)->drain(1000);
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);
        foreach ([self::TAG_A, self::TAG_B] as $tag) {
            $pageManager->deleteOrphaned($tag);
            $indexer->delete($tag);
        }
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
        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);

        $this->assertSame(['regressionbanana'], $data['tags']);
        $this->assertSame(1, $data['total']);
    }
}

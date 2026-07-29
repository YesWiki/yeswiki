<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Search\Api\TagApiController;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 10 (tags absorbed into core):
 * - TagsManager::getAll($page) used to silently ignore $page, always reading
 *   Wiki::GetPageTag() instead (only ever worked by coincidence at its one real
 *   call site) -- it now honors the argument.
 * - TagsManager::search() is the new live-search backing GET /api/tags, replacing
 *   the old "dump every tag" pattern.
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
        self::$tripleStore->create(self::TAG_A, TagsManager::TAG_PROPERTY, 'regressionapple', '', '');
        self::$tripleStore->create(self::TAG_A, TagsManager::TAG_PROPERTY, 'regressionapricot', '', '');
        self::$tripleStore->create(self::TAG_B, TagsManager::TAG_PROPERTY, 'regressionbanana', '', '');
    }

    public static function tearDownAfterClass(): void
    {
        self::$tripleStore->delete(self::TAG_A, TagsManager::TAG_PROPERTY, null, '', '');
        self::$tripleStore->delete(self::TAG_B, TagsManager::TAG_PROPERTY, null, '', '');
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
        // src/fields/TagsField.php (out of scope for this ticket) relies on this
        // exact no-arg "everything" behavior for its own tag-autocomplete -- must survive
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

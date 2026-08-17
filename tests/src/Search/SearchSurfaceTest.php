<?php

namespace YesWiki\Test\Search;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Search\Api\SearchApiController;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The search surface (ticket 26): `{{search}}`, `/api/search`, and what a result looks like. */
class SearchSurfaceTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'SearchSurfaceTestPage';
    private const PRIVATE_TAG = 'SearchSurfaceTestPrivatePage';

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        $this->removeFixtures();
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        foreach ([self::PAGE_TAG, self::PRIVATE_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    private function removeFixtures(): void
    {
        $wiki = $this->getWiki();
        foreach ([self::PAGE_TAG, self::PRIVATE_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    private function savePage(string $tag, string $title, string $content, ?string $readAcl = null): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageManager::class)->save($tag, [
            PageBody::TITLE => $title,
            PageBody::CONTENT => $content,
        ], '', true);
        if ($readAcl !== null) {
            $wiki->services->get(AclService::class)->save($tag, 'read', $readAcl);
        }
        $wiki->services->get(SearchIndexer::class)->index($tag);
    }

    /**
     * @param array<string, string|int> $query
     */
    private function fragment(array $query): string
    {
        return (string)$this->getWiki()->services->get(SearchApiController::class)
            ->search(Request::create('/api/search', 'GET', $query))
            ->getContent();
    }

    public function testTheActionRendersAFormAndAResultsContainer(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search');

        $this->assertStringContainsString('id="yw-search-form"', $html);
        $this->assertStringContainsString('id="yw-search-results"', $html);

        $this->assertStringContainsString('hx-get=', $html);

        $this->assertStringContainsString('name="q"', $html);
    }

    public function testTheActionUsesTheSharedSearchBox(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search');

        $this->assertStringContainsString('yw-searchbox', $html);
        $this->assertStringContainsString('yw-searchbox__icon', $html);
        $this->assertStringContainsString('yw-searchbox__button', $html);
    }

    /**
     * The content-type filter is not beside the box; it arrives with the results, because a filter is only meaningful once there is something to narrow.
     */
    public function testTheTypeFilterIsNotOfferedBeforeAnySearch(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search');

        $this->assertStringNotContainsString('yw-facets', $html);
        $this->assertStringNotContainsString('<select', $html);
    }

    public function testAnEmbeddedBoxCanBeScopedToOneTypeAndSuppressItsFacets(): void
    {
        $scoped = $this->getWiki()->services->get(ActionRunner::class)
            ->action('search', ['filters' => '0', 'type' => 'entry']);

        $this->assertStringContainsString('name="type"', $scoped);
        $this->assertStringContainsString('value="entry"', $scoped);

        $this->assertStringContainsString('name="facets"', $scoped);
    }

    public function testAnEmptyPhrasePromptsRatherThanListingTheWholeWiki(): void
    {
        $html = $this->fragment(['q' => '']);

        $this->assertStringContainsString(_t('SEARCH_TYPE_SOMETHING'), $html);
        $this->assertStringNotContainsString('yw-search-result ', $html);
    }

    public function testAMatchIsRenderedAsALinkedResultWithItsType(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $html = $this->fragment(['q' => 'ciboulette']);

        $this->assertStringContainsString('Le potager', $html);
        $this->assertStringContainsString(self::PAGE_TAG, $html);
        $this->assertStringContainsString(_t('SEARCH_TYPE_PAGE'), $html);
        $this->assertStringContainsString('yw-search-result--page', $html);
    }

    public function testNoMatchSaysSoRatherThanRenderingAnEmptyList(): void
    {
        $html = $this->fragment(['q' => 'zzzzaucunresultatzzzz']);

        $this->assertStringContainsString(htmlspecialchars(_t('NO_SEARCH_RESULT'), ENT_QUOTES), $html);
    }

    public function testTheTypeFilterNarrowsTheFragment(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $this->assertStringContainsString(self::PAGE_TAG, $this->fragment(['q' => 'ciboulette', 'type' => 'page']));
        $this->assertStringNotContainsString(self::PAGE_TAG, $this->fragment(['q' => 'ciboulette', 'type' => 'entry']));
    }

    /**
     * Facets arrive WITH the results, carry counts, and cover every type the query matches -- not just the selected one, since the point of a facet is to show what else is there.
     */
    public function testTheFacetRowArrivesWithTheResultsAndCarriesCounts(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $html = $this->fragment(['q' => 'wiki']);

        if (!str_contains($html, 'yw-facets')) {
            $this->markTestSkipped('this wiki matches only one content type for that phrase');
        }
        $this->assertStringContainsString('yw-facet__count', $html);
        $this->assertStringContainsString('name="type"', $html, 'a facet is form state, not a JS toggle');
        $this->assertStringContainsString('type="radio"', $html);
    }

    /** Selecting a facet keeps it checked, so the choice survives the swap that renders it. */
    public function testTheSelectedFacetComesBackChecked(): void
    {
        $html = $this->fragment(['q' => 'wiki', 'type' => 'page']);
        if (!str_contains($html, 'yw-facets')) {
            $this->markTestSkipped('this wiki matches only one content type for that phrase');
        }

        $this->assertMatchesRegularExpression(
            '/value="page"[^>]*\n?\s*checked|checked[^>]*value="page"/',
            $html,
            'the chosen facet must render checked -- nothing else remembers it'
        );
    }

    /** Three ways to read the same results. */
    public function testResultsCanBeRenderedAsAListAccordionOrCards(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $action = $this->getWiki()->services->get(ActionRunner::class)->action('search');
        $this->assertStringContainsString('yw-display-switch', $action);

        $list = $this->fragment(['q' => 'ciboulette']);
        $this->assertStringNotContainsString('yw-display-switch', $list);
        $this->assertStringNotContainsString('yw-search-results--', $list, 'list is the unmodified default');

        $accordion = $this->fragment(['q' => 'ciboulette', 'display' => 'accordion']);
        $this->assertStringContainsString('yw-search-results--accordion', $accordion);

        $this->assertStringContainsString('<details', $accordion);
        $this->assertStringContainsString('yw-accordion__item', $accordion);
        $this->assertStringContainsString('yw-accordion__summary', $accordion);

        $cards = $this->fragment(['q' => 'ciboulette', 'display' => 'cards']);
        $this->assertStringContainsString('yw-search-results--cards', $cards);
    }

    /** A display mode is a visitor-supplied string that reaches a class name. */
    public function testAnUnknownDisplayModeFallsBackToTheListRatherThanReachingTheMarkup(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $html = $this->fragment(['q' => 'ciboulette', 'display' => 'evil"><script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('yw-search-results--evil', $html);
    }

    /** The endpoint is public by ACL, and safely so *because* the query evaluates rights in SQL. */
    public function testThePublicEndpointDoesNotLeakAPrivatePage(): void
    {
        $this->savePage(self::PRIVATE_TAG, 'Secret', 'un texte sur le topinambour', '@admins');

        $anonymous = $this->fragment(['q' => 'topinambour']);
        $this->assertStringNotContainsString(self::PRIVATE_TAG, $anonymous);
        $this->assertStringNotContainsString('Secret', $anonymous);

        $this->loginAsAdmin();

        $this->assertStringContainsString(
            self::PRIVATE_TAG,
            $this->fragment(['q' => 'topinambour']),
            'an entitled reader must still find it -- the filter is per-visitor, not a blanket hide'
        );
    }

    public function testPagingIsOfferedOnlyWhenThereIsMoreThanOnePage(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $onePage = $this->fragment(['q' => 'ciboulette', 'limit' => 20]);
        $this->assertStringNotContainsString('yw-search-pagination', $onePage);

        $manyPages = $this->fragment(['q' => 'wiki', 'limit' => 1]);
        if (!str_contains($manyPages, htmlspecialchars(_t('NO_SEARCH_RESULT'), ENT_QUOTES))) {
            $this->assertStringContainsString('yw-search-pagination', $manyPages);
        }
    }

    /** The one place ticket 18's key/label decision leaks into what a person sees. */
    public function testAnExcerptShowsOptionLabelsRatherThanStoredKeys(): void
    {
        $wiki = $this->getWiki();
        $translator = $wiki->services->get(\YesWiki\Search\Service\FormOptionTranslator::class);

        $key = null;
        $label = null;
        foreach ($wiki->services->get(\YesWiki\Content\Service\FormManager::class)->getAll() as $form) {
            foreach ($form['prepared'] ?? [] as $field) {
                if (!$field instanceof \YesWiki\Content\Field\EnumField) {
                    continue;
                }
                foreach ((array)$field->getOptions() as $optionKey => $optionLabel) {
                    if (is_string($optionLabel) && trim($optionLabel) !== '' && (string)$optionKey !== $optionLabel) {
                        $key = (string)$optionKey;
                        $label = $optionLabel;
                        break 3;
                    }
                }
            }
        }
        if ($key === null) {
            $this->markTestSkipped('this wiki has no enum option whose key differs from its label');
        }

        $this->assertSame(
            $label,
            $translator->labelForKey($key),
            'an excerpt must be able to read a stored key back as the label a visitor typed'
        );
    }

    public function testTheRetiredActionsAreGone(): void
    {
        foreach (['newtextsearch', 'searchform'] as $retired) {
            $html = $this->getWiki()->services->get(ActionRunner::class)->action($retired);
            $this->assertStringNotContainsString(
                'yw-search-form',
                $html,
                "{{{$retired}}} must not still be answering -- ticket 26 deletes it"
            );
        }
    }

    /** Registering the route reserves the tag; ReservedTagsTest guards the derivation. */
    public function testTheSearchTagIsReserved(): void
    {
        $this->assertTrue(ReservedTags::isReserved('search'));
        $this->assertTrue(ReservedTags::isReserved('Search'));
        $this->assertFalse(ReservedTags::isReserved('searchable'), 'only the exact first segment is reserved');
    }

    private function loginAsAdmin(): void
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }
}

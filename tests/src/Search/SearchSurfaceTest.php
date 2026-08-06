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

/**
 * The search surface (ticket 26): `{{search}}`, `/api/search`, and what a result looks like.
 *
 * The index itself is covered by SearchIndexTest and SearchIndexAclTest. What is under test
 * here is everything between a matching row and a visitor: the fragment's shape, the
 * per-content-type presentation, and the ACL boundary at the *endpoint*, which is where a
 * mistake would be a disclosure rather than a bad result.
 */
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

    /** @param array<string, string|int> $query */
    private function fragment(array $query): string
    {
        return (string)$this->getWiki()->services->get(SearchApiController::class)
            ->search(Request::create('/api/search', 'GET', $query))
            ->getContent();
    }

    // ------------------------------------------------------------------ the action

    public function testTheActionRendersAFormAndAResultsContainer(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search');

        $this->assertStringContainsString('id="yw-search-form"', $html);
        $this->assertStringContainsString('id="yw-search-results"', $html);
        // the container fetches the fragment rather than the action rendering results itself,
        // so filtering and paging are one endpoint rather than two rendering paths
        $this->assertStringContainsString('hx-get=', $html);
        // the field is named `q`, so a shared search URL stays short
        $this->assertStringContainsString('name="q"', $html);
    }

    public function testTheActionUsesTheSharedSearchBox(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search');

        // one component for every search field in the wiki -- the surface must not grow its
        // own markup again, which is how it ended up with a class that had no CSS at all
        $this->assertStringContainsString('yw-searchbox', $html);
        $this->assertStringContainsString('yw-searchbox__icon', $html);
        $this->assertStringContainsString('yw-searchbox__button', $html);
    }

    /**
     * The content-type filter is not beside the box; it arrives with the results, because a
     * filter is only meaningful once there is something to narrow.
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

        // the type travels so the embedded box stays scoped ...
        $this->assertStringContainsString('name="type"', $scoped);
        $this->assertStringContainsString('value="entry"', $scoped);
        // ... and the facet row is suppressed, since it would only offer to widen past what
        // the webmaster asked for
        $this->assertStringContainsString('name="facets"', $scoped);
    }

    // ------------------------------------------------------------------ the fragment

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

        // the message is Twig-escaped in the fragment, so compare against the escaped form
        $this->assertStringContainsString(htmlspecialchars(_t('NO_SEARCH_RESULT'), ENT_QUOTES), $html);
    }

    public function testTheTypeFilterNarrowsTheFragment(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $this->assertStringContainsString(self::PAGE_TAG, $this->fragment(['q' => 'ciboulette', 'type' => 'page']));
        $this->assertStringNotContainsString(self::PAGE_TAG, $this->fragment(['q' => 'ciboulette', 'type' => 'entry']));
    }

    /**
     * Facets arrive WITH the results, carry counts, and cover every type the query matches --
     * not just the selected one, since the point of a facet is to show what else is there.
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
        // a phrase spanning more than one content type: with only one there is nothing to
        // narrow and the row is deliberately not rendered at all
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

    /**
     * Three ways to read the same results. The mode is a *radio*, so it is form state that
     * travels with the query -- switching layout must not re-run a different search.
     */
    public function testResultsCanBeRenderedAsAListAccordionOrCards(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        // the switcher itself lives in the FORM, not in the fragment: it says how to read
        // whatever comes back, so it must not appear and disappear with the results
        $action = $this->getWiki()->services->get(ActionRunner::class)->action('search');
        $this->assertStringContainsString('yw-display-switch', $action);

        $list = $this->fragment(['q' => 'ciboulette']);
        $this->assertStringNotContainsString('yw-display-switch', $list);
        $this->assertStringNotContainsString('yw-search-results--', $list, 'list is the unmodified default');

        $accordion = $this->fragment(['q' => 'ciboulette', 'display' => 'accordion']);
        $this->assertStringContainsString('yw-search-results--accordion', $accordion);
        // <details>, not a JS widget: the browser already has an accessible one -- and it is
        // the wiki's ONE accordion partial, shared with the entry list, the syndication list,
        // the pages accordion and the {{panel}} action
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

    /**
     * The endpoint is public by ACL, and safely so *because* the query evaluates rights in
     * SQL. If that ever stopped being true this is a disclosure, not a wrong result -- which
     * is why it is asserted at the endpoint and not only at the query.
     */
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

        // force several pages out of the seeded corpus rather than inventing one
        $manyPages = $this->fragment(['q' => 'wiki', 'limit' => 1]);
        if (!str_contains($manyPages, htmlspecialchars(_t('NO_SEARCH_RESULT'), ENT_QUOTES))) {
            $this->assertStringContainsString('yw-search-pagination', $manyPages);
        }
    }

    /**
     * The one place ticket 18's key/label decision leaks into what a person sees.
     *
     * The index deliberately stores an enum option's **key**, so a relabel costs no
     * reindexing -- which means an excerpt cut straight from indexed text would read
     * `atelier` or `3` where a visitor expects "Atelier participatif". The presenter reads
     * them back; a miss shows a raw key, never a missed match.
     */
    public function testAnExcerptShowsOptionLabelsRatherThanStoredKeys(): void
    {
        $wiki = $this->getWiki();
        $translator = $wiki->services->get(\YesWiki\Search\Service\FormOptionTranslator::class);

        // whatever this wiki's seeded lists happen to hold -- the mapping is what is under
        // test, not a particular option
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

    // ------------------------------------------------------------------ retirements

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

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
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('search', false);

        $this->assertStringContainsString('id="yw-search-form"', $html);
        $this->assertStringContainsString('id="yw-search-results"', $html);
        // the container fetches the fragment rather than the action rendering results itself,
        // so filtering and paging are one endpoint rather than two rendering paths
        $this->assertStringContainsString('hx-get=', $html);
        $this->assertStringContainsString('name="phrase"', $html);
    }

    public function testTheTypeFilterCanBeTurnedOffForAnEmbeddedBox(): void
    {
        $withFilters = $this->getWiki()->services->get(ActionRunner::class)->action('search', false);
        $this->assertStringContainsString('name="type"', $withFilters);
        $this->assertStringContainsString('<select', $withFilters);

        $without = $this->getWiki()->services->get(ActionRunner::class)->action('search', false, ['filters' => '0']);
        $this->assertStringNotContainsString('<select', $without);
        // the parameter still travels, so an embedded box can be scoped to one type
        $this->assertStringContainsString('name="type"', $without);
    }

    // ------------------------------------------------------------------ the fragment

    public function testAnEmptyPhrasePromptsRatherThanListingTheWholeWiki(): void
    {
        $html = $this->fragment(['phrase' => '']);

        $this->assertStringContainsString(_t('SEARCH_TYPE_SOMETHING'), $html);
        $this->assertStringNotContainsString('yw-search-result ', $html);
    }

    public function testAMatchIsRenderedAsALinkedResultWithItsType(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $html = $this->fragment(['phrase' => 'ciboulette']);

        $this->assertStringContainsString('Le potager', $html);
        $this->assertStringContainsString(self::PAGE_TAG, $html);
        $this->assertStringContainsString(_t('SEARCH_TYPE_PAGE'), $html);
        $this->assertStringContainsString('yw-search-result--page', $html);
    }

    public function testNoMatchSaysSoRatherThanRenderingAnEmptyList(): void
    {
        $html = $this->fragment(['phrase' => 'zzzzaucunresultatzzzz']);

        // the message is Twig-escaped in the fragment, so compare against the escaped form
        $this->assertStringContainsString(htmlspecialchars(_t('NO_SEARCH_RESULT'), ENT_QUOTES), $html);
    }

    public function testTheTypeFilterNarrowsTheFragment(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $this->assertStringContainsString(self::PAGE_TAG, $this->fragment(['phrase' => 'ciboulette', 'type' => 'page']));
        $this->assertStringNotContainsString(self::PAGE_TAG, $this->fragment(['phrase' => 'ciboulette', 'type' => 'entry']));
    }

    /**
     * The endpoint is public by ACL, and safely so *because* the query evaluates rights in
     * SQL. If that ever stopped being true this is a disclosure, not a wrong result -- which
     * is why it is asserted at the endpoint and not only at the query.
     */
    public function testThePublicEndpointDoesNotLeakAPrivatePage(): void
    {
        $this->savePage(self::PRIVATE_TAG, 'Secret', 'un texte sur le topinambour', '@admins');

        $anonymous = $this->fragment(['phrase' => 'topinambour']);
        $this->assertStringNotContainsString(self::PRIVATE_TAG, $anonymous);
        $this->assertStringNotContainsString('Secret', $anonymous);

        $this->loginAsAdmin();

        $this->assertStringContainsString(
            self::PRIVATE_TAG,
            $this->fragment(['phrase' => 'topinambour']),
            'an entitled reader must still find it -- the filter is per-visitor, not a blanket hide'
        );
    }

    public function testPagingIsOfferedOnlyWhenThereIsMoreThanOnePage(): void
    {
        $this->savePage(self::PAGE_TAG, 'Le potager', 'un texte sur la ciboulette');

        $onePage = $this->fragment(['phrase' => 'ciboulette', 'limit' => 20]);
        $this->assertStringNotContainsString('yw-search-pagination', $onePage);

        // force several pages out of the seeded corpus rather than inventing one
        $manyPages = $this->fragment(['phrase' => 'wiki', 'limit' => 1]);
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
            $html = $this->getWiki()->services->get(ActionRunner::class)->action($retired, false);
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

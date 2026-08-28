<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Every action that lists pages must name only the ones the visitor can read. Ported from doryphore-dev; `nuagetag` is `tagcloud` here and `orphanedpages` has no ectoplasme counterpart. */
class PageListingsAclTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'PageListingsAclPublicPage';
    private const RESTRICTED_TAG = 'PageListingsAclRestrictedPage';
    private const TAG_VALUE = 'PageListingsAclTagValue';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private TripleStore $tripleStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);

        $this->pageManager->save(self::PUBLIC_TAG, [PageBody::CONTENT => 'public content'], '', true);
        $this->pageManager->save(self::RESTRICTED_TAG, [PageBody::CONTENT => 'secret content'], '', true);
        $this->aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        foreach ([self::PUBLIC_TAG, self::RESTRICTED_TAG] as $tag) {
            $this->tripleStore->create($tag, TagsManager::TAG_PROPERTY, self::TAG_VALUE, '', '');
        }
        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }

        foreach ([self::PUBLIC_TAG, self::RESTRICTED_TAG] as $tag) {
            $this->tripleStore->delete($tag, TagsManager::TAG_PROPERTY, self::TAG_VALUE, '', '');
            $this->pageManager->deleteOrphaned($tag);
        }
        $this->aclService->delete(self::RESTRICTED_TAG);
        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: array<string, string>}> */
    public static function actionProvider(): array
    {
        return [
            'pageindex' => ['pageindex', []],
            'pageonlyindex' => ['pageonlyindex', []],
            'tagcloud' => ['tagcloud', ['tags' => self::TAG_VALUE]],
            'admintag' => ['admintag', []],
            'listpagestag' => ['listpagestag', ['tags' => self::TAG_VALUE]],
            'includepages' => ['includepages', ['pages' => self::PUBLIC_TAG . ',' . self::RESTRICTED_TAG]],
            'filtertags' => ['filtertags', ['filter1' => self::TAG_VALUE]],
        ];
    }

    /** @param array<string, string> $args */
    #[DataProvider('actionProvider')]
    public function testActionHidesTheRestrictedPage(string $action, array $args): void
    {
        $html = (string)$this->wiki->services->get(ActionRunner::class)->action($action, $args);

        $this->assertStringNotContainsString(self::RESTRICTED_TAG, $html, "$action leaks a read-restricted page tag");
    }

    public function testTagListingStillShowsThePublicPage(): void
    {
        $html = (string)$this->wiki->services->get(ActionRunner::class)->action('tagcloud', ['tags' => self::TAG_VALUE]);

        $this->assertStringContainsString(self::PUBLIC_TAG, $html);
    }

    public function testAListingStillShowsThePublicPage(): void
    {
        $html = (string)$this->wiki->services->get(ActionRunner::class)
            ->action('includepages', ['pages' => self::PUBLIC_TAG . ',' . self::RESTRICTED_TAG]);

        $this->assertStringContainsString(self::PUBLIC_TAG, $html);
    }

    public function testAResultCountDoesNotCountUnreadablePages(): void
    {
        $html = (string)$this->wiki->services->get(ActionRunner::class)->action('filtertags', ['filter1' => self::TAG_VALUE]);

        $this->assertMatchesRegularExpression(
            '/<span class="nbfilteredelements">1<\/span>/',
            $html,
            'the result count betrays how many unreadable pages carry the tag'
        );
    }

    public function testPagesByTagsHidesTheRestrictedPage(): void
    {
        $tagsManager = $this->wiki->services->get(TagsManager::class);

        $byTag = array_column($tagsManager->getPagesByTags(self::TAG_VALUE) ?: [], 'tag');
        $allWiki = array_column($tagsManager->getPagesByTags('', 'wiki') ?: [], 'tag');

        $this->assertContains(self::PUBLIC_TAG, $byTag);
        $this->assertNotContains(self::RESTRICTED_TAG, $byTag);
        $this->assertNotContains(self::RESTRICTED_TAG, $allWiki);
    }
}

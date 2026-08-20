<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Tags\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Every action that lists pages must name only the ones the visitor can read.
 */
class PageListingsAclTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'PageListingsAclPublicPage';
    private const RESTRICTED_TAG = 'PageListingsAclRestrictedPage';
    private const TAG_VALUE = 'PageListingsAclTagValue';
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    private $wiki;
    private $pageManager;
    private $aclService;
    private $tripleStore;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);

        $this->pageManager->save(self::PUBLIC_TAG, 'public content', '', true);
        $this->pageManager->save(self::RESTRICTED_TAG, 'secret content', '', true);
        $this->aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        foreach ([self::PUBLIC_TAG, self::RESTRICTED_TAG] as $tag) {
            $this->tripleStore->create($tag, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
        }
        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        foreach ([self::PUBLIC_TAG, self::RESTRICTED_TAG] as $tag) {
            $this->tripleStore->delete($tag, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
            $this->pageManager->deleteOrphaned($tag);
        }
        $this->aclService->delete(self::RESTRICTED_TAG);
    }

    public static function actionProvider(): array
    {
        return [
            'pageindex' => ['pageindex', []],
            'pageonlyindex' => ['pageonlyindex', []],
            'orphanedpages' => ['orphanedpages', []],
            'nuagetag' => ['nuagetag', ['tags' => self::TAG_VALUE]],
            'admintag' => ['admintag', []],
        ];
    }

    #[DataProvider('actionProvider')]
    public function testActionHidesTheRestrictedPage(string $action, array $args)
    {
        $html = $this->wiki->Action($action, 1, $args);

        $this->assertStringNotContainsString(self::RESTRICTED_TAG, $html, "$action leaks a read-restricted page tag");
    }

    public function testTagListingStillShowsThePublicPage()
    {
        $html = $this->wiki->Action('nuagetag', 1, ['tags' => self::TAG_VALUE]);

        $this->assertStringContainsString(self::PUBLIC_TAG, $html);
    }

    public function testPagesByTagsHidesTheRestrictedPage()
    {
        $tagsManager = $this->wiki->services->get(TagsManager::class);

        $byTag = array_column($tagsManager->getPagesByTags(self::TAG_VALUE) ?: [], 'tag');
        $allWiki = array_column($tagsManager->getPagesByTags('', 'wiki') ?: [], 'tag');

        $this->assertContains(self::PUBLIC_TAG, $byTag);
        $this->assertNotContains(self::RESTRICTED_TAG, $byTag);
        $this->assertNotContains(self::RESTRICTED_TAG, $allWiki);
    }
}

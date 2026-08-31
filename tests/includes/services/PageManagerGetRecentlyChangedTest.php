<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Recent-change listings must only name pages the caller is allowed to read,
 * whichever surface they reach: the action, the feeds, the comment lists.
 */
class PageManagerGetRecentlyChangedTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'RecentlyChangedPublicPage';
    private const RESTRICTED_TAG = 'RecentlyChangedRestrictedPage';

    public function testAnonymousGetsThePublicPageAndNotTheRestrictedOne()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::PUBLIC_TAG, 'public content', '', true);
        $pageManager->save(self::RESTRICTED_TAG, 'secret content', '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        unset($_SESSION['user']);

        try {
            $tags = array_column($pageManager->getRecentlyChanged(50) ?? [], 'tag');

            $this->assertContains(self::PUBLIC_TAG, $tags);
            $this->assertNotContains(self::RESTRICTED_TAG, $tags);
        } finally {
            $pageManager->deleteOrphaned(self::PUBLIC_TAG);
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }

    public function testTheMinDateVariantFiltersToo()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::RESTRICTED_TAG, 'secret content', '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        unset($_SESSION['user']);

        try {
            $tags = array_column($pageManager->getRecentlyChanged(50, '1970-01-01 00:00:00') ?? [], 'tag');

            $this->assertNotContains(self::RESTRICTED_TAG, $tags);
        } finally {
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }

    public function testACommentIsNotListedWhenItsPageIsUnreadable()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $commentTag = 'Comment' . self::RESTRICTED_TAG;
        $pageManager->save(self::RESTRICTED_TAG, 'secret content', '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        $pageManager->save($commentTag, 'a comment body', self::RESTRICTED_TAG, true);
        unset($_SESSION['user']);

        try {
            $listed = array_column($wiki->LoadRecentComments(50) ?: [], 'comment_on');

            $this->assertNotContains(self::RESTRICTED_TAG, $listed);
        } finally {
            $pageManager->deleteOrphaned($commentTag);
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }
}

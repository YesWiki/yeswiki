<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression test for a read-ACL bypass in PageManager::getReadablePageTags(). */
class PageManagerGetReadablePageTagsTest extends YesWikiTestCase
{
    private const RESTRICTED_TAG = 'PageManagerAclBypassRestrictedPage';

    public function testAnonymousDoesNotSeeReadRestrictedPageTag(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::RESTRICTED_TAG, [PageBody::CONTENT => 'secret content'], '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        unset($_SESSION['user']);

        try {
            $tags = $pageManager->getReadablePageTags();

            $this->assertNotContains(
                self::RESTRICTED_TAG,
                $tags,
                'a read-restricted page tag was returned to an anonymous, non-admin caller (ACL bypass)'
            );
        } finally {
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }
}

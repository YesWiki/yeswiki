<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for a read-ACL bypass in PageManager::getReadablePageTags().
 *
 * The SQL was built as `... WHERE LATEST = 'Y' ORDER BY tag`, and the ACL filter
 * fragment was then appended as `... AND (<aclFragment>)` -- landing *after*
 * `ORDER BY`. MySQL happily parses `ORDER BY tag AND (...)` as a single boolean sort
 * expression rather than a syntax error, so the ACL fragment never filtered any row :
 * every non-admin/anonymous caller got every page tag back, including read-restricted
 * ones. This list is sent verbatim to the browser by the {{aceditor}} action (used by
 * any bazar form field with syntax "wiki-textarea"), so any authenticated non-admin
 * user filling out such a form leaked the names of every read-restricted page.
 */
class PageManagerGetReadablePageTagsTest extends YesWikiTestCase
{
    private const RESTRICTED_TAG = 'PageManagerAclBypassRestrictedPage';

    public function testAnonymousDoesNotSeeReadRestrictedPageTag()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::RESTRICTED_TAG, 'secret content', '', true);
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

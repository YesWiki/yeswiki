<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for anonymous disclosure via the legacy {{recentchangesrssplus}} action (actions/recentchangesrssplus.php, formerly tools/rss): it queried every latest, non-comment page and wrote a 500-char body excerpt into the public RSS feed with no per-page read-ACL check, unlike its modern sibling RecentChangesRssAction.php.
 */
class RecentchangesrssplusActionTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'RecentchangesrssplusPublicPage';
    private const RESTRICTED_TAG = 'RecentchangesrssplusRestrictedPage';
    private const PUBLIC_MARKER = 'RECENTCHANGESRSSPLUS_PUBLIC_MARKER';
    private const RESTRICTED_SECRET = 'RECENTCHANGESRSSPLUS_RESTRICTED_SECRET_MARKER';

    public function testFeedListsAllPagesButRedactsUnreadableOnes(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::PUBLIC_TAG, [PageBody::CONTENT => self::PUBLIC_MARKER], '', true);
        $pageManager->save(self::RESTRICTED_TAG, [PageBody::CONTENT => self::RESTRICTED_SECRET], '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');

        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setMethod('xml');
        $output = '';

        try {
            try {
                $action = new \YesWiki\Content\Action\RecentchangesrssplusAction();
                $action->setServices($wiki->services);
                $vars = [];
                $action->setArguments($vars);
                $action->setOutput($output);
                $output .= $action->run();
            } catch (ExitException $e) {
            }

            $redactedTag = substr(self::RESTRICTED_TAG, 0, 3) . '___';
            $this->assertStringContainsString(
                $redactedTag,
                $output,
                'the restricted page should still be listed (redacted) in the feed'
            );

            $this->assertStringNotContainsString(self::RESTRICTED_TAG, $output);
            $this->assertStringNotContainsString(self::RESTRICTED_SECRET, $output);
            $this->assertStringContainsString(_t('RSS_HIDDEN_CONTENT'), $output);

            $this->assertStringContainsString(self::PUBLIC_TAG, $output);
            $this->assertStringContainsString(self::PUBLIC_MARKER, $output);
        } finally {
            $pageManager->deleteOrphaned(self::PUBLIC_TAG);
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }
}

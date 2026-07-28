<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Exception\ExitException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for anonymous disclosure via the legacy {{recentchangesrssplus}}
 * action (actions/recentchangesrssplus.php, formerly tools/rss): it queried every latest,
 * non-comment page and wrote a 500-char body excerpt into the public RSS feed with
 * no per-page read-ACL check, unlike its modern sibling RecentChangesRssAction.php.
 * The fix keeps every page's entry in the feed (so the feed still reflects all
 * recent changes) but redacts the tag and body for pages the requester can't read.
 */
class RecentchangesrssplusActionTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'RecentchangesrssplusPublicPage';
    private const RESTRICTED_TAG = 'RecentchangesrssplusRestrictedPage';
    private const PUBLIC_MARKER = 'RECENTCHANGESRSSPLUS_PUBLIC_MARKER';
    private const RESTRICTED_SECRET = 'RECENTCHANGESRSSPLUS_RESTRICTED_SECRET_MARKER';

    public function testFeedListsAllPagesButRedactsUnreadableOnes()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::PUBLIC_TAG, self::PUBLIC_MARKER, '', true);
        $pageManager->save(self::RESTRICTED_TAG, self::RESTRICTED_SECRET, '', true);
        $aclService->save(self::RESTRICTED_TAG, 'read', '@admins');

        $wiki->method = 'xml';
        $output = '';
        $vars = ['plugin_output_new' => &$output];

        try {
            try {
                $wiki->runFileInBuffer('actions/recentchangesrssplus.php', $vars);
            } catch (ExitException $e) {
                // recentchangesrssplus.php ends with $this->exit(), which throws in CLI;
                // $output was already populated by reference before the throw.
            }

            // the restricted page must still appear in the feed (all changes listed)...
            $redactedTag = substr(self::RESTRICTED_TAG, 0, 3) . '___';
            $this->assertStringContainsString(
                $redactedTag,
                $output,
                'the restricted page should still be listed (redacted) in the feed'
            );
            // ...but its real tag and body content must not leak
            $this->assertStringNotContainsString(self::RESTRICTED_TAG, $output);
            $this->assertStringNotContainsString(self::RESTRICTED_SECRET, $output);
            $this->assertStringContainsString(_t('RSS_HIDDEN_CONTENT'), $output);

            // the public page's real tag and body must be shown, unredacted
            $this->assertStringContainsString(self::PUBLIC_TAG, $output);
            $this->assertStringContainsString(self::PUBLIC_MARKER, $output);
        } finally {
            $pageManager->deleteOrphaned(self::PUBLIC_TAG);
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }
}

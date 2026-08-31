<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The public RSS feed must list the pages the requester can read and nothing else:
 * no tag, no body and no placeholder standing in for a page they cannot read.
 */
class RecentchangesrssplusActionTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'RecentchangesrssplusPublicPage';
    private const RESTRICTED_TAG = 'RecentchangesrssplusRestrictedPage';
    private const PUBLIC_MARKER = 'RECENTCHANGESRSSPLUS_PUBLIC_MARKER';
    private const RESTRICTED_SECRET = 'RECENTCHANGESRSSPLUS_RESTRICTED_SECRET_MARKER';

    public function testFeedHidesPagesTheRequesterCannotRead()
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
                $wiki->runFileInBuffer('tools/rss/actions/recentchangesrssplus.php', $vars);
            } catch (ExitException $e) {
            }

            $this->assertStringNotContainsString(self::RESTRICTED_TAG, $output);
            $this->assertStringNotContainsString(self::RESTRICTED_SECRET, $output);
            $this->assertStringNotContainsString(substr(self::RESTRICTED_TAG, 0, 3) . '___', $output);

            $this->assertStringContainsString(self::PUBLIC_TAG, $output);
            $this->assertStringContainsString(self::PUBLIC_MARKER, $output);
        } finally {
            while (ob_get_level() > 1) {
                ob_end_clean();
            }
            $pageManager->deleteOrphaned(self::PUBLIC_TAG);
            $pageManager->deleteOrphaned(self::RESTRICTED_TAG);
            $aclService->delete(self::RESTRICTED_TAG);
        }
    }
}

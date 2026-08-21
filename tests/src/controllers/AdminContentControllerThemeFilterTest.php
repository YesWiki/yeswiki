<?php

namespace YesWiki\Test\Core\Controller;

use YesWiki\Admin\Api\AdminPagesApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 02 (versioned metadata column on pages): the admin content list's theme filter used to query the non-versioned METADATA_PROPERTY triple; it now queries pages.metadata directly.
 */
class AdminContentControllerThemeFilterTest extends YesWikiTestCase
{
    private const THEMED_TAG = 'AdminContentThemeFilterRegressionThemed';
    private const UNTHEMED_TAG = 'AdminContentThemeFilterRegressionUntheme';

    /**
     * @return array{string, list<mixed>, string, list<mixed>} [where, whereParams, having, havingParams]
     */
    private function buildWhere(AdminPagesApiController $controller, DbService $db, string $themeFilter): array
    {
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildWhere');
        $method->setAccessible(true);

        return $method->invoke($controller, $db, '', 'all', '', '', '', $themeFilter);
    }

    public function testThemeFilterMatchesPagesByTheirOwnMetadataColumn(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $dbService = $wiki->services->get(DbService::class);
        $controller = $wiki->services->get(AdminPagesApiController::class);

        $favoriteTheme = $wiki->services->get(ThemeManager::class)->getFavoriteTheme();

        $filterTheme = $favoriteTheme === 'colibris' ? 'margot' : 'colibris';

        try {
            $pageManager->save(self::THEMED_TAG, [PageBody::CONTENT => 'themed page'], '', true);
            $pageManager->setMetadata(self::THEMED_TAG, ['theme' => $filterTheme]);
            $pageManager->save(self::UNTHEMED_TAG, [PageBody::CONTENT => 'untheme page'], '', true);

            [$whereClause, $whereParams] = $this->buildWhere($controller, $dbService, $filterTheme);

            $pT = $dbService->prefixTable('pages');
            $matchingTags = array_column(
                $dbService->loadAll("SELECT tag FROM {$pT} p WHERE p.latest = 'Y' AND {$whereClause}", $whereParams),
                'tag'
            );

            $this->assertContains(self::THEMED_TAG, $matchingTags, "the theme filter should match a page whose pages.metadata JSON has \"theme\":\"$filterTheme\"");
            $this->assertNotContains(self::UNTHEMED_TAG, $matchingTags, 'the theme filter should not match a page with no metadata at all');
        } finally {
            $pageManager->deleteOrphaned(self::THEMED_TAG);
            $pageManager->deleteOrphaned(self::UNTHEMED_TAG);
        }
    }
}

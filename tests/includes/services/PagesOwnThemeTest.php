<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Templates\Controller\ThemeController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The theme selector can tell which pages keep their own theme instead of the site's, and hand one back to the site.
 */
class PagesOwnThemeTest extends YesWikiTestCase
{
    private const WITH_THEME = 'PagesOwnThemeTestWithTheme';
    private const WITHOUT_THEME = 'PagesOwnThemeTestWithoutTheme';
    private const RESET = 'PagesOwnThemeTestReset';

    private $pageManager;
    private $themeManager;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $this->pageManager = $wiki->services->get(PageManager::class);
        $this->themeManager = $wiki->services->get(ThemeManager::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        foreach ([self::WITH_THEME, self::WITHOUT_THEME, self::RESET] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
        }
    }

    private function createPage(string $tag, ?array $metadata = null): void
    {
        $this->pageManager->save($tag, 'body', '', true);
        if ($metadata !== null) {
            $this->pageManager->setMetadata($tag, $metadata);
        }
    }

    public function testAPageWithItsOwnThemeIsReportedWithThatTheme()
    {
        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);

        $this->assertSame(
            ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html'],
            $this->themeManager->getOwnTheme(self::WITH_THEME)
        );
    }

    public function testAPageWithOnlyAMenuHasNoThemeOfItsOwn()
    {
        $this->createPage(self::WITHOUT_THEME, ['PageMenu' => 'SomeMenu']);

        $this->assertNull($this->themeManager->getOwnTheme(self::WITHOUT_THEME));
    }

    public function testOnlyPagesWithTheirOwnThemeAreCounted()
    {
        $before = $this->themeManager->countPagesWithOwnTheme();

        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.tpl.html']);
        $this->createPage(self::WITHOUT_THEME, ['PageMenu' => 'SomeMenu']);

        $this->assertSame($before + 1, $this->themeManager->countPagesWithOwnTheme());
    }

    public function testAPageHandedBackToTheSiteLosesItsThemeButKeepsItsMenu()
    {
        $this->createPage(self::RESET, ['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.tpl.html', 'favorite_preset' => 'fun.css', 'PageMenu' => 'SomeMenu']);
        $before = $this->themeManager->countPagesWithOwnTheme();

        $this->themeManager->useSiteTheme(self::RESET);

        $this->assertNull($this->themeManager->getOwnTheme(self::RESET));
        $this->assertSame('SomeMenu', $this->pageManager->getMetadata(self::RESET)['PageMenu'] ?? null);
        $this->assertEmpty($this->pageManager->getMetadata(self::RESET)['favorite_preset'] ?? null);
        $this->assertSame($before - 1, $this->themeManager->countPagesWithOwnTheme());
    }

    public function testAnAdminChoosingTheSiteThemeOnAPageWithItsOwnIsTold()
    {
        $wiki = $this->getWiki();
        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);
        $wiki->SetPage($this->pageManager->getOne(self::WITH_THEME));
        $_SESSION['user'] = ['name' => $wiki->services->get(GroupManager::class)->getMembers('admins')[0]];

        $html = $wiki->services->get(ThemeController::class)->showFormThemeSelector('selector');

        $this->assertStringContainsString(htmlspecialchars(_t('TEMPLATE_PAGE_HAS_OWN_THEME', ['theme' => 'margot / margot-fun / full-page'])), $html);
        $this->assertStringContainsString('value="useSiteTheme"', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('TEMPLATE_PAGES_WITH_OWN_THEME', ['count' => $this->themeManager->countPagesWithOwnTheme()])), $html);
    }
}

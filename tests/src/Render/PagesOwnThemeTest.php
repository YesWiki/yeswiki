<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Render\Service\ThemeSelectorRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The theme selector tells which pages keep their own theme instead of the site's, and hands one back to the site. */
class PagesOwnThemeTest extends YesWikiTestCase
{
    private const WITH_THEME = 'PagesOwnThemeTestWithTheme';
    private const WITHOUT_THEME = 'PagesOwnThemeTestWithoutTheme';
    private const RESET = 'PagesOwnThemeTestReset';

    private PageManager $pageManager;
    private ThemeManager $themeManager;
    private ?string $previousTag = null;

    protected function setUp(): void
    {
        $services = $this->getWiki()->services;
        $this->pageManager = $services->get(PageManager::class);
        $this->themeManager = $services->get(ThemeManager::class);
        $this->previousTag = $services->get(PageContext::class)->getTag();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $services->get(PageContext::class)->setTag((string)$this->previousTag);
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach ([self::WITH_THEME, self::WITHOUT_THEME, self::RESET] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function createPage(string $tag, array $metadata): void
    {
        $this->pageManager->save($tag, [PageBody::CONTENT => 'body'], '', true);
        $this->pageManager->setMetadata($tag, $metadata);
    }

    public function testAPageWithItsOwnThemeIsReportedWithThatTheme(): void
    {
        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);

        $this->assertSame(
            ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html'],
            $this->themeManager->getOwnTheme(self::WITH_THEME)
        );
    }

    public function testAPageWithOnlyAFooterHasNoThemeOfItsOwn(): void
    {
        $this->createPage(self::WITHOUT_THEME, ['PageFooter' => 'SomeFooter']);

        $this->assertNull($this->themeManager->getOwnTheme(self::WITHOUT_THEME));
    }

    public function testOnlyPagesWithTheirOwnThemeAreCounted(): void
    {
        $before = $this->themeManager->countPagesWithOwnTheme();

        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.tpl.html']);
        $this->createPage(self::WITHOUT_THEME, ['PageFooter' => 'SomeFooter']);

        $this->assertSame($before + 1, $this->themeManager->countPagesWithOwnTheme());
    }

    public function testAPageHandedBackToTheSiteLosesItsThemeButKeepsItsFooter(): void
    {
        $this->createPage(self::RESET, ['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.tpl.html', 'favorite_preset' => 'fun.css', 'PageFooter' => 'SomeFooter']);
        $before = $this->themeManager->countPagesWithOwnTheme();

        $this->themeManager->useSiteTheme(self::RESET);

        $this->assertNull($this->themeManager->getOwnTheme(self::RESET));
        $this->assertSame('SomeFooter', $this->pageManager->getMetadata(self::RESET)['PageFooter'] ?? null);
        $this->assertEmpty($this->pageManager->getMetadata(self::RESET)['favorite_preset'] ?? null);
        $this->assertSame($before - 1, $this->themeManager->countPagesWithOwnTheme());
    }

    public function testAnAdminChoosingTheSiteThemeOnAPageWithItsOwnIsTold(): void
    {
        $services = $this->getWiki()->services;
        $this->createPage(self::WITH_THEME, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);
        $services->get(PageContext::class)->setTag(self::WITH_THEME);
        $aclService = $services->get(AclService::class);
        $admin = current(array_filter($services->get(UserManager::class)->getAll(), fn ($user) => $aclService->isAdmin($user['name'])));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $services->get(AuthenticationService::class)->login($admin);

        $html = $services->get(ThemeSelectorRenderer::class)->showFormThemeSelector('selector');

        $this->assertStringContainsString(htmlspecialchars(_t('TEMPLATE_PAGE_HAS_OWN_THEME', ['theme' => 'margot / margot-fun / full-page'])), $html);
        $this->assertStringContainsString('value="useSiteTheme"', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('TEMPLATE_PAGES_WITH_OWN_THEME', ['count' => $this->themeManager->countPagesWithOwnTheme()])), $html);
    }
}

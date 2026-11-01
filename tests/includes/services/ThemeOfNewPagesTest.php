<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A new page follows the site theme unless the page it was linked from has a theme of its own.
 */
class ThemeOfNewPagesTest extends YesWikiTestCase
{
    private const PARENT = 'ThemeOfNewPagesTestParent';
    private const CHILD = 'ThemeOfNewPagesTestChild';

    private $wiki;
    private $pageManager;
    private $previousConfig;
    private $previousTag;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->previousTag = $this->wiki->tag;
        $this->previousConfig = $this->wiki->config;
        $this->wiki->config['favorite_theme'] = 'margot';
        $this->wiki->config['favorite_style'] = 'margot.css';
        $this->wiki->config['favorite_squelette'] = '1col.tpl.html';
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->wiki->config = $this->previousConfig;
        $this->wiki->tag = $this->previousTag;
        $this->wiki->request->request->replace([]);
        $this->wiki->request->query->replace([]);
    }

    private function cleanUp(): void
    {
        foreach ([self::PARENT, self::CHILD] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
        }
    }

    private function createChildFromItsLink(array $linkParams): ?array
    {
        $this->wiki->request->query->replace(['wiki' => self::CHILD . '/edit']);
        $this->wiki->request->request->replace(['body' => 'child'] + $linkParams);
        $this->pageManager->save(self::CHILD, 'child', '', true);

        return $this->pageManager->getMetadata(self::CHILD);
    }

    public function testALinkFromAPageWithoutItsOwnThemeCarriesNoTheme()
    {
        $this->pageManager->save(self::PARENT, 'parent', '', true);
        $this->wiki->tag = self::PARENT;

        $params = $this->wiki->ParamsForNewPageLink();

        $this->assertSame(1, $params['newpage']);
        foreach (['theme', 'style', 'squelette', 'bgimg'] as $key) {
            $this->assertArrayNotHasKey($key, $params);
        }
    }

    public function testALinkFromAPageWithItsOwnThemeCarriesThatTheme()
    {
        $this->pageManager->save(self::PARENT, 'parent', '', true);
        $this->pageManager->setMetadata(self::PARENT, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);
        $this->wiki->tag = self::PARENT;

        $params = $this->wiki->ParamsForNewPageLink();

        $this->assertSame('margot', $params['theme']);
        $this->assertSame('margot-fun.css', $params['style']);
        $this->assertSame('full-page.tpl.html', $params['squelette']);
    }

    public function testAPageCreatedFromALinkOnAPageWithoutItsOwnThemeFollowsTheSiteTheme()
    {
        $this->pageManager->save(self::PARENT, 'parent', '', true);
        $this->wiki->tag = self::PARENT;

        $metadata = $this->createChildFromItsLink($this->wiki->ParamsForNewPageLink());

        $this->assertArrayNotHasKey('theme', $metadata ?? []);
    }

    public function testAPageCreatedFromALinkKeepsTheMenuOfThePageItCameFrom()
    {
        $this->pageManager->save(self::PARENT, 'parent', '', true);
        $this->pageManager->setMetadata(self::PARENT, ['PageMenu' => 'ThemeOfNewPagesTestMenu']);
        $this->wiki->tag = self::PARENT;

        $metadata = $this->createChildFromItsLink($this->wiki->ParamsForNewPageLink());

        $this->assertSame('ThemeOfNewPagesTestMenu', $metadata['PageMenu'] ?? null);
        $this->assertArrayNotHasKey('theme', $metadata);
    }

    public function testAPageCreatedFromALinkOnAPageWithItsOwnThemeInheritsIt()
    {
        $this->pageManager->save(self::PARENT, 'parent', '', true);
        $this->pageManager->setMetadata(self::PARENT, ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html']);
        $this->wiki->tag = self::PARENT;

        $metadata = $this->createChildFromItsLink($this->wiki->ParamsForNewPageLink());

        $this->assertSame('margot-fun.css', $metadata['style'] ?? null);
        $this->assertSame('full-page.tpl.html', $metadata['squelette'] ?? null);
    }
}

<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A `{{nav}}` icon is separated from its label, and is only drawn when the call asks for one. */
class NavIconSpacingTest extends YesWikiTestCase
{
    private const MENU_TAG = 'MenuIconSpacingTest';

    public static function setUpBeforeClass(): void
    {
        self::getWiki()->services->get(MenuManager::class)->create('Icon spacing', [
            new MenuNode(id: 'n1', label: 'Accueil', link: 'PagePrincipale', iconSource: MenuNode::ICON_SPRITE, iconValue: 'home'),
            new MenuNode(id: 'n2', label: 'Bac à sable', link: 'BacASable', iconSource: MenuNode::ICON_SPRITE, iconValue: 'pencil'),
        ], self::MENU_TAG);
    }

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(MenuManager::class)->delete(self::MENU_TAG);
    }

    public function testAnIconIsSeparatedFromItsLabel(): void
    {
        $html = $this->nav(['menu' => self::MENU_TAG, 'showicons' => 'true']);

        $this->assertStringContainsString('Accueil', $html, 'the nav did not render at all');
        $this->assertMatchesRegularExpression(
            '/<\/svg>\s+Accueil/',
            $html,
            'the icon runs into its label: the separating space was never added'
        );
    }

    /** The icons are the node's own now, and the call says whether this placement draws them. */
    public function testIconsAreDrawnOnlyWhereTheyAreAskedFor(): void
    {
        $html = $this->nav(['menu' => self::MENU_TAG]);

        $this->assertStringContainsString('Accueil', $html);
        $this->assertStringNotContainsString('<svg', $html, 'no icon was asked for');
    }

    /** With no labels, the glyph carries the entry on its own. */
    public function testAnIconOnlyPlacementDrawsNoLabel(): void
    {
        $html = $this->nav(['menu' => self::MENU_TAG, 'showicons' => 'true', 'showlabels' => 'false']);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('Accueil', $html);
    }

    /** A call naming no menu draws nothing, rather than an empty shell. */
    public function testACallNamingNoMenuDrawsNothing(): void
    {
        $this->assertSame('', $this->nav([]));
        $this->assertSame('', $this->nav(['menu' => 'MenuThatWasNeverMade']));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function nav(array $arguments): string
    {
        return (string)self::getWiki()->services->get(Performer::class)->run('nav', 'action', $arguments);
    }
}

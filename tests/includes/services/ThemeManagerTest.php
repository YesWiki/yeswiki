<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A form field named like a theme setting must not break the page that receives it.
 */
class ThemeManagerTest extends YesWikiTestCase
{
    private $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
    }

    protected function tearDown(): void
    {
        $this->wiki->request->request->replace([]);
    }

    public function testArrayValuesForThemeKeysAreIgnored()
    {
        $themeManager = $this->wiki->services->get(ThemeManager::class);
        $themeManager->loadTemplates([]);
        $expected = [
            $themeManager->getFavoriteTheme(),
            $themeManager->getFavoriteSquelette(),
            $themeManager->getFavoriteStyle(),
            $themeManager->getFavoritePreset(),
        ];

        $this->wiki->request->request->replace([
            'theme' => ['margot', 'bootstrap'],
            'squelette' => ['1col.tpl.html'],
            'style' => ['margot.css'],
            'preset' => ['blue.css'],
        ]);
        $themeManager->loadTemplates([]);

        $this->assertSame($expected, [
            $themeManager->getFavoriteTheme(),
            $themeManager->getFavoriteSquelette(),
            $themeManager->getFavoriteStyle(),
            $themeManager->getFavoritePreset(),
        ]);
    }
}

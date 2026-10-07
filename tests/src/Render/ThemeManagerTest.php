<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Form fields named like theme settings. */
class ThemeManagerTest extends YesWikiTestCase
{
    protected function tearDown(): void
    {
        $request = $this->getWiki()->services->get(CurrentRequest::class)->get();
        foreach (['theme', 'squelette', 'style', 'preset'] as $key) {
            $request->request->remove($key);
        }
        parent::tearDown();
    }

    public function testArrayValuesForThemeKeysAreIgnored(): void
    {
        $themeManager = $this->getWiki()->services->get(ThemeManager::class);
        $themeManager->loadTemplates([]);
        $expected = [
            $themeManager->getFavoriteTheme(),
            $themeManager->getFavoriteSquelette(),
            $themeManager->getFavoriteStyle(),
            $themeManager->getFavoritePreset(),
        ];

        $request = $this->getWiki()->services->get(CurrentRequest::class)->get();
        $request->request->add([
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

<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The Font Awesome names Doryphore content carries draw a sprite glyph, and an unmapped one still draws something. */
class LegacyIconFallbackTest extends YesWikiTestCase
{
    private function symbolOf(?string $html): ?string
    {
        return preg_match('/<use href="[^"]*#([^"]+)"/', (string)$html, $m) ? $m[1] : null;
    }

    /** @return list<string> */
    private function spriteSymbols(): array
    {
        preg_match_all('/<symbol id="([^"]+)"/', (string)file_get_contents(YESWIKI_PROGRAM_DIR . '/src/assets/icons.svg'), $m);

        return $m[1];
    }

    public function testDoryphoreIconsResolveToTheSprite(): void
    {
        $engine = $this->getWiki()->services->get(TemplateEngine::class);
        $expected = [
            'fas fa-podcast' => 'microphone-2',
            'fas fa-qrcode' => 'qrcode',
            'far fa-smile-wink' => 'mood-wink',
            'fab fa-flickr' => 'brand-flickr',
            'fas fa-tachometer-alt' => 'gauge',
        ];
        foreach ($expected as $class => $symbol) {
            $this->assertSame($symbol, $this->symbolOf($engine->legacyIconToSprite($class)), $class);
        }
    }

    public function testAGeneratorExtraIsKnownWithoutAMapEntry(): void
    {
        $engine = $this->getWiki()->services->get(TemplateEngine::class);
        $this->assertSame('sort-ascending-letters', $this->symbolOf($engine->legacyIconToSprite('fa-sort-ascending-letters')));
    }

    public function testEveryMappedIconIsInTheSprite(): void
    {
        $map = json_decode((string)file_get_contents(YESWIKI_PROGRAM_DIR . '/src/icon-map.json'), true);
        unset($map['__comment']);
        $this->assertSame([], array_values(array_diff(array_unique(array_values($map)), $this->spriteSymbols())));
    }

    public function testAnUnmappedFontAwesomeIconFallsBackToTheNeutralGlyph(): void
    {
        $helper = $this->getWiki()->services->get(TemplateHelperService::class);
        $this->assertContains('circle-dot', $this->spriteSymbols());
        $this->assertSame('circle-dot', $this->symbolOf($helper->formatIconHtml('fas fa-nonexistent')));
        $this->assertSame('circle-dot', $this->symbolOf($helper->formatIconHtml('nonexistent')));
        $this->assertNull($this->getWiki()->services->get(TemplateEngine::class)->legacyIconToSprite('fas fa-nonexistent'));
    }

    public function testAnotherClassListKeepsItsOwnMarkup(): void
    {
        $helper = $this->getWiki()->services->get(TemplateHelperService::class);
        $this->assertSame('<i class="my-theme-icon icon-x"></i>', $helper->formatIconHtml('my-theme-icon icon-x'));
        $this->assertSame('<i class="a &quot;b"></i>', $helper->formatIconHtml('a "b'));
    }

    public function testAnIconOnlyButtonDrawsItsGlyph(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $html = $wiki->services->get(MarkdownFormatterService::class)->format('{{button icon="fas fa-podcast" nobtn="1" link="x"}}');

        $this->assertMatchesRegularExpression('/<a [^>]*href="[^"]*x"[^>]*><svg class="yw-icon"[^>]*><use href="[^"]*#microphone-2"\/><\/svg><\/a>/', $html);
    }
}

<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 09 (yw-* core CSS/JS foundation, ADR-0004/0005): the yw-core design system and htmx must be declared on every page.
 */
class CoreAssetsTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    /** Everything CoreAssets declares, as the head block would emit it. */
    private function coreAssets(YesWikiRuntime $wiki): string
    {
        $registry = $wiki->services->get(\YesWiki\Kernel\Service\AssetRegistry::class);
        $registry->drain();

        (new \YesWiki\Render\Service\CoreAssets(
            $registry,
            $wiki->services->get(\YesWiki\Render\Service\ThemeManager::class),
            $wiki->services->get(\YesWiki\Render\Service\CustomCssService::class),
            $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class),
            $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class),
            $wiki->services->get(\Symfony\Component\Security\Csrf\CsrfTokenManager::class),
            $wiki->services->get(\YesWiki\Kernel\Service\LanguageService::class),
            $wiki->services->get(\YesWiki\Files\Service\Storage::class),
        ))->register();

        return $registry->drain()->toHtml();
    }

    #[Depends('testWikiExisting')]
    public function testYwCoreCssIsLoadedWithoutBootstrap(YesWikiRuntime $wiki): void
    {
        $output = $this->coreAssets($wiki);
        $this->assertStringContainsString('styles/yw-core.css', $output);
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap CSS must not load anymore.');
    }

    #[Depends('testWikiExisting')]
    public function testHtmxAndYwCoreJsAreLoadedGlobally(YesWikiRuntime $wiki): void
    {
        $output = $this->coreAssets($wiki);
        $this->assertStringContainsString('javascripts/vendor/htmx/htmx.min.js', $output);
        $this->assertStringContainsString('javascripts/yw-core.js', $output);
        $this->assertStringContainsString('javascripts/yw-datatable.js', $output);
        $this->assertStringContainsString('javascripts/yw-autocomplete.js', $output);
        $this->assertStringNotContainsString('jquery', $output, 'ticket 16: jQuery must not load globally anymore.');
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap JS must not load anymore.');
    }

    /** Ticket 14 regression. */
    #[Depends('testWikiExisting')]
    public function testTheInitialiserHelpersLoadBeforeEverythingThatCallsThem(YesWikiRuntime $wiki): void
    {
        $output = $this->coreAssets($wiki);

        $this->assertMatchesRegularExpression(
            '#<script src="[^"]*javascripts/yw-init\.js[^"]*"></script>#',
            $output,
            'yw-init.js must be emitted, and without defer: a deferred definition would run after its callers'
        );

        preg_match_all('#<script src="([^"]*)"([^>]*)>#', $output, $tags, PREG_SET_ORDER);
        $initPosition = null;
        foreach ($tags as $index => [, $src, $attributes]) {
            if (str_contains($src, 'javascripts/yw-init.js')) {
                $initPosition = $index;
                continue;
            }
            if ($initPosition === null) {
                $this->assertStringNotContainsString(
                    'defer',
                    $attributes,
                    "$src is emitted before yw-init.js and deferred, so it would run first"
                );
                $this->assertStringNotContainsString('type="module"', $attributes, "$src precedes yw-init.js");
            }
        }
        $this->assertNotNull($initPosition);
    }

    /** The point of ticket 15. */
    #[Depends('testWikiExisting')]
    public function testAnAssetDeclaredByThePageBodyEndsUpInTheHead(YesWikiRuntime $wiki): void
    {
        $registry = $wiki->services->get(\YesWiki\Kernel\Service\AssetRegistry::class);
        $registry->drain();
        $registry->addCssFile('styles/yw-core.css');

        $page = $wiki->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>page content</p>');

        $headEnd = strpos($page, '</head>');
        $stylesheet = strpos($page, 'styles/yw-core.css');
        $this->assertNotFalse($headEnd, 'the skeleton must render a head');
        $this->assertNotFalse($stylesheet, 'the declared stylesheet must be emitted');
        $this->assertLessThan($headEnd, $stylesheet, 'it must be emitted inside <head>, not after the body');
        $this->assertStringContainsString('<p>page content</p>', $page);
    }
}

<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 09 (yw-* core CSS/JS foundation, ADR-0004/0005): the yw-core
 * design system and htmx must be declared on every page.
 *
 * Ticket 15 replaced `{{linkstyle}}`/`{{linkjavascript}}` with CoreAssets, which the render
 * pipeline runs before the handler, and one emission point in the squelette's head block.
 */
class CoreAssetsTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    /**
     * Everything CoreAssets declares, as the head block would emit it.
     *
     * A fresh CoreAssets per call on purpose: "register once per request" is instance state,
     * and the container hands out a shared instance, so tests sharing it would silently
     * depend on their order -- the first one to run would drain the registry and leave the
     * rest asserting against nothing.
     */
    private function coreAssets(YesWikiRuntime $wiki): string
    {
        $registry = $wiki->services->get(\YesWiki\Kernel\Service\AssetRegistry::class);
        $registry->drain();

        (new \YesWiki\Render\Service\CoreAssets(
            $registry,
            $wiki->services->get(\YesWiki\Render\Service\ThemeManager::class),
            $wiki->services->get(\YesWiki\Content\Service\PageManager::class),
            $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class),
            $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class),
            $wiki->services->get(\Symfony\Component\Security\Csrf\CsrfTokenManager::class),
        ))->register();

        return $registry->drain()->toHtml();
    }

    #[Depends('testWikiExisting')]
    public function testYwCoreCssIsLoadedWithoutBootstrap(YesWikiRuntime $wiki)
    {
        $output = $this->coreAssets($wiki);
        $this->assertStringContainsString('styles/yw-core.css', $output);
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap CSS must not load anymore.');
    }

    #[Depends('testWikiExisting')]
    public function testHtmxAndYwCoreJsAreLoadedGlobally(YesWikiRuntime $wiki)
    {
        $output = $this->coreAssets($wiki);
        $this->assertStringContainsString('javascripts/vendor/htmx/htmx.min.js', $output);
        $this->assertStringContainsString('javascripts/yw-core.js', $output);
        $this->assertStringContainsString('javascripts/yw-datatable.js', $output);
        $this->assertStringContainsString('javascripts/yw-autocomplete.js', $output);
        $this->assertStringNotContainsString('jquery', $output, 'ticket 16: jQuery must not load globally anymore.');
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap JS must not load anymore.');
    }

    /**
     * Ticket 14 regression. ~25 initialisers call ywInit()/ywInitEach() at their top level, and
     * `templates/aceditor.twig` loads one of them as a module. If the file defining those
     * helpers is emitted after any of them -- or carries `defer`, which would put it in the
     * same queue -- every one of them dies with a ReferenceError before the page is usable.
     *
     * The original failure was subtler than ordering and is the reason this lives in its own
     * file: the helpers were added to `yeswiki-base-no-defer.js`, whose URL is cache-busted
     * with `?v={yeswiki_release}` and therefore does not change when the file's contents do.
     * Browsers holding the previous copy kept serving a version with no ywInit in it.
     */
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

    /**
     * The point of ticket 15. An asset declared while the *page body* renders has to end up in
     * <head>, which is only possible because the skeleton's `head` block is rendered after its
     * `body` block. Before this, `{{linkstyle}}` ran in <head> before the page had rendered
     * anything, so page stylesheets were flushed at </body> instead -- painted content first,
     * then its styles.
     */
    #[Depends('testWikiExisting')]
    public function testAnAssetDeclaredByThePageBodyEndsUpInTheHead(YesWikiRuntime $wiki): void
    {
        $registry = $wiki->services->get(\YesWiki\Kernel\Service\AssetRegistry::class);
        $registry->drain();
        $registry->addCssFile('styles/bazar/bazar.css');

        $page = $wiki->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>page content</p>');

        $headEnd = strpos($page, '</head>');
        $stylesheet = strpos($page, 'styles/bazar/bazar.css');
        $this->assertNotFalse($headEnd, 'the skeleton must render a head');
        $this->assertNotFalse($stylesheet, 'the declared stylesheet must be emitted');
        $this->assertLessThan($headEnd, $stylesheet, 'it must be emitted inside <head>, not after the body');
        $this->assertStringContainsString('<p>page content</p>', $page);
    }
}

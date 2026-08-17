<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Asset\AssetEntry;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 14: assets are declared by a render rather than accumulated by a request.
 *
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
class AssetRegistryTest extends YesWikiTestCase
{
    private function registry(): AssetRegistry
    {
        $registry = $this->getWiki()->services->get(AssetRegistry::class);
        $this->assertInstanceOf(AssetRegistry::class, $registry);

        $registry->drain();

        return $registry;
    }

    public function testCaptureReturnsWhatWasDeclaredInside(): void
    {
        $registry = $this->registry();

        $captured = $registry->capture(function () use ($registry): void {
            $registry->addCssFile('styles/yw-core.css');
        });

        $this->assertSame(1, $captured->count());
        $this->assertStringContainsString('styles/yw-core.css', $captured->toHtml());
    }

    public function testCaptureDoesNotSeeWhatWasDeclaredOutsideIt(): void
    {
        $registry = $this->registry();
        $registry->addCssFile('styles/yw-core.css');

        $captured = $registry->capture(function () use ($registry): void {
            $registry->addJsFile('javascripts/yw-core.js');
        });

        $this->assertSame(1, $captured->count());
        $this->assertStringContainsString('javascripts/yw-core.js', $captured->toHtml());
        $this->assertStringNotContainsString('styles/yw-core.css', $captured->toHtml());
    }

    /**
     * A fragment is self-contained: its assets go into the response, not onto the page that happens to be rendering.
     */
    public function testCapturedAssetsNeverReachThePage(): void
    {
        $registry = $this->registry();

        $registry->capture(function () use ($registry): void {
            $registry->addJsFile('javascripts/yw-core.js');
        });

        $this->assertTrue($registry->drain()->isEmpty(), 'a captured asset must not be emitted by the page');
    }

    public function testANestedScopeDoesNotLeakIntoItsParent(): void
    {
        $registry = $this->registry();
        $inner = new \YesWiki\Kernel\Asset\AssetSet();

        $outer = $registry->capture(function () use ($registry, &$inner): void {
            $registry->addCssFile('styles/yw-core.css');
            $inner = $registry->capture(function () use ($registry): void {
                $registry->addJsFile('javascripts/yw-core.js');
            });
        });

        $this->assertSame(1, $outer->count(), 'the inner declaration must not appear in the outer set');
        $this->assertStringContainsString('styles/yw-core.css', $outer->toHtml());
        $this->assertSame(1, $inner->count());
        $this->assertStringContainsString('javascripts/yw-core.js', $inner->toHtml());
    }

    /** A render that throws must not leave the scope open, or every later declaration is swallowed. */
    public function testAScopeClosesWhenTheRenderThrows(): void
    {
        $registry = $this->registry();

        try {
            $registry->capture(function (): void {
                throw new \RuntimeException('rendering blew up');
            });
            $this->fail('the exception must propagate');
        } catch (\RuntimeException) {
        }

        $registry->addCssFile('styles/yw-core.css');
        $this->assertSame(1, $registry->drain()->count(), 'declarations after a failed render must reach the page');
    }

    public function testTheSameFileDeclaredTwiceIsOneEntry(): void
    {
        $registry = $this->registry();
        $registry->addCssFile('styles/yw-core.css');
        $registry->addCssFile('styles/yw-core.css');

        $this->assertSame(1, $registry->drain()->count());
    }

    /**
     * The old registry deduplicated by searching generated HTML with `!strpos(...)`, which is falsy at offset 0 -- so the first stylesheet registered always failed its own duplicate check.
     */
    public function testTheFirstEntryIsDeduplicatedToo(): void
    {
        $registry = $this->registry();
        $registry->addCssFile('styles/yw-core.css');
        $registry->addCssFile('styles/yw-core.css');
        $registry->addCssFile('styles/animate.css');

        $this->assertSame(2, $registry->drain()->count());
    }

    public function testAMissingFileDeclaresNothing(): void
    {
        $registry = $this->registry();
        $registry->addJsFile('javascripts/there-is-no-such-file.js');

        $this->assertTrue($registry->drain()->isEmpty());
    }

    public function testDrainTakesOnlyWhatTheFilterMatches(): void
    {
        $registry = $this->registry();
        $registry->addCssFile('styles/yw-core.css');
        $registry->addJsFile('javascripts/yw-core.js');

        $css = $registry->drain(fn (AssetEntry $entry) => $entry->isCss());
        $this->assertSame(1, $css->count());
        $this->assertStringContainsString('styles/yw-core.css', $css->toHtml());

        $rest = $registry->drain();
        $this->assertSame(1, $rest->count());
        $this->assertStringContainsString('javascripts/yw-core.js', $rest->toHtml());
    }

    public function testScriptsAreDeferredUnlessDeclaredFirst(): void
    {
        $registry = $this->registry();
        $registry->addJsFile('javascripts/yw-core.js');
        $registry->addJsFile('javascripts/yeswiki-base-no-defer.js', true);

        $html = $registry->drain()->toHtml();

        $this->assertLessThan(
            strpos($html, 'javascripts/yw-core.js'),
            strpos($html, 'javascripts/yeswiki-base-no-defer.js'),
            'a `first` script must be emitted before the deferred ones'
        );
        $this->assertMatchesRegularExpression('#yeswiki-base-no-defer\.js[^>]*>#', $html);
        $this->assertDoesNotMatchRegularExpression('#yeswiki-base-no-defer\.js[^>]*defer#', $html);
        $this->assertMatchesRegularExpression('#yw-core\.js[^>]*defer#', $html);
    }

    /**
     * A fragment's assets swap into <head> rather than landing inline, so that deleting the fragment cannot take them with it.
     */
    public function testAFragmentEmitsItsAssetsOutOfBandIntoHead(): void
    {
        $registry = $this->registry();

        $captured = $registry->capture(function () use ($registry): void {
            $registry->addCssFile('styles/yw-core.css');
        });

        $html = $captured->toOutOfBandHtml();
        $this->assertStringContainsString('hx-swap-oob="beforeend:head"', $html);
        $this->assertStringContainsString('styles/yw-core.css', $html);
    }

    public function testAnEmptySetEmitsNothingAtAll(): void
    {
        $this->assertSame('', $this->registry()->drain()->toOutOfBandHtml());
    }
}

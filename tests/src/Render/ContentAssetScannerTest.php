<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Asset\AssetSet;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Render\Service\ContentAssetScanner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06 replaced formatters/wakka__.php -- the last file in formatters/ and the last
 * user of Performer's filename-hook convention -- with this service. The behaviour it
 * carries (page content opting into a client-side library by class name) had no test at
 * all while it lived in a hook file.
 *
 * Ticket 14: what the scanner registered used to be read back out of $GLOBALS['js']. It is
 * now read from a capture scope, which is both the real mechanism and a better assertion --
 * the set contains what *this* scan declared, rather than whatever the global had
 * accumulated by the time the test looked.
 */
class ContentAssetScannerTest extends YesWikiTestCase
{
    private function registry(): AssetRegistry
    {
        $registry = $this->getWiki()->services->get(AssetRegistry::class);
        $this->assertInstanceOf(AssetRegistry::class, $registry);

        return $registry;
    }

    /**
     * A fresh scanner per test on purpose: the "register once per request" guard is
     * instance state, and the container hands out a shared instance, so tests sharing it
     * would silently depend on each other's ordering.
     */
    private function scanner(): ContentAssetScanner
    {
        return new ContentAssetScanner($this->registry());
    }

    /** What $html declares when scanned, without any of it reaching the page. */
    private function declaredBy(ContentAssetScanner $scanner, string ...$html): AssetSet
    {
        return $this->registry()->capture(function () use ($scanner, $html): void {
            foreach ($html as $fragment) {
                $scanner->scan($fragment);
            }
        });
    }

    public function testTheContainerProvidesIt(): void
    {
        $this->assertInstanceOf(
            ContentAssetScanner::class,
            $this->getWiki()->services->get(ContentAssetScanner::class)
        );
    }

    public function testScanReturnsItsInputUnchanged(): void
    {
        $html = '<div class="mermaid">graph TD;</div>';
        $this->assertSame($html, $this->scanner()->scan($html));
    }

    public function testMermaidMarkupRegistersTheMermaidInitialiser(): void
    {
        $declared = $this->declaredBy($this->scanner(), '<div class="mermaid">graph TD; A-->B;</div>');

        $this->assertStringContainsString('mermaid', $declared->toHtml());
        $this->assertStringContainsString(
            'type="module"',
            $declared->toHtml(),
            'the ESM import needs a module script'
        );
    }

    /**
     * The rule matches a whole class name. Before this was a declarative table it was a
     * loose `preg_match` over the raw output, which would fire on any substring.
     */
    public function testMarkerMustBeAWholeClassName(): void
    {
        $declared = $this->declaredBy(
            $this->scanner(),
            '<div class="mermaid-diagram-wrapper">not mermaid</div>',
            '<p>the word mermaid in prose</p>',
            '<div data-note="c4-izmir">not a class attribute</div>'
        );

        $this->assertTrue(
            $declared->isEmpty(),
            'a substring, a prose mention and a non-class attribute must not pull in assets'
        );
    }

    public function testEachMarkerRegistersOnlyOncePerRequest(): void
    {
        $scanner = $this->scanner();

        $first = $this->declaredBy($scanner, '<div class="mermaid">a</div>');
        $again = $this->declaredBy($scanner, '<div class="mermaid">b</div><div class="mermaid">c</div>');

        $this->assertSame(1, $first->count());
        $this->assertTrue($again->isEmpty(), 'the initialiser must not be emitted once per occurrence');
    }

    public function testKnownMarkersAreTheOnesTheOldHookHandled(): void
    {
        // wow and markdown were dropped: both emitted $(document).ready(...) for a jQuery
        // ticket 16 removed, and the markdown one client-side rendered markdown that core
        // already renders server-side.
        $this->assertSame(['mermaid', 'c4-izmir'], ContentAssetScanner::markers());
    }
}

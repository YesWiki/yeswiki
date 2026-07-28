<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\ContentAssetScanner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06 replaced formatters/wakka__.php -- the last file in formatters/ and the last
 * user of Performer's filename-hook convention -- with this service. The behaviour it
 * carries (page content opting into a client-side library by class name) had no test at
 * all while it lived in a hook file.
 */
class ContentAssetScannerTest extends YesWikiTestCase
{
    /**
     * A fresh scanner per test on purpose: the "register once per request" guard is
     * instance state, and the container hands out a shared instance, so tests sharing it
     * would silently depend on each other's ordering.
     */
    private function scanner(): ContentAssetScanner
    {
        $assets = $this->getWiki()->services?->get(\YesWiki\Kernel\Service\AssetsManager::class);
        $this->assertInstanceOf(\YesWiki\Kernel\Service\AssetsManager::class, $assets);

        return new ContentAssetScanner($assets);
    }

    public function testTheContainerProvidesIt(): void
    {
        $this->assertInstanceOf(
            ContentAssetScanner::class,
            $this->getWiki()->services?->get(ContentAssetScanner::class)
        );
    }

    public function testScanReturnsItsInputUnchanged(): void
    {
        $html = '<div class="mermaid">graph TD;</div>';
        $this->assertSame($html, $this->scanner()->scan($html));
    }

    public function testMermaidMarkupRegistersTheMermaidInitialiser(): void
    {
        // AssetsManager accumulates inline JS in $GLOBALS['js']
        $GLOBALS['js'] = '';
        $this->scanner()->scan('<div class="mermaid">graph TD; A-->B;</div>');

        $this->assertStringContainsString('mermaid', $GLOBALS['js']);
        $this->assertStringContainsString('type="module"', $GLOBALS['js'], 'the ESM import needs a module script');
    }

    /**
     * The rule matches a whole class name. Before this was a declarative table it was a
     * loose `preg_match` over the raw output, which would fire on any substring.
     */
    public function testMarkerMustBeAWholeClassName(): void
    {
        $scanner = $this->scanner();
        $GLOBALS['js'] = '';
        $scanner->scan('<div class="mermaid-diagram-wrapper">not mermaid</div>');
        $scanner->scan('<p>the word mermaid in prose</p>');
        $scanner->scan('<div data-note="c4-izmir">not a class attribute</div>');

        $this->assertSame(
            '',
            $GLOBALS['js'],
            'a substring, a prose mention and a non-class attribute must not pull in assets'
        );
    }

    public function testEachMarkerRegistersOnlyOncePerRequest(): void
    {
        $scanner = $this->scanner();

        $GLOBALS['js'] = '';
        $scanner->scan('<div class="mermaid">a</div>');
        $afterFirst = $GLOBALS['js'];
        $scanner->scan('<div class="mermaid">b</div><div class="mermaid">c</div>');

        $this->assertSame($afterFirst, $GLOBALS['js'],
            'the initialiser must not be emitted once per occurrence');
    }

    public function testKnownMarkersAreTheOnesTheOldHookHandled(): void
    {
        // wow and markdown were dropped: both emitted $(document).ready(...) for a jQuery
        // ticket 16 removed, and the markdown one client-side rendered markdown that core
        // already renders server-side.
        $this->assertSame(['mermaid', 'c4-izmir'], ContentAssetScanner::markers());
    }
}

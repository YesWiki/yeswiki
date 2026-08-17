<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Asset\AssetSet;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Render\Service\ContentAssetScanner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06 replaced formatters/wakka__.php -- the last file in formatters/ and the last user of Performer's filename-hook convention -- with this service.
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
     * A fresh scanner per test on purpose: the "register once per request" guard is instance state, and the container hands out a shared instance, so tests sharing it would silently depend on each other's ordering.
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

    /** The rule matches a whole class name. */
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
        $this->assertSame(['mermaid', 'c4-izmir'], ContentAssetScanner::markers());
    }
}

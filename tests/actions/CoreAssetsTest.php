<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 09 (yw-* core CSS/JS foundation, ADR-0004/0005):
 * the yw-core design system and htmx must be loaded on every page, alongside
 * Bootstrap/jQuery (not replacing them yet -- that's ticket 16).
 */
class CoreAssetsTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testYwCoreCssIsLoadedWithoutBootstrap(YesWikiRuntime $wiki)
    {
        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{linkstyle}}');
        $this->assertStringContainsString('styles/yw-core.css', $output);
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap CSS must not load anymore.');
    }

    #[Depends('testWikiExisting')]
    public function testHtmxAndYwCoreJsAreLoadedGlobally(YesWikiRuntime $wiki)
    {
        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{linkjavascript}}');
        $this->assertStringContainsString('javascripts/vendor/htmx/htmx.min.js', $output);
        $this->assertStringContainsString('javascripts/yw-core.js', $output);
        $this->assertStringContainsString('javascripts/yw-datatable.js', $output);
        $this->assertStringContainsString('javascripts/yw-autocomplete.js', $output);
        $this->assertStringNotContainsString('jquery', $output, 'ticket 16: jQuery must not load globally anymore.');
        $this->assertStringNotContainsString('bootstrap', $output, 'ticket 16: Bootstrap JS must not load anymore.');
    }
}

<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 09 (yw-* core CSS/JS foundation, ADR-0004/0005):
 * the yw-core design system and htmx must be loaded on every page, alongside
 * Bootstrap/jQuery (not replacing them yet -- that's ticket 16).
 */
class CoreAssetsTest extends YesWikiTestCase
{
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki->services->get(Wiki::class);
    }

    #[Depends('testWikiExisting')]
    public function testYwCoreCssIsLoadedAlongsideBootstrap(Wiki $wiki)
    {
        $output = $wiki->Format('{{linkstyle}}');
        $this->assertStringContainsString('styles/yw-core.css', $output);
        $this->assertStringContainsString('bootstrap.min.css', $output, 'Bootstrap must still load -- this ticket does not remove it.');
    }

    #[Depends('testWikiExisting')]
    public function testHtmxAndYwCoreJsAreLoadedGlobally(Wiki $wiki)
    {
        $output = $wiki->Format('{{linkjavascript}}');
        $this->assertStringContainsString('javascripts/vendor/htmx/htmx.min.js', $output);
        $this->assertStringContainsString('javascripts/yw-core.js', $output);
        $this->assertStringContainsString('javascripts/yw-datatable.js', $output);
        $this->assertStringContainsString('javascripts/yw-autocomplete.js', $output);
        $this->assertStringContainsString('jquery', $output, 'jQuery must still load -- this ticket does not remove it.');
    }
}

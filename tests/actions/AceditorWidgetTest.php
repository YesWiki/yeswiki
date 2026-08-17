<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 11 (aceditor absorbed into core): the editor wrapper/toolbar/link rail no longer depend on jQuery or Bootstrap's JS components (bootstrap-tagsinput, data-toggle="dropdown", data-toggle="modal").
 */
class AceditorWidgetTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        $GLOBALS['yeswikiServices'] = $wiki->services;

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testAceditorTagRendersWithoutError(YesWikiRuntime $wiki)
    {
        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{aceditor}}');
        $this->assertStringContainsString('aceditor-container', $output);
        $this->assertStringNotContainsStringIgnoringCase('unable to find template', $output);
    }

    #[Depends('testWikiExisting')]
    public function testWidgetDoesNotDependOnBootstrapJsComponents(YesWikiRuntime $wiki)
    {
        $output = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{aceditor}}');

        $this->assertStringNotContainsString('bootstrap-tagsinput', $output);

        $this->assertStringNotContainsString('data-toggle="dropdown"', $output);
        $this->assertStringContainsString('yw-dropdown', $output);
        $this->assertStringContainsString('data-yw-dropdown-toggle', $output);
    }

    #[Depends('testWikiExisting')]
    public function testGlobalAssetsIncludeRebuiltWidget(YesWikiRuntime $wiki)
    {
        $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{aceditor}}');
        $js = $wiki->services->get(\YesWiki\Kernel\Service\AssetRegistry::class)->drain()->toHtml();

        $this->assertStringContainsString('javascripts/aceditor.js', $js);
        unset($GLOBALS['wiki']);
    }
}

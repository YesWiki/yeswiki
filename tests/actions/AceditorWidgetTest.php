<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 11 (aceditor absorbed into core): the editor
 * wrapper/toolbar/link-modal no longer depend on jQuery or Bootstrap's JS
 * components (bootstrap-tagsinput, data-toggle="dropdown", data-toggle="modal").
 * The Vue-based actions-builder subsystem is deliberately untouched/still
 * Bootstrap-styled -- out of scope for this ticket.
 */
class AceditorWidgetTest extends YesWikiTestCase
{
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));
        // {{aceditor}}'s ActionsBuilderService::getData() calls bazar's
        // baz_forms_and_lists_ids(), which reads $GLOBALS['wiki'] -- normally
        // populated by the production HTTP bootstrap, not the test harness
        // (same workaround as FiltertagsActionTest)
        $GLOBALS['wiki'] = $wiki;

        return $wiki->services->get(Wiki::class);
    }

    #[Depends('testWikiExisting')]
    public function testAceditorTagRendersWithoutError(Wiki $wiki)
    {
        $output = $wiki->Format('{{aceditor}}');
        $this->assertStringContainsString('aceditor-container', $output);
        $this->assertStringNotContainsStringIgnoringCase('unable to find template', $output);
    }

    #[Depends('testWikiExisting')]
    public function testWidgetDoesNotDependOnBootstrapJsComponents(Wiki $wiki)
    {
        $output = $wiki->Format('{{aceditor}}');

        $this->assertStringNotContainsString('bootstrap-tagsinput', $output);
        // the toolbar's own dropdowns (Format/Actions menus) must be yw-dropdown,
        // not Bootstrap's JS-driven data-toggle="dropdown"
        $this->assertStringNotContainsString('data-toggle="dropdown"', $output);
        $this->assertStringContainsString('yw-dropdown', $output);
        $this->assertStringContainsString('data-yw-dropdown-toggle', $output);
    }

    #[Depends('testWikiExisting')]
    public function testGlobalAssetsIncludeRebuiltWidget(Wiki $wiki)
    {
        $wiki->Format('{{aceditor}}');
        $js = $wiki->Format('{{linkjavascript}}');

        $this->assertStringContainsString('javascripts/aceditor.js', $js);
        unset($GLOBALS['wiki']);
    }
}

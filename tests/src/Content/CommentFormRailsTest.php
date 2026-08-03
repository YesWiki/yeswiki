<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Every template that renders an `{{aceditor}}` has to ship the rails it opens.
 *
 * The editor builds all three on startup -- the actions builder, the link editor, the
 * file picker -- so a page that ships two of them and not the third breaks on the third,
 * at the first cursor move rather than at load. The comment form is the case that is
 * easy to forget: it renders an editor outside the edit screen, from a different
 * template, and used to include its own shorter list of modals.
 */
class CommentFormRailsTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testTheCommentFormShipsEveryRailItsEditorOpens(YesWikiRuntime $wiki): void
    {
        // {{aceditor}}'s ActionsBuilderService::getData() reads $GLOBALS['wiki'] --
        // normally populated by the production HTTP bootstrap, not the test harness
        // (same workaround as FiltertagsActionTest)
        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $output = $wiki->services->get(TemplateEngine::class)->renderSafely('@core/comment-form.twig', [
                'pagetag' => 'SomePage',
                'formlink' => '/?api/comments',
                'hashcash' => '',
                'tempTag' => 'temp_0123456789',
            ]);

            $this->assertStringContainsString('aceditor-container', $output, 'the comment form renders an editor');
            $this->assertStringContainsString('actions-builder-app', $output, 'and the actions builder rail');
            $this->assertStringContainsString('YesWikiLinkPanel', $output, 'and the link rail');
            $this->assertStringContainsString('YesWikiFilePickerPanel', $output, 'and the file picker rail');
        } finally {
            unset($GLOBALS['wiki']);
        }
    }
}

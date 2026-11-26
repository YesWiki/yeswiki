<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\FormApiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The form designer's preview tab renders the whole entry form from an unsaved template. */
class FormPreviewTest extends YesWikiTestCase
{
    public function testTheWholeEntryFormRendersFromThePostedTemplate(): void
    {
        $html = $this->preview([
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'columns', 'layout' => '4-8'],
                ['type' => 'texte', 'name' => 'bf_a', 'label' => 'A'],
                ['type' => 'columnchange'],
                ['type' => 'texte', 'name' => 'bf_b', 'label' => 'B'],
                ['type' => 'columnsend'],
            ], JSON_THROW_ON_ERROR),
            'label' => 'Brouillon',
            'entry_form_width' => 'wide',
        ]);

        $this->assertStringContainsString('<form action="#"', $html);
        $this->assertStringContainsString('yw-entry-width--wide', $html);
        $this->assertStringContainsString('yw-form-columns--4-8', $html);
        $this->assertStringContainsString('name="bf_b"', $html);
        $this->assertStringContainsString('Brouillon', $html);
        $this->assertStringNotContainsString('data-yw-unsaved-guard', $html);
    }

    public function testATemplateThatIsNotJsonGetsAnAlert(): void
    {
        $html = $this->preview(['template' => '[{oops']);

        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    /** @param array<string, string> $post */
    private function preview(array $post): string
    {
        $controller = $this->getWiki()->services->get(FormApiController::class);

        return (string)$controller->previewWholeForm(new Request([], $post))->getContent();
    }
}

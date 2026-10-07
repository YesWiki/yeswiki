<?php

namespace YesWiki\Test\Core\Controller;

use YesWiki\Content\Api\EntryApiController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Entry API output carries template stylesheets. */
class ApiEntryStylesTest extends YesWikiTestCase
{
    private const FORM_ID = '999965';
    private const TAG = 'ApiEntryStylesTestEntry';
    private const STYLE = 'styles/yw-entries.css';
    private const TEMPLATE = 'custom/templates/core/fiche-' . self::FORM_ID . '.twig';

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Api entry styles test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1']]),
            'condition' => '',
        ]);
        file_put_contents(self::TEMPLATE, "{{ include_css('" . self::STYLE . "') }}<div class=\"api-entry-styles\">{{ html.bf_titre|raw }}</div>");
        $services->get(EntryManager::class)->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Api entry styles entry', 'tag' => self::TAG]);
        $services->get(AclService::class)->save(self::TAG, 'read', '*');
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $services->get(EntryManager::class)->delete(self::TAG, true);
        $services->get(AclService::class)->delete(self::TAG);
        $services->get(FormManager::class)->delete(self::FORM_ID);
        if (is_file(self::TEMPLATE)) {
            unlink(self::TEMPLATE);
        }
        self::getWiki()->services->get(CurrentRequest::class)->get()->query->remove('fields');
    }

    private function htmlOutput(): string
    {
        $response = $this->getWiki()->services->get(EntryApiController::class)->getAllEntries('html', self::TAG);

        return (string)(json_decode((string)$response->getContent(), true)[self::TAG]['html_output'] ?? '');
    }

    public function testASingleEntryViewCarriesItsTemplateStylesheet(): void
    {
        $this->getWiki()->services->get(CurrentRequest::class)->get()->query->set('fields', 'html_output');

        $html = $this->htmlOutput();

        $this->assertStringContainsString('class="api-entry-styles"', $html);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*' . preg_quote(self::STYLE, '#') . '#', $html);
    }

    public function testEntriesListedAsHtmlCarryTheirTemplateStylesheet(): void
    {
        $this->getWiki()->services->get(CurrentRequest::class)->get()->query->remove('fields');

        $response = $this->getWiki()->services->get(EntryApiController::class)->getAllFormEntries(self::FORM_ID, 'html');
        $entries = array_column((array)json_decode((string)$response->getContent(), true), 'html_output', 'tag');
        $html = (string)($entries[self::TAG] ?? '');

        $this->assertStringContainsString('class="api-entry-styles"', $html);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*' . preg_quote(self::STYLE, '#') . '#', $html);
    }
}

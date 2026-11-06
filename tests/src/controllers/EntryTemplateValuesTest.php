<?php

namespace YesWiki\Test\Controllers;

use YesWiki\Content\Api\EntryApiController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\CustomTemplateService;
use YesWiki\Render\Twig\MarkdownTemplate;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What an entry template is given: labels, rendered values and raw values, minus the fields its reader may not see. */
class EntryTemplateValuesTest extends YesWikiTestCase
{
    private const FORM_ID = '999964';
    private const TAG = 'EntryTemplateValuesTestEntry';
    private const TEMPLATE = 'core/fiche-' . self::FORM_ID . '.twig';

    /** @var list<string> */
    private static array $missingDirectories = [];

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        self::$missingDirectories = array_values(array_filter(
            [CustomTemplateService::DIRECTORY . '/core', CustomTemplateService::DIRECTORY],
            static fn (string $directory) => !is_dir($directory)
        ));
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $form = fn (array $secretAcl): array => [
            'id' => self::FORM_ID,
            'label' => 'Entry template values test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Le titre', 'required' => '1'],
                ['type' => 'texte', 'name' => 'bf_secret', 'label' => 'Le secret'] + $secretAcl,
            ]),
            'condition' => '',
        ];
        $formManager->create($form([]));
        $services->get(CustomTemplateService::class)->write(self::TEMPLATE, MarkdownTemplate::mark(
            "## {{ formFields.bf_titre.label }}\n\n**brut** [{{ entry.bf_titre }}] html [{{ html.bf_titre|raw }}]\n\nsecret [{{ entry.bf_secret }}]\n"
        ));
        $services->get(EntryManager::class)->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Valeur *titre*', 'bf_secret' => 'CACHE', 'tag' => self::TAG]);
        $formManager->update($form(['read_access' => '@admins']));
        $services->get(AclService::class)->save(self::TAG, 'read', '*');
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $services->get(EntryManager::class)->delete(self::TAG, true);
        $services->get(AclService::class)->delete(self::TAG);
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(CustomTemplateService::class)->delete(self::TEMPLATE);
        foreach (self::$missingDirectories as $directory) {
            @rmdir($directory);
        }
        $services->get(CurrentRequest::class)->get()->query->remove('fields');
    }

    private function htmlOutput(): string
    {
        $services = $this->getWiki()->services;
        $services->get(CurrentRequest::class)->get()->query->set('fields', 'html_output');
        $response = $services->get(EntryApiController::class)->getAllEntries('html', self::TAG);

        return (string)(json_decode((string)$response->getContent(), true)[self::TAG]['html_output'] ?? '');
    }

    public function testLabelsAndValuesReachTheMarkdownTemplateUnconverted(): void
    {
        $html = $this->htmlOutput();

        $this->assertStringContainsString('Le titre</h2>', $html);
        $this->assertStringContainsString('<strong>brut</strong>', $html);
        $this->assertStringContainsString('[Valeur *titre*]', $html);
    }

    public function testARawValueObeysTheFieldReadAcl(): void
    {
        $this->assertStringNotContainsString('CACHE', $this->htmlOutput());
    }
}

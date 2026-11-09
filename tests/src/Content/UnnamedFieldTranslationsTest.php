<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A field without a name, such as a label or tabs, is translated through its handle. */
class UnnamedFieldTranslationsTest extends YesWikiTestCase
{
    private string $formId = '';

    protected function setUp(): void
    {
        $formManager = $this->formManager();
        $id = 9866;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->formId = (string)$id;
        $this->assertSame(0, $formManager->create([
            'id' => $this->formId,
            'label' => 'UnnamedFieldTranslationsTest',
            'entry_title_template' => '{{bf_titre}}',
            'template' => [
                ['type' => 'tabs', 'form_titles' => 'Premier,Second', 'view_titles' => 'Un,Deux'],
                ['type' => 'labelhtml', 'form_text' => '<h3>Bienvenue</h3>', 'view_text' => '<h3>Fiche</h3>'],
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'tabchange', 'form_change' => 'formChange'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->formId !== '' && $this->formManager()->getOne($this->formId) !== null) {
            $this->formManager()->delete($this->formId);
        }
    }

    private function formManager(): FormManager
    {
        return $this->getWiki()->services->get(FormManager::class);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return $this->formManager()->getUntranslated($this->formId) ?? [];
    }

    private function handleOf(string $type): string
    {
        foreach ($this->stored()['template'] ?? [] as $field) {
            if (($field['type'] ?? '') === $type) {
                return (string)$field['handle'];
            }
        }
        $this->fail("no {$type} field");
    }

    public function testALabelAndTabsOfferTheirTextsByHandle(): void
    {
        $paths = array_column($this->getWiki()->services->get(TranslatableContent::class)->formPaths($this->stored()), 'path');
        $label = $this->handleOf('labelhtml');
        $tabs = $this->handleOf('tabs');

        $this->assertContains("template.{$label}.form_text", $paths);
        $this->assertContains("template.{$label}.view_text", $paths);
        $this->assertContains("template.{$tabs}.form_titles", $paths);
        $this->assertContains("template.{$tabs}.view_titles", $paths);
        $this->assertContains('template.bf_titre.label', $paths);
        $this->assertSame([], array_filter($paths, fn ($path) => str_contains($path, 'form_change')));
    }

    public function testATranslatedLabelIsStoredUnderItsHandleAndSurvivesAnEdit(): void
    {
        $label = $this->handleOf('labelhtml');
        $this->formManager()->saveTranslations($this->formId, 'en', ["template.{$label}.form_text" => '<h3>Welcome</h3>']);

        $tag = (string)($this->stored()['tag'] ?? '');
        $db = $this->getWiki()->services->get(DbService::class);
        $body = json_decode((string)($db->loadSingle("SELECT body FROM {$db->prefixTable('pages')} WHERE tag = ? AND latest = 'Y'", [$tag])['body'] ?? ''), true);
        $this->assertSame('<h3>Welcome</h3>', $body[Translations::BODY_KEY]['en']['fields'][$label]['form_text'] ?? null);

        $template = $this->stored()['template'];
        $template[1]['form_text'] = '<h3>Bienvenue à tous</h3>';
        $this->formManager()->update(['id' => $this->formId, 'label' => 'UnnamedFieldTranslationsTest', 'entry_title_template' => '{{bf_titre}}', 'template' => $template]);

        $read = Translations::apply($this->stored(), 'en');
        $this->assertSame('<h3>Welcome</h3>', $read['template'][1]['form_text']);
        $this->assertSame('<h3>Fiche</h3>', $read['template'][1]['view_text']);
    }

    public function testATranslatedLabelMovedElsewhereInTheFormKeepsItsTranslation(): void
    {
        $label = $this->handleOf('labelhtml');
        $this->formManager()->saveTranslations($this->formId, 'en', ["template.{$label}.form_text" => '<h3>Welcome</h3>']);

        $template = $this->stored()['template'];
        $template = [$template[2], $template[0], $template[3], $template[1]];
        $this->formManager()->update(['id' => $this->formId, 'label' => 'UnnamedFieldTranslationsTest', 'entry_title_template' => '{{bf_titre}}', 'template' => $template]);

        $this->assertSame(["template.{$label}.form_text" => '<h3>Welcome</h3>'], Translations::of($this->stored(), 'en'));
        $this->assertSame('<h3>Welcome</h3>', Translations::apply($this->stored(), 'en')['template'][3]['form_text']);
    }
}

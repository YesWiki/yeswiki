<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\FeedLanguages;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 66: a field keeps a stable handle, so renaming it carries its translations, and a translated entry keeps a translated title. */
class FieldRenameKeepsTranslationsTest extends YesWikiTestCase
{
    private string $formId = '';

    protected function setUp(): void
    {
        $formManager = $this->formManager();
        $id = 9766;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->formId = (string)$id;
        $this->assertSame(0, $formManager->create([
            'id' => $this->formId,
            'label' => 'FieldRenameKeepsTranslationsTest',
            'entry_title_template' => '{{bf_titre}}',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'texte', 'name' => 'bf_lieu', 'label' => 'Lieu'],
                ['type' => 'texte', 'name' => 'bf_date', 'label' => 'Date'],
            ],
        ]));
        $formManager->saveTranslations($this->formId, 'en', [
            'template.bf_lieu.label' => 'Place',
            'template.bf_date.label' => 'Day',
        ]);
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

    /** @return list<array<string, string>> */
    private function storedTemplate(): array
    {
        return $this->formManager()->getUntranslated($this->formId)['template'] ?? [];
    }

    /** @param array<string, string> $names old name => new name */
    private function saveRenamed(array $names, bool $keepHandles = true): void
    {
        $template = [];
        foreach ($this->storedTemplate() as $field) {
            $field['name'] = $names[$field['name']] ?? $field['name'];
            if (!$keepHandles) {
                unset($field['handle']);
            }
            $template[] = $field;
        }
        $this->formManager()->update(['id' => $this->formId, 'label' => 'FieldRenameKeepsTranslationsTest', 'entry_title_template' => '{{bf_titre}}', 'template' => $template]);
    }

    /** @return array<string, string> */
    private function english(): array
    {
        return Translations::of($this->formManager()->getUntranslated($this->formId) ?? [], 'en');
    }

    public function testEveryFieldGetsItsOwnHandle(): void
    {
        $handles = array_column($this->storedTemplate(), 'handle');

        $this->assertCount(3, array_unique($handles));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $handles[0]);
    }

    public function testARenamedFieldKeepsItsTranslation(): void
    {
        $this->saveRenamed(['bf_lieu' => 'bf_endroit']);

        $this->assertSame(['template.bf_date.label' => 'Day', 'template.bf_endroit.label' => 'Place'], $this->sorted($this->english()));
    }

    public function testTwoFieldsSwappingNamesSwapTheirTranslations(): void
    {
        $this->saveRenamed(['bf_lieu' => 'bf_date', 'bf_date' => 'bf_lieu']);

        $this->assertSame(['template.bf_date.label' => 'Place', 'template.bf_lieu.label' => 'Day'], $this->sorted($this->english()));
    }

    public function testAReplacedFieldLosesItsTranslation(): void
    {
        $this->saveRenamed(['bf_lieu' => 'bf_endroit'], false);

        $this->assertSame(['template.bf_date.label' => 'Day'], $this->english());
    }

    public function testADuplicatedHandleIsReplaced(): void
    {
        $template = $this->formManager()->withHandles([
            ['type' => 'texte', 'name' => 'a', 'handle' => 'cafe0001'],
            ['type' => 'texte', 'name' => 'b', 'handle' => 'cafe0001'],
        ]);

        $this->assertSame('cafe0001', $template[0]['handle']);
        $this->assertNotSame('cafe0001', $template[1]['handle']);
    }

    public function testATranslatedEntryHasATranslatedTitle(): void
    {
        $form = $this->formManager()->getOne($this->formId);
        $titles = $this->getWiki()->services->get(FormPropertiesService::class);
        $entry = ['form_id' => $this->formId, 'bf_titre' => 'Atelier', 'title' => 'Atelier', Translations::BODY_KEY => ['en' => ['bf_titre' => 'Workshop']]];

        $this->assertSame('Workshop', $titles->translatedTitleOf($form, $entry, 'en'));
        $entry[Translations::BODY_KEY]['en']['title'] = 'Our workshop';
        $this->assertSame('Our workshop', $titles->translatedTitleOf($form, $entry, 'en'));
    }

    public function testAFeedShowsEveryLanguageUnlessOneIsAsked(): void
    {
        $feed = $this->getWiki()->services->get(FeedLanguages::class);
        $body = ['bf_titre' => 'Atelier', Translations::BODY_KEY => ['en' => ['bf_titre' => 'Workshop']]];

        $this->assertSame(['fr' => ['bf_titre' => 'Atelier'], 'en' => ['bf_titre' => 'Workshop']], $feed->versions($body, 'fr', $feed->requested(Request::create('/feed'))));
        $this->assertSame(['en' => ['bf_titre' => 'Workshop']], $feed->versions($body, 'fr', $feed->requested(Request::create('/feed', 'GET', ['lang' => 'en']))));
        $this->assertSame(['fr' => ['bf_titre' => 'Atelier']], $feed->versions($body, 'fr', 'fr'));
        $this->assertSame('https://x/?Page&lang=en', $feed->urlIn('https://x/?Page', 'en', 'fr'));
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}

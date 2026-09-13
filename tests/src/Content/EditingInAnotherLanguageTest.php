<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Controller\FormController;
use YesWiki\Content\Controller\ListController;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Render\Service\LanguageSwitch;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Editing a Content in another language: the same editor, writing translations instead of the source. */
class EditingInAnotherLanguageTest extends YesWikiTestCase
{
    private const FORM_ID = '999914';
    private const LIST_ID = 'ListeEditLangTest';

    private const TEMPLATE = [
        ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'hint' => 'Le nom'],
        ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
    ];

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(AuthenticationService::class)->logout();
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);

        parent::tearDown();
    }

    /** Act as this wiki's own admin, or skip: both editors are gated. */
    private function loginAsAdmin(): void
    {
        $wiki = $this->getWiki();
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $wiki->services->get(AclService::class)->isAdmin($user['name'])
        ));
        if ($admin === false) {
            $this->markTestSkipped('this wiki has no admin to edit as');
        }
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }

    private function skipUnlessMultilingual(): void
    {
        if (count($this->getWiki()->services->get(LanguageService::class)->availableLanguages()) < 2) {
            $this->markTestSkipped('this wiki offers a single language, so nothing switches');
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function request(array $query, array $post = []): void
    {
        $this->getWiki()->services->get(RequestScope::class)->startNewRequest();
        $this->getWiki()->services->get(CurrentRequest::class)->replace(new Request($query, $post));
    }

    private function makeForm(): void
    {
        $this->getWiki()->services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire à traduire',
            'description' => 'Une description en français',
            'template' => json_encode(self::TEMPLATE),
        ]);
    }

    private function makeList(): void
    {
        $this->getWiki()->services->get(ListManager::class)->create(
            'Couleurs',
            [['id' => 'rouge', 'label' => 'Rouge', 'children' => []]],
            self::LIST_ID
        );
    }

    /** One switch for the whole wiki: an edit screen hands it the languages it writes, and the chrome draws it where the reader's own switch sits. */
    public function testTheDesignerHandsItsLanguagesToTheSwitch(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();

        $this->request(['editlang' => 'en']);
        $this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);
        $switch = $this->getWiki()->services->get(LanguageSwitch::class);

        $this->assertTrue($switch->isWriting(), 'the switch changes the translation being written, not the reader\'s language');
        $states = array_column($switch->options(), 'state', 'code');

        $this->assertSame('source', $states['fr'] ?? null, 'the language it is written in is the source');
        $this->assertSame(
            ['empty'],
            array_values(array_unique(array_diff_key($states, ['fr' => true]))),
            'nothing is translated yet, whichever languages this wiki offers'
        );
    }

    /** Opening the editor from a page being read in another language writes that language, which is the one the reader was already in. */
    public function testTheEditorOpensOnTheLanguageBeingRead(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();
        $language = $this->getWiki()->services->get(LanguageService::class);
        $served = $language->preferredLanguage();

        try {
            $language->serveIn('en');
            $this->request([]);
            $this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);

            $this->assertSame(
                'en',
                $this->currentWritingLanguage(),
                'no editlang was asked for, so the editor opens in the language being read'
            );
        } finally {
            $language->serveIn($served);
        }
    }

    /** Which is not a trap: the source language names itself, so one click writes the original again. */
    public function testTheSourceLanguageStaysOneClickAway(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();
        $language = $this->getWiki()->services->get(LanguageService::class);
        $served = $language->preferredLanguage();

        try {
            $language->serveIn('en');
            $this->request(['editlang' => 'fr']);
            $this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);

            $this->assertSame('fr', $this->currentWritingLanguage());
        } finally {
            $language->serveIn($served);
        }
    }

    /** The language the switch says is being written. */
    private function currentWritingLanguage(): string
    {
        foreach ($this->getWiki()->services->get(LanguageSwitch::class)->options() as $option) {
            if ($option['current']) {
                return (string)$option['code'];
            }
        }

        return '';
    }

    /** An entry's fields are blank, with the source wording beside each one. */
    public function testTheEntryEditorShowsTheSourceBesideEachField(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();
        $entryManager = $this->getWiki()->services->get(EntryManager::class);
        $entry = $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Un titre en français']);

        $this->request(['editlang' => 'en']);
        $html = (string)$this->getWiki()->services->get(EntryController::class)->update($entry['tag']);

        $this->assertStringNotContainsString('value="Un titre en français"', $html);
        $this->assertMatchesRegularExpression(
            '/yw-translate-from__text[^>]*>\s*Un titre en français/u',
            $html,
            'the translator is shown what they are translating'
        );
    }

    /** A list value keeps its own wording where a translator can see it, without it being posted back as the translation. */
    public function testTheListEditorShowsEachValueSWording(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeList();

        $this->request(['editlang' => 'en']);
        $html = (string)$this->getWiki()->services->get(ListController::class)->update(self::LIST_ID);

        $this->assertStringContainsString('&quot;sourceLabel&quot;:&quot;Rouge&quot;', $html);
        $this->assertStringContainsString('&quot;label&quot;:&quot;&quot;', $html, 'and the value itself stays empty');
    }

    /** The mark on each language: none of it, some of it, or all of what the Content has to translate. */
    public function testTheSwitchSaysHowFarEachTranslationHasGot(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $paths = $this->getWiki()->services->get(TranslatableContent::class)
            ->formPaths($formManager->getUntranslated(self::FORM_ID) ?? []);

        $formManager->saveTranslations(self::FORM_ID, 'en', [$paths[0]['path'] => 'Form to translate']);
        $this->request(['editlang' => 'en']);
        $this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);

        $this->assertGreaterThan(1, count($paths), 'this form has more than one thing to translate');
        $this->assertSame(
            'partial',
            array_column($this->getWiki()->services->get(LanguageSwitch::class)->options(), 'state', 'code')['en'],
            'one value out of several is not a translated form'
        );
    }

    /**
     * The designer shows what is still to translate, not the source wording to overwrite: the input
     * stays empty, because an empty translation is what makes a reader fall back to the source, and
     * the source is shown beside it so the translator knows what they are retyping.
     */
    public function testTheDesignerShowsBlanksWithTheSourceBeside(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();

        $this->request(['editlang' => 'en']);
        $html = (string)$this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);

        $this->assertStringNotContainsString(
            'value="Formulaire à traduire"',
            $html,
            'the input would be saved as the English translation of itself'
        );
        $this->assertMatchesRegularExpression(
            '/yw-translate-from__text[^>]*>\s*Formulaire à traduire/u',
            $html,
            'the translator is shown what they are translating'
        );
    }

    public function testSavingTheDesignerInAnotherLanguageWritesATranslation(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeForm();

        $this->request(['editlang' => 'en'], [
            'valider' => '1',
            'label' => 'Form to translate',
            'description' => '',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Title', 'hint' => 'The name'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => ''],
            ]),
        ]);

        try {
            $this->getWiki()->services->get(FormController::class)->update(self::FORM_ID);
        } catch (\YesWiki\Kernel\Exception\ExitException) {
            // the controller redirects when it has saved
        }

        $stored = $this->getWiki()->services->get(FormManager::class)->getUntranslated(self::FORM_ID);
        $this->assertIsArray($stored);
        $this->assertSame('Formulaire à traduire', $stored['label'], 'the source wording is untouched');
        $this->assertSame('Form to translate', $stored[Translations::BODY_KEY]['en']['label']);
        $this->assertSame('Title', $stored[Translations::BODY_KEY]['en']['template.bf_titre.label']);
        $this->assertSame('The name', $stored[Translations::BODY_KEY]['en']['template.bf_titre.hint']);
        $this->assertArrayNotHasKey(
            'template.bf_description.label',
            $stored[Translations::BODY_KEY]['en'],
            'a field left empty is not translated, so it falls back'
        );
    }

    public function testSavingTheListEditorInAnotherLanguageWritesATranslation(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeList();

        $this->request(['editlang' => 'en'], [
            'submit' => '1',
            'title' => 'Colours',
            'nodes' => json_encode([['id' => 'rouge', 'label' => 'Red', 'children' => []]]),
        ]);

        try {
            $this->getWiki()->services->get(ListController::class)->update(self::LIST_ID);
        } catch (\YesWiki\Kernel\Exception\ExitException) {
            // the controller redirects when it has saved
        }

        $stored = $this->getWiki()->services->get(ListManager::class)->getUntranslated(self::LIST_ID);
        $this->assertIsArray($stored);
        $this->assertSame('Couleurs', $stored['title'], 'the source wording is untouched');
        $this->assertSame('Rouge', $stored['nodes'][0]['label']);
        $this->assertSame('Colours', $stored[Translations::BODY_KEY]['en']['title']);
        $this->assertSame('Red', $stored[Translations::BODY_KEY]['en']['nodes.rouge.label']);
    }

    /** Structure is not this screen's to change: only the wording is taken. */
    public function testTranslatingAListDoesNotChangeItsValues(): void
    {
        $this->skipUnlessMultilingual();
        $this->loginAsAdmin();
        $this->makeList();

        $this->request(['editlang' => 'en'], [
            'submit' => '1',
            'title' => 'Colours',
            'nodes' => json_encode([
                ['id' => 'rouge', 'label' => 'Red', 'children' => []],
                ['id' => 'bleu', 'label' => 'Blue', 'children' => []],
            ]),
        ]);

        try {
            $this->getWiki()->services->get(ListController::class)->update(self::LIST_ID);
        } catch (\YesWiki\Kernel\Exception\ExitException) {
            // the controller redirects when it has saved
        }

        $stored = $this->getWiki()->services->get(ListManager::class)->getUntranslated(self::LIST_ID);
        $this->assertIsArray($stored);
        $this->assertCount(1, $stored['nodes'], 'the value someone added while translating is not created');
    }
}

<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A reader gets the Content in their language; a writer never posts that language back into the source. */
class TranslatedContentTest extends YesWikiTestCase
{
    private const FORM_ID = '999912';
    private const ENTRY_TAG = 'TranslatedContentTestEntry';
    private const LIST_ID = 'ListeTranslatedContentTest';
    private const PAGE_TAG = 'TranslatedContentTestPage';

    private const TEMPLATE = [
        ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'hint' => 'Le nom'],
        ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
        ['type' => 'texte', 'name' => 'bf_mail', 'label' => 'Courriel', 'sub_type' => 'email'],
    ];

    private string $previousLanguage = 'fr';

    protected function setUp(): void
    {
        parent::setUp();

        $wiki = $this->getWiki();
        $this->previousLanguage = $wiki->services->get(LanguageService::class)->preferredLanguage();

        $wiki->services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test multilingue',
            'description' => 'Une description en français',
            'template' => json_encode(self::TEMPLATE),
        ]);

        $wiki->services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'tag' => self::ENTRY_TAG,
            'bf_titre' => 'Le titre',
            'bf_description' => 'La description',
            'bf_mail' => 'personne@example.org',
        ]);
    }

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $this->serveIn($this->previousLanguage);

        $entryManager = $wiki->services->get(EntryManager::class);
        if ($entryManager->isEntry(self::ENTRY_TAG)) {
            $entryManager->delete(self::ENTRY_TAG, true);
        }
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);

        $wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);

        parent::tearDown();
    }

    /** Serve the rest of the test in $language, dropping the caches the previous one filled. */
    private function serveIn(string $language): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(LanguageService::class)->serveIn($language);
        $wiki->services->get(FormManager::class)->startNewRequest();
        $wiki->services->get(ListManager::class)->startNewRequest();
    }

    public function testAReaderGetsTheTranslatedEntryAndFallsBackToTheSource(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);

        $entryManager->saveTranslations(self::ENTRY_TAG, 'en', ['bf_titre' => 'The title']);

        $this->serveIn('en');
        $read = $entryManager->getOne(self::ENTRY_TAG);
        $this->assertIsArray($read);

        $this->assertSame('The title', $read['bf_titre']);
        $this->assertSame('La description', $read['bf_description']);
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $read);
    }

    public function testTheSourceLanguageReaderNeverSeesTheStore(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $entryManager->saveTranslations(self::ENTRY_TAG, 'en', ['bf_titre' => 'The title']);

        $this->serveIn('fr');
        $read = $entryManager->getOne(self::ENTRY_TAG);
        $this->assertIsArray($read);

        $this->assertSame('Le titre', $read['bf_titre']);
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $read);
    }

    public function testGetUntranslatedKeepsBothTheSourceAndTheStore(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $entryManager->saveTranslations(self::ENTRY_TAG, 'en', ['bf_titre' => 'The title']);

        $this->serveIn('en');
        $stored = $entryManager->getUntranslated(self::ENTRY_TAG);
        $this->assertIsArray($stored);

        $this->assertSame('Le titre', $stored['bf_titre']);
        $this->assertSame('The title', $stored[Translations::BODY_KEY]['en']['bf_titre']);
    }

    /** The trap the upstream branch fell into: editing while reading in another language. */
    public function testAnOrdinaryEditWhileReadingInAnotherLanguageDoesNotBakeItIn(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $entryManager->saveTranslations(self::ENTRY_TAG, 'en', ['bf_titre' => 'The title']);

        $this->serveIn('en');
        $entryManager->update(self::ENTRY_TAG, [
            'antispam' => 1,
            'bf_description' => 'Une description modifiée',
        ]);

        $this->serveIn('fr');
        $stored = $entryManager->getUntranslated(self::ENTRY_TAG);
        $this->assertIsArray($stored);

        $this->assertSame('Le titre', $stored['bf_titre']);
        $this->assertSame('Une description modifiée', $stored['bf_description']);
        $this->assertSame('The title', $stored[Translations::BODY_KEY]['en']['bf_titre']);
    }

    public function testAPostedTranslationStoreIsIgnoredOnAnOrdinaryWrite(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);

        $entryManager->update(self::ENTRY_TAG, [
            'antispam' => 1,
            'bf_titre' => 'Le titre',
            Translations::BODY_KEY => ['en' => ['bf_mail' => 'injecte@example.org']],
        ]);

        $stored = $entryManager->getUntranslated(self::ENTRY_TAG);
        $this->assertIsArray($stored);

        $this->assertArrayNotHasKey(Translations::BODY_KEY, $stored);
    }

    public function testAFormIsReadInTheReadersLanguage(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        $formManager->saveTranslations(self::FORM_ID, 'en', [
            'label' => 'Multilingual test form',
            'template.bf_titre.label' => 'Title',
        ]);

        $this->serveIn('en');
        $form = $formManager->getOne(self::FORM_ID);
        $this->assertIsArray($form);

        $this->assertSame('Multilingual test form', $form['label']);
        $this->assertSame('Une description en français', $form['description']);

        $labels = array_column($form['template'], 'label', 'name');
        $this->assertSame('Title', $labels['bf_titre']);
        $this->assertSame('Description', $labels['bf_description']);
    }

    public function testEditingAFormKeepsItsTranslations(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $formManager->saveTranslations(self::FORM_ID, 'en', ['label' => 'Multilingual test form']);

        $this->serveIn('en');
        $formManager->update([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test multilingue',
            'description' => 'Une description en français',
            'template' => json_encode(self::TEMPLATE),
        ]);

        $stored = $formManager->getUntranslated(self::FORM_ID);
        $this->assertIsArray($stored);

        $this->assertSame('Formulaire de test multilingue', $stored['label']);
        $this->assertSame('Multilingual test form', $stored[Translations::BODY_KEY]['en']['label']);
    }

    public function testDeletingAFieldForgetsItsTranslations(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $formManager->saveTranslations(self::FORM_ID, 'en', [
            'template.bf_titre.label' => 'Title',
            'template.bf_description.label' => 'Description in English',
        ]);

        $formManager->update([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test multilingue',
            'template' => json_encode([self::TEMPLATE[0], self::TEMPLATE[2]]),
        ]);

        $stored = $formManager->getUntranslated(self::FORM_ID);
        $this->assertIsArray($stored);

        $this->assertArrayHasKey('template.bf_titre.label', $stored[Translations::BODY_KEY]['en']);
        $this->assertArrayNotHasKey('template.bf_description.label', $stored[Translations::BODY_KEY]['en']);
    }

    public function testAValueListIsReadInTheReadersLanguage(): void
    {
        $wiki = $this->getWiki();
        $listManager = $wiki->services->get(ListManager::class);

        $listManager->create('Couleurs', [
            ['id' => 'rouge', 'label' => 'Rouge', 'children' => [['id' => 'carmin', 'label' => 'Carmin']]],
            ['id' => 'bleu', 'label' => 'Bleu'],
        ], self::LIST_ID);

        $listManager->saveTranslations(self::LIST_ID, 'en', [
            'title' => 'Colours',
            'nodes.rouge.label' => 'Red',
            'nodes.rouge.children.carmin.label' => 'Carmine',
        ]);

        $this->serveIn('en');
        $list = $listManager->getOne(self::LIST_ID);
        $this->assertIsArray($list);

        $this->assertSame('Colours', $list['title']);
        $this->assertSame('Red', $list['nodes'][0]['label']);
        $this->assertSame('Carmine', $list['nodes'][0]['children'][0]['label']);
        $this->assertSame('Bleu', $list['nodes'][1]['label']);
    }

    public function testEditingAValueListKeepsItsTranslations(): void
    {
        $wiki = $this->getWiki();
        $listManager = $wiki->services->get(ListManager::class);
        $listManager->create('Couleurs', [['id' => 'rouge', 'label' => 'Rouge']], self::LIST_ID);
        $listManager->saveTranslations(self::LIST_ID, 'en', ['nodes.rouge.label' => 'Red']);

        $this->serveIn('en');
        $listManager->update(self::LIST_ID, 'Couleurs', [
            ['id' => 'rouge', 'label' => 'Rouge'],
            ['id' => 'vert', 'label' => 'Vert'],
        ]);

        $stored = $listManager->getUntranslated(self::LIST_ID);
        $this->assertIsArray($stored);

        $this->assertSame('Rouge', $stored['nodes'][0]['label']);
        $this->assertSame('Red', $stored[Translations::BODY_KEY]['en']['nodes.rouge.label']);
    }

    /**
     * A page is drawn by EntryController, not by the raw-body branch of ShowHandler, so the overlay
     * has to be on the path a page actually takes -- which is where it was missing.
     */
    public function testAnOrdinaryPageIsDrawnInTheReadersLanguage(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, ['title' => 'Le titre', 'content' => 'Du contenu en français']);
        $pageManager->saveTranslations(self::PAGE_TAG, 'en', ['content' => 'Some content in English']);

        $this->serveIn('en');
        $drawn = $wiki->services->get(EntryController::class)->view(self::PAGE_TAG);

        $this->assertStringContainsString('Some content in English', $drawn);
        $this->assertStringNotContainsString('Du contenu en français', $drawn);
    }

    public function testOnlyProseFieldsAreOfferedForTranslation(): void
    {
        $wiki = $this->getWiki();
        $form = $wiki->services->get(FormManager::class)->getUntranslated(self::FORM_ID);
        $this->assertIsArray($form);

        $paths = array_column($wiki->services->get(TranslatableContent::class)->entryPaths($form), 'path');

        $this->assertContains('bf_titre', $paths);
        $this->assertContains('bf_description', $paths);
        $this->assertNotContains('bf_mail', $paths);
    }
}

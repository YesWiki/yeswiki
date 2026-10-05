<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A card shows each slot the way its field reads, the way the Doryphore card did. */
class EntryListCardTest extends YesWikiTestCase
{
    private const FORM_ID = '999931';
    private const LIST_ID = 'ListeEntryListCardTest';
    private const ENTRY_TAG = 'EntryListCardTestEntry';
    private const IMAGE = 'EntryListCardTestEntry_imagebf_image_affiche.png';

    private string $previousLanguage = 'fr';

    /** @var list<string> */
    private array $writtenFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('the card resizes its picture, which needs GD');
        }

        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $this->previousLanguage = $wiki->services->get(LanguageService::class)->preferredLanguage();

        $image = imagecreatetruecolor(900, 600);
        ob_start();
        imagepng($image);
        $this->writeFile('files/' . self::IMAGE, (string)ob_get_clean());

        $wiki->services->get(ListManager::class)->create('Types d\'activité', [
            ['id' => 'form', 'label' => 'Formation'],
            ['id' => 'conf', 'label' => 'Conférence'],
        ], self::LIST_ID);

        $wiki->services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test des cartes',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'listedatedeb', 'name' => 'bf_date_debut_evenement', 'label' => 'Début'],
                ['type' => 'liste', 'linked_object' => self::LIST_ID, 'name' => 'bf_type_act', 'label' => 'Type'],
                ['type' => 'image', 'name' => 'bf_image', 'label' => 'Image'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
            ]),
            'condition' => '',
        ]);

        $wiki->services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'tag' => self::ENTRY_TAG,
            'bf_titre' => 'Robustesse : du concept à l\'opérationnel',
            'bf_date_debut_evenement' => '2026-10-06T18:30:00+02:00',
            'bf_type_act' => 'form',
            'imagebf_image' => self::IMAGE,
            'bf_description' => 'Deux jours pour essayer',
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

        $storage = $wiki->services->get(Storage::class);
        foreach ($this->writtenFiles as $path) {
            if ($storage->exists($path)) {
                $storage->delete($path);
            }
        }
        foreach ($storage->glob('cache/' . pathinfo(self::IMAGE, PATHINFO_FILENAME) . '*') as $cached) {
            $storage->delete($cached);
        }

        parent::tearDown();
    }

    private function writeFile(string $path, string $contents): void
    {
        $this->getWiki()->services->get(Storage::class)->write($path, $contents);
        $this->writtenFiles[] = $path;
    }

    private function serveIn(string $language): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(LanguageService::class)->serveIn($language);
        $wiki->services->get(FormManager::class)->startNewRequest();
        $wiki->services->get(ListManager::class)->startNewRequest();
    }

    /** @param array<string, string> $extra */
    private function render(string $displayfields, array $extra = []): string
    {
        return (string)$this->getWiki()->services->get(Performer::class)->run('entrylist', 'action', [
            'id' => self::FORM_ID,
            'template' => 'card',
            'displayfields' => $displayfields,
            'columns' => '4',
        ] + $extra);
    }

    private function visibleText(string $html): string
    {
        $withoutAttributes = (string)preg_replace('/<[^>]*>/', ' ', $html);

        return (string)preg_replace('/\s+/', ' ', html_entity_decode($withoutAttributes));
    }

    private function imageSource(string $html): string
    {
        if (preg_match('#<img class="yw-item__image"\s+src="([^"]+)"#', $html, $match) !== 1) {
            $this->fail('the card draws no picture');
        }

        return html_entity_decode($match[1]);
    }

    public function testTheMigratedAgendaCardReadsLikeDoryphores(): void
    {
        $this->serveIn('fr');
        $html = $this->render('visual=imagebf_image,title=bf_titre,footer=bf_type_act,floating=bf_date_debut_evenement');
        $text = $this->visibleText($html);

        $this->assertStringContainsString('class="yw-item__footer">Formation</div>', $html, 'the footer band shows the label of the list value');
        $this->assertStringNotContainsString('>form<', $html, 'never the stored key');

        $this->assertMatchesRegularExpression('#yw-item__badge-day">\s*6\s*<#', $html);
        $this->assertMatchesRegularExpression('#yw-item__badge-month">\s*oct\s*<#', $html);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $text, 'a visitor never reads an ISO date');

        $src = $this->imageSource($html);
        $base = $this->getWiki()->services->get(UrlFormatter::class)->getBaseUrl() . '/';
        $this->assertStringStartsWith($base, $src);
        $path = substr($src, strlen($base));
        $this->assertTrue($this->getWiki()->services->get(Storage::class)->exists($path), "the <img> points at a file that is there: $path");
        $this->assertStringContainsString('_cropped_', $path, 'a cover card cuts its picture to the frame');
    }

    public function testADateInAnotherSlotIsWrittenOutInTheReadersLanguage(): void
    {
        $this->serveIn('en');
        $html = $this->render('title=bf_titre,subtitle=bf_date_debut_evenement,text=bf_description');
        $text = $this->visibleText($html);

        $this->assertStringContainsString('October 6, 2026', $text);
        $this->assertStringContainsString('Deux jours pour essayer', $text, '`text` is the Doryphore name of the description slot');
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $text);
    }

    public function testAPortraitDirectoryKeepsTheWholePicture(): void
    {
        $this->serveIn('fr');
        $html = $this->render('visual=imagebf_image,title=bf_titre', ['imgstyle' => 'contain']);

        $this->assertStringContainsString('--yw-item-image-fit: contain', $html);
        $this->assertStringContainsString('_vignette_', $this->imageSource($html), 'contain fits the picture instead of cropping it');
    }
}

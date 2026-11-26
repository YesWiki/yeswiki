<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\ImageResizer;
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
        return (string)$this->getWiki()->services->get(Performer::class)->run('entrylist', 'action', $extra + [
            'id' => self::FORM_ID,
            'template' => 'card',
            'displayfields' => $displayfields,
            'columns' => '4',
        ]);
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

        if (preg_match('#data-yw-original="([^"]+)"#', $html, $original) !== 1) {
            $this->fail("no original picture in:\n" . $html);
        }
        $base = $this->getWiki()->services->get(UrlFormatter::class)->getBaseUrl() . '/';
        $this->assertStringStartsWith($base, $original[1]);
        $path = substr($original[1], strlen($base));
        $this->assertTrue($this->getWiki()->services->get(Storage::class)->exists($path), "the <img> falls back on a file that is there: $path");
        $this->assertSame('crop', $this->thumbnailRequest($html)['mode'], 'a cover card cuts its picture to the frame');
    }

    public function testTheColourFieldPutsAPelletBeforeTheTitle(): void
    {
        $this->serveIn('fr');
        $palette = \YesWiki\Content\Service\EntryDisplay::categoryColorFor('form');

        $html = $this->render('title=bf_titre', ['colorfield' => 'bf_type_act']);
        $this->assertMatchesRegularExpression('#class="yw-item__title"[^>]*>\s*<span class="pellet" style="background-color: ' . preg_quote($palette, '#') . '"#', $html, 'a value with no stated colour takes its palette slot, as on the map');

        $html = $this->render('title=bf_titre', ['colorfield' => 'bf_type_act', 'color' => '#c0ffee=form']);
        $this->assertStringContainsString('<span class="pellet" style="background-color: #c0ffee"', $html, 'a stated mapping wins');

        $this->assertStringNotContainsString('class="pellet"', $this->render('title=bf_titre'), 'no colour field, no pellet');
    }

    public function testTheTimelineDatesItsEntriesByTheirStartAndHeadsEachYear(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre', ['template' => 'timeline']);

        $this->assertStringContainsString('<h2 class="yw-items__year">2026</h2>', $html, 'with no date slot, the timeline reads the start date role');
        $this->assertMatchesRegularExpression('#<time class="yw-item__date" datetime="2026-10-06#', $html);
    }

    public function testThePrintableCartelsFillAnA4SheetWithAQrCodeToEachEntry(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre,text=bf_description', ['template' => 'print-cartels', 'size' => 'a7', 'printfields' => 'bf_type_act']);

        $this->assertStringContainsString('class="yw-print yw-print--cartels yw-print--a7"', $html);
        $this->assertSame(1, substr_count($html, 'class="yw-print__sheet"'), 'one entry fits on one sheet');
        $this->assertStringContainsString('Robustesse : du concept à l&#039;opérationnel', $html);
        $this->assertStringContainsString('Deux jours pour essayer', $this->visibleText($html));
        $this->assertStringContainsString('Formation', $this->visibleText($html), 'a printed field reads as the entry shows it');

        if (preg_match('#class="yw-print__qr" src="([^"]+)"#', $html, $qr) !== 1) {
            $this->fail("no QR code in:\n" . $html);
        }
        $this->writtenFiles[] = $qr[1];
        $this->assertTrue($this->getWiki()->services->get(Storage::class)->exists($qr[1]), "the QR code is written where the page reads it: {$qr[1]}");

        $without = $this->render('title=bf_titre', ['template' => 'print-cartels', 'qrcode' => '0', 'size' => 'nonsense']);
        $this->assertStringNotContainsString('yw-print__qr', $without);
        $this->assertStringContainsString('class="yw-print yw-print--cartels yw-print--a6"', $without, 'an unknown size falls back on A6');
    }

    public function testEveryPrintablePresentationDrawsTheEntryOnAnA4Sheet(): void
    {
        $this->serveIn('fr');
        foreach (['print-chevalet', 'print-zine', 'print-badges', 'print-cartes', 'print-affiche', 'print-frise', 'print-cube', 'print-croix', 'print-pied', 'print-tour', 'print-triangle', 'print-totem'] as $template) {
            $html = $this->render('title=bf_titre,text=bf_description', ['template' => $template, 'qrcode' => '0']);
            $this->assertStringContainsString('class="yw-print__sheet', $html, "{$template} draws no sheet");
            $this->assertStringContainsString('>Robustesse', $html, "{$template} leaves the entry out");
            $this->assertStringContainsString('data-yw-print', $html, "{$template} has no print button");
        }
    }

    public function testThePlayingCardsPutEachBackBehindItsFront(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre,text=bf_description', ['template' => 'print-cartes', 'qrcode' => '0']);

        $this->assertSame(2, substr_count($html, 'class="yw-print__sheet'), 'a front sheet and its back');
        $back = substr($html, (int)strpos($html, 'yw-cards--verso'));
        $this->assertSame(9, substr_count($back, 'class="yw-card '), 'the back sheet keeps all nine places');
        $this->assertLessThan(strpos($back, 'yw-card--back'), strpos($back, 'yw-card--empty'), 'flipped on the long edge, the first card\'s back is the third in its row');
        $this->assertStringNotContainsString('yw-cards--verso', $this->render('title=bf_titre', ['template' => 'print-cartes', 'verso' => '0']));
    }

    public function testTheCrossStandSplitsEachEntryAcrossBothPieces(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre,text=bf_description', ['template' => 'print-croix', 'qrcode' => '0']);

        $this->assertSame(2, substr_count($html, 'class="yw-print__sheet'), 'one sheet per piece');
        $this->assertSame(2, substr_count($html, 'Robustesse : du concept'), 'with no picture, the picture panel shows the title beside the text panel');
        $this->assertSame(4, substr_count($html, 'yw-print__panel--flipped'), 'the top half of each piece folds behind it');
    }

    public function testSlottedStandsAlwaysPrintEveryPanelTheyNeedToStand(): void
    {
        $this->serveIn('fr');
        $tower = $this->render('title=bf_titre', ['template' => 'print-tour', 'qrcode' => '0']);
        $this->assertSame(4, substr_count($tower, 'class="yw-print__sheet'), 'one entry still makes a tower of four walls');
        $this->assertSame(2, substr_count($tower, 'V105 H60.6 V10'), 'walls 1 and 3 are slotted from the top');

        $triangle = $this->render('title=bf_titre', ['template' => 'print-triangle', 'qrcode' => '0']);
        $this->assertSame(3, substr_count($triangle, 'class="yw-print__sheet'));
    }

    public function testTheCounterTotemFoldsIntoTwoOrThreeFaces(): void
    {
        $this->serveIn('fr');
        $ellipse = $this->render('title=bf_titre', ['template' => 'print-totem', 'qrcode' => '0']);
        $this->assertSame(2, substr_count($ellipse, 'class="yw-print__panel yw-totem__face '), 'the elliptical totem bows two faces');

        $triangle = $this->render('title=bf_titre', ['template' => 'print-totem', 'forme' => 'triangle', 'qrcode' => '0']);
        $this->assertSame(3, substr_count($triangle, 'class="yw-print__panel yw-totem__face '), 'the triangular one folds in three');
    }

    public function testPrintableTemplatesCutTextAtTheirLimitAndFollowThePicturesShape(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre,visual=imagebf_image,text=bf_description', ['template' => 'print-cartels', 'maxtext' => '20', 'qrcode' => '0']);

        $this->assertStringContainsString('<p>Deux jours pour…</p>', $html, 'past its limit, the text stops at the last whole word');
        $this->assertStringContainsString('yw-print__layout is-landscape', $html, 'a 900 × 600 picture is laid out as a landscape one');
        $this->assertStringContainsString('texte 20', html_entity_decode($html), 'the instructions show the limit the page uses');
        $this->assertMatchesRegularExpression('#<img class="yw-print__image" src="[^"]*imagebf_image_affiche\.png"#', $html, 'the print gets the whole picture, not a card\'s crop');
    }

    public function testAPosterOpensOnItsPictureWhateverItsShape(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre,visual=imagebf_image', ['template' => 'print-affiche', 'tiles' => '1x3', 'qrcode' => '0']);

        $this->assertSame(3, substr_count($html, 'class="yw-print__layout is-landscape is-hero"'), 'each of the three sheets carries the whole poster, picture first');
        $this->assertStringContainsString('yw-poster--tower', $html, 'a 1 × 3 banner is laid out as a tower');
        $this->assertLessThan(strpos($html, 'class="yw-print__title"'), strpos($html, 'class="yw-print__image"'));
    }

    public function testTheZineLaysItsEightPagesOutForFolding(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre', ['template' => 'print-zine', 'cover' => 'Mon livret', 'qrcode' => '0']);

        $this->assertStringContainsString('Mon livret', $html);
        $this->assertSame(8, substr_count($html, 'class="yw-zine__page'));
        $this->assertSame(4, substr_count($html, 'yw-zine__page--flipped'), 'the top row prints upside down');
        preg_match_all('#yw-zine__folio">(\d)<#', $html, $folios);
        $this->assertSame(['5', '4', '3', '2', '6', '7'], $folios[1], 'pages run 5 4 3 2 on top, 6 7 then the covers below');
    }

    public function testThePrintableTimelineDatesItsEntriesByTheirStart(): void
    {
        $this->serveIn('fr');
        $html = $this->render('title=bf_titre', ['template' => 'print-frise']);

        $this->assertMatchesRegularExpression('#<time datetime="2026-10-06#', $html, 'with no date slot, the timeline reads the start date role');
        $this->assertStringContainsString('6 octobre 2026', $this->visibleText($html));
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
        $this->assertSame('fit', $this->thumbnailRequest($html)['mode'], 'contain fits the picture instead of cropping it');
    }

    /** A thumbnail not cached yet is asked for by the page, not made while it renders: four large pictures outran the time limit. */
    public function testAMissingThumbnailIsLeftToThePage(): void
    {
        $this->serveIn('fr');
        $html = $this->render('visual=imagebf_image,title=bf_titre');

        $request = $this->thumbnailRequest($html);
        $this->assertSame(self::IMAGE, $request['filename']);
        $this->assertNotSame('', $request['token']);
        $this->assertStringContainsString('<noscript>', $html, 'without JavaScript the picture itself shows');
        $this->assertSame([], $this->getWiki()->services->get(Storage::class)->glob('cache/' . pathinfo(self::IMAGE, PATHINFO_FILENAME) . '*'));
    }

    public function testACachedThumbnailIsServedAsItIs(): void
    {
        $this->serveIn('fr');
        $services = $this->getWiki()->services;
        $services->get(ImageResizer::class)->cached('files/' . self::IMAGE, '586', '390', 'crop');

        $html = $this->render('visual=imagebf_image,title=bf_titre');

        $this->assertStringContainsString('_cropped_', $this->imageSource($html));
        $this->assertStringNotContainsString('data-yw-thumbnail', $html);
    }

    /** @return array{filename: string, width: int, height: int, mode: string, token: string} */
    private function thumbnailRequest(string $html): array
    {
        if (preg_match('#data-yw-thumbnail="([^"]+)"#', $html, $match) !== 1) {
            $this->fail("no thumbnail request in:\n" . $html);
        }

        return json_decode(html_entity_decode($match[1]), true);
    }
}

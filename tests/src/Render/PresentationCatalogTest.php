<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FormManager;
use YesWiki\Render\Entity\Presentation;
use YesWiki\Render\Service\PresentationCatalog;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A list template declares itself in its own header, and the form screen offers the ones a form can draw. */
class PresentationCatalogTest extends YesWikiTestCase
{
    private const CUSTOM_DIR = PresentationCatalog::CUSTOM_PREFIX . 'entries/index-dynamic-templates';
    private const WITH_HEADER = self::CUSTOM_DIR . '/zz-probe-gallery.twig';
    private const BARE = self::CUSTOM_DIR . '/zz-probe-bare.twig';

    private static ?string $plainFormId = null;
    private static ?string $richFormId = null;

    public static function tearDownAfterClass(): void
    {
        $formManager = self::getWiki()->services->get(FormManager::class);
        foreach ([self::$plainFormId, self::$richFormId] as $id) {
            if ($id !== null) {
                $formManager->delete($id);
            }
        }
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        @unlink(self::WITH_HEADER);
        @unlink(self::BARE);
        @rmdir(self::CUSTOM_DIR);
        @rmdir(\dirname(self::CUSTOM_DIR));
        parent::tearDown();
    }

    private function catalog(): PresentationCatalog
    {
        return new PresentationCatalog(
            $this->getWiki()->services->get(\YesWiki\Files\Service\ProgramFiles::class),
            $this->getWiki()->services->get(\YesWiki\Files\Service\Storage::class),
            $this->getWiki()->services->get(\YesWiki\Content\Service\FieldRoleResolver::class),
        );
    }

    public function testTheShippedTemplatesAreListedAsTheirHeadersSay(): void
    {
        $byName = [];
        foreach ($this->catalog()->all() as $presentation) {
            $byName[$presentation->name] = $presentation;
        }

        $expected = [
            'card' => ['card', [], true],
            'list' => ['list', [], true],
            'table' => ['table', [], true],
            'timeline' => ['list', [FieldRole::START_DATE], true],
            'calendar' => ['calendar', [FieldRole::START_DATE], false],
            'map' => ['map', [FieldRole::GEOLOCATION], false],
            'map-and-table' => ['map', [FieldRole::GEOLOCATION], false],
        ];
        foreach ($expected as $name => [$category, $requires, $shared]) {
            $this->assertArrayHasKey($name, $byName, "{$name} is not in the catalog");
            $this->assertSame($category, $byName[$name]->category, "{$name} is in the wrong category");
            $this->assertSame($requires, $byName[$name]->requires, "{$name} requires the wrong roles");
            $this->assertSame($shared, $byName[$name]->shared, "{$name} is (not) a shared shape");
            $this->assertFalse($byName[$name]->custom);
        }
        $this->assertSame(_t('PRESENTATION_MAP_AND_TABLE'), $byName['map-and-table']->label, 'the label is not translated');
        $this->assertSame('pin', $byName['map-and-table']->icon, 'an explicit icon is not kept');
        $this->assertSame(Presentation::categoryIcon('calendar'), $byName['calendar']->icon, 'a template without an icon does not take its category\'s');
    }

    public function testPartialsAreNotPresentations(): void
    {
        $names = array_map(static fn (Presentation $p) => $p->name, $this->catalog()->all());

        $this->assertNotContains('_map_popup_html', $names);
        $this->assertNotContains('_item-fields', $names);
    }

    public function testACustomTemplateIsListedWithWhatItsHeaderDeclares(): void
    {
        @mkdir(self::CUSTOM_DIR, 0o775, true);
        file_put_contents(self::WITH_HEADER, "{# presentation\n   label: Galerie photo\n   category: card\n   icon: photo\n   requires: image, description\n#}\n{% extends '@core/entries/index-dynamic.twig' %}\n");
        file_put_contents(self::BARE, "{% extends '@core/entries/index-dynamic.twig' %}\n");

        $catalog = $this->catalog();

        $gallery = $catalog->get('zz-probe-gallery');
        $this->assertNotNull($gallery, 'a template dropped in custom/ is not listed');
        $this->assertSame('Galerie photo', $gallery->label, 'a literal label is not kept as written');
        $this->assertSame('card', $gallery->category);
        $this->assertSame('photo', $gallery->icon);
        $this->assertSame([FieldRole::IMAGE, FieldRole::DESCRIPTION], $gallery->requires);
        $this->assertTrue($gallery->custom);
        $this->assertFalse($gallery->shared);

        $bare = $catalog->get('zz-probe-bare.twig');
        $this->assertNotNull($bare, 'a template with no header is not listed');
        $this->assertSame('zz-probe-bare', $bare->label, 'a template with no header is not named after its file');
        $this->assertSame(Presentation::DEFAULT_CATEGORY, $bare->category);
        $this->assertSame([], $bare->requires);
    }

    public function testAFormIsOfferedOnlyWhatItsFieldsCanDraw(): void
    {
        $catalog = $this->catalog();

        $plain = array_map(static fn (Presentation $p) => $p->name, $catalog->fitting($this->plainForm()));
        $this->assertSame(['card', 'list', 'table'], $plain, 'a form with only a text field is offered a date or a map display');

        $rich = array_map(static fn (Presentation $p) => $p->name, $catalog->fitting($this->richForm()));
        foreach (['card', 'list', 'table', 'timeline', 'calendar', 'map', 'map-and-table'] as $name) {
            $this->assertContains($name, $rich, "{$name} is not offered to a form with a date and a location");
        }
    }

    public function testTheSwitcherGroupsByCategoryInAFixedOrderAndSkipsEmptyOnes(): void
    {
        $catalog = $this->catalog();

        $plain = $catalog->switcherFor($this->plainForm());
        $this->assertSame(['card', 'list', 'table'], array_column($plain, 'name'));

        $rich = $catalog->switcherFor($this->richForm());
        $this->assertSame(['card', 'list', 'table', 'map', 'calendar'], array_column($rich, 'name'));
        $byCategory = array_column($rich, 'presentations', 'name');
        $this->assertSame(['list', 'timeline'], array_column($byCategory['list'], 'name'));
        $this->assertSame(['map', 'map-and-table'], array_column($byCategory['map'], 'name'));
        $this->assertSame(_t('PRESENTATION_MAP'), $rich[3]['label']);
        $this->assertSame('map-2', $rich[3]['icon']);
    }

    public function testRequiredRolesCoverTheCatalogAndTheTemplatesOutsideIt(): void
    {
        $catalog = $this->catalog();

        $this->assertSame([FieldRole::GEOLOCATION], $catalog->requiredRoles('map.twig'));
        $this->assertSame([FieldRole::START_DATE], $catalog->requiredRoles('calendar'));
        $this->assertSame([], $catalog->requiredRoles('card'));
        $this->assertSame([FieldRole::START_DATE], $catalog->requiredRoles('agenda'), 'the legacy agenda lost its requirement');
        $this->assertSame([], $catalog->requiredRoles('no-such-template'));
    }

    /** @return array<string, mixed> */
    private function plainForm(): array
    {
        if (self::$plainFormId === null) {
            self::$plainFormId = $this->createForm('PresentationCatalogTest plain', [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
            ]);
        }

        return $this->form(self::$plainFormId);
    }

    /** @return array<string, mixed> */
    private function richForm(): array
    {
        if (self::$richFormId === null) {
            self::$richFormId = $this->createForm('PresentationCatalogTest rich', [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'listedatedeb', 'name' => 'bf_date_debut_evenement', 'label' => 'Début'],
                ['type' => 'map', 'name' => 'bf_latitude', 'label' => 'bf_longitude'],
            ]);
        }

        return $this->form(self::$richFormId);
    }

    /** @param list<array<string, string>> $fields */
    private function createForm(string $label, array $fields): string
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $id = 9700;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->assertSame(0, $formManager->create([
            'id' => (string)$id,
            'label' => $label,
            'entry_title_template' => '{{bf_titre}}',
            'template' => $fields,
        ]));

        return (string)$id;
    }

    /** @return array<string, mixed> */
    private function form(string $id): array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getOne($id);
        $this->assertNotNull($form);

        return $form;
    }
}

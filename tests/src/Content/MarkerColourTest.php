<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A map coloured by a list gives each value its own colour (end-users 30). */
class MarkerColourTest extends YesWikiTestCase
{
    private const FORM_ID = '999930';
    private const LIST_ID = 'ListeMarkerColourTest';
    private const ENTRY_TAGS = ['MarkerColourTestSol', 'MarkerColourTestEau'];

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $wiki->services->get(ListManager::class)->create('Thèmes', [
            ['id' => 'eau', 'label' => 'Eau', 'color' => '#1e6fd9'],
            ['id' => 'sol', 'label' => 'Sol'],
            ['id' => 'air', 'label' => 'Air'],
            ['id' => 'feu', 'label' => 'Feu'],
            ['id' => 'bois', 'label' => 'Bois'],
            ['id' => 'pierre', 'label' => 'Pierre'],
        ], self::LIST_ID);
        $wiki->services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test des marqueurs',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'checkbox', 'linked_object' => self::LIST_ID, 'name' => 'bf_theme', 'label' => 'Thème'],
                ['type' => 'map', 'name' => 'bf_geolocation', 'label' => 'Lieu'],
            ]),
            'condition' => '',
        ]);
        foreach (array_combine(self::ENTRY_TAGS, ['sol', 'eau']) as $tag => $theme) {
            $wiki->services->get(EntryManager::class)->create(self::FORM_ID, [
                'antispam' => 1,
                'tag' => $tag,
                'bf_titre' => $tag,
                'bf_theme' => $theme,
                'bf_geolocation' => ['latitude' => '44.389', 'longitude' => '5.099'],
            ]);
        }
    }

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $entries = $wiki->services->get(EntryManager::class);
        foreach (self::ENTRY_TAGS as $tag) {
            if ($entries->isEntry($tag)) {
                $entries->delete($tag, true);
            }
        }
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        parent::tearDown();
    }

    private function colourOf(mixed $parameter, ?string $field, string $values): string
    {
        return $this->getWiki()->services->get(EntryDisplay::class)
            ->markerColorFor($parameter, $field, ['form_id' => self::FORM_ID, 'bf_theme' => $values]);
    }

    public function testEachValueAlwaysLandsOnTheSameToken(): void
    {
        $this->assertSame('var(--yw-category-5)', EntryDisplay::categoryColorFor('1'));
        $this->assertSame('var(--yw-category-6)', EntryDisplay::categoryColorFor('2'));
        $this->assertSame('var(--yw-category-8)', EntryDisplay::categoryColorFor('val1'));
        $this->assertSame('var(--yw-category-8)', EntryDisplay::categoryColorFor('atelier'));
        $this->assertSame('var(--yw-category-2)', EntryDisplay::categoryColorFor('é'));
    }

    public function testUncolouredValuesGetDistinctStableColours(): void
    {
        $colours = array_map(fn ($value) => $this->colourOf(null, 'bf_theme', $value), ['sol', 'air', 'feu', 'bois', 'pierre']);

        foreach ($colours as $colour) {
            $this->assertMatchesRegularExpression('/^var\(--yw-category-[1-8]\)$/', $colour);
        }
        $this->assertCount(5, array_unique($colours), implode(', ', $colours));
        $this->assertSame($colours[0], $this->colourOf(null, 'bf_theme', 'sol'));
    }

    public function testTheOrderIsMappingThenListThenPalette(): void
    {
        $this->assertSame('#1e6fd9', $this->colourOf(null, 'bf_theme', 'eau'));
        $this->assertSame('red', $this->colourOf(['eau' => 'red'], 'bf_theme', 'eau'));
        $this->assertSame('#1e6fd9', $this->colourOf(['sol' => 'red'], 'bf_theme', 'eau,sol'));
        $this->assertSame(EntryDisplay::categoryColorFor('sol'), $this->colourOf(['air' => 'red'], 'bf_theme', 'sol'));
    }

    public function testNoValueLeavesTheColourToTheStylesheet(): void
    {
        $this->assertSame('', $this->colourOf(null, 'bf_theme', ''));
        $this->assertSame('', $this->colourOf(null, null, 'sol'));
        $this->assertSame('', $this->colourOf('', null, 'sol'));
    }

    public function testTheFieldTellsTheBrowserTheListColours(): void
    {
        $field = $this->getWiki()->services->get(FormManager::class)->findFieldFromNameOrPropertyName('bf_theme', self::FORM_ID);
        $json = json_decode((string)json_encode($field), true);

        $this->assertSame(['eau' => '#1e6fd9'], $json['optionColors']);
    }

    /**
     * @param array<string, string> $arguments
     *
     * @return array<string, string> tag => the colour its marker is drawn in
     */
    private function markerColours(array $arguments = []): array
    {
        $html = (string)$this->getWiki()->services->get(Performer::class)
            ->run('entrylist', 'action', ['id' => self::FORM_ID, 'template' => 'map'] + $arguments);
        if (preg_match('#class="yw-entry-map__data">(.*?)</script>#s', $html, $match) !== 1) {
            $this->fail('the map sent no points');
        }
        $colours = [];
        foreach (json_decode($match[1], true)['points'] as $point) {
            $colours[$point['tag']] = $point['color'] ?? '';
        }

        return $colours;
    }

    public function testTheMapColoursEachValue(): void
    {
        $colours = $this->markerColours(['colorfield' => 'bf_theme']);

        $this->assertSame('#1e6fd9', $colours['MarkerColourTestEau']);
        $this->assertSame(EntryDisplay::categoryColorFor('sol'), $colours['MarkerColourTestSol']);
    }

    public function testTheMapWithoutAFieldLeavesTheColourToTheStylesheet(): void
    {
        $this->assertSame(['', ''], array_values($this->markerColours()));
    }
}

<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\GeoJSONFormatter;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Entries become GeoJSON features from their point and from the shapes drawn on their map field.
 */
class GeoJSONFormatterTest extends YesWikiTestCase
{
    private $wiki;
    private string $formId;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->formId = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'GeoJSON formatter test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'map***bf_geolocation***Géolocalisation*** *** *** *** *** ***0***marker, polygon, circle*** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(FormManager::class)->delete($this->formId);
    }

    private function entry(string $id, string $latitude, string $longitude, array $drawings): array
    {
        return [
            'id_fiche' => $id,
            'id_typeannonce' => $this->formId,
            'bf_titre' => "Title of $id",
            'bf_geolocation' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geometries' => empty($drawings) ? '' : json_encode(['type' => 'FeatureCollection', 'features' => $drawings]),
            ],
        ];
    }

    private function polygon(): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['color' => '#ff0000'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[2.30, 48.87], [2.31, 48.87], [2.31, 48.88], [2.30, 48.87]]]],
        ];
    }

    private function circle(): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['type' => 'circle', 'radius' => 83.4],
            'geometry' => ['type' => 'Point', 'coordinates' => [2.303, 48.870]],
        ];
    }

    private function format(array $entries): array
    {
        return $this->wiki->services->get(GeoJSONFormatter::class)->formatToGeoJSON($entries);
    }

    public function testAnEntryWithOnlyAPointIsOnePointFeatureWithNumericCoordinates()
    {
        $geojson = $this->format([$this->entry('PointOnly', '44.389', '5.099', [])]);

        $this->assertSame('FeatureCollection', $geojson['type']);
        $this->assertCount(1, $geojson['features']);
        $feature = $geojson['features'][0];
        $this->assertSame(['type' => 'Point', 'coordinates' => [5.099, 44.389]], $feature['geometry']);
        $this->assertSame('PointOnly', $feature['id']);
        $this->assertSame('Title of PointOnly', $feature['properties']['bf_titre']);
    }

    public function testAnEntryWithOnlyDrawnShapesKeepsEveryShape()
    {
        $geojson = $this->format([$this->entry('ShapesOnly', '', '', [$this->polygon(), $this->circle()])]);

        $this->assertCount(2, $geojson['features']);
        [$polygon, $circle] = $geojson['features'];
        $this->assertSame('Polygon', $polygon['geometry']['type']);
        $this->assertSame('#ff0000', $polygon['properties']['color']);
        $this->assertSame('Title of ShapesOnly', $polygon['properties']['bf_titre']);
        $this->assertSame('Point', $circle['geometry']['type']);
        $this->assertSame(83.4, $circle['properties']['radius']);
        $this->assertSame('ShapesOnly', $circle['properties']['id_fiche']);
        $this->assertNotSame($polygon['id'], $circle['id']);
    }

    public function testAnEntryWithAPointAndShapesHasAFeatureForEach()
    {
        $geojson = $this->format([$this->entry('Both', '44.389', '5.099', [$this->polygon()])]);

        $this->assertSame(['Point', 'Polygon'], array_map(fn ($feature) => $feature['geometry']['type'], $geojson['features']));
        $this->assertSame('Both', $geojson['features'][0]['id']);
    }

    public function testTheDrawnShapesAreNotRepeatedAsATextProperty()
    {
        $geojson = $this->format([$this->entry('Both', '44.389', '5.099', [$this->polygon()])]);

        foreach ($geojson['features'] as $feature) {
            $this->assertArrayNotHasKey('geometries', $feature['properties']['bf_geolocation']);
        }
    }

    public function testAnEntryWithoutLocationIsLeftOutAndAnEmptyListIsStillACollection()
    {
        $geojson = $this->format([$this->entry('Nowhere', '', '', [])]);

        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], $geojson);
    }

    public function testAnEntryOfAFormWithoutMapFieldUsesItsLegacyCoordinates()
    {
        $geojson = $this->format([[
            'id_fiche' => 'Legacy',
            'id_typeannonce' => 'not-a-form',
            'bf_titre' => 'Legacy',
            'bf_latitude' => '44.389',
            'bf_longitude' => '5.099',
        ]]);

        $this->assertSame([5.099, 44.389], $geojson['features'][0]['geometry']['coordinates']);
    }
}

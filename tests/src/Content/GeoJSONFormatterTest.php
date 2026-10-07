<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\GeoJSONFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** GeoJSON output of entries. */
class GeoJSONFormatterTest extends YesWikiTestCase
{
    private const FORM_ID = '999964';

    public static function setUpBeforeClass(): void
    {
        $formManager = self::getWiki()->services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'GeoJSON formatter test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1'],
                ['type' => 'map', 'name' => 'bf_geolocation', 'label' => 'Géolocalisation', 'geometries' => 'marker,polygon,circle'],
            ]),
            'condition' => '',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /**
     * @param list<array<string, mixed>> $drawings
     *
     * @return array<string, mixed>
     */
    private function entry(string $tag, string $latitude, string $longitude, array $drawings): array
    {
        return [
            'tag' => $tag,
            'form_id' => self::FORM_ID,
            'title' => "Title of $tag",
            'bf_titre' => "Title of $tag",
            'bf_geolocation' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geometries' => empty($drawings) ? '' : json_encode(['type' => 'FeatureCollection', 'features' => $drawings]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function polygon(): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['color' => '#ff0000'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[2.30, 48.87], [2.31, 48.87], [2.31, 48.88], [2.30, 48.87]]]],
        ];
    }

    /** @return array<string, mixed> */
    private function circle(): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['type' => 'circle', 'radius' => 83.4],
            'geometry' => ['type' => 'Point', 'coordinates' => [2.303, 48.870]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array<string, mixed>
     */
    private function format(array $entries): array
    {
        return $this->getWiki()->services->get(GeoJSONFormatter::class)->formatToGeoJSON($entries);
    }

    public function testAnEntryWithOnlyAPointIsOnePointFeatureWithNumericCoordinates(): void
    {
        $geojson = $this->format([$this->entry('PointOnly', '44.389', '5.099', [])]);

        $this->assertSame('FeatureCollection', $geojson['type']);
        $this->assertCount(1, $geojson['features']);
        $feature = $geojson['features'][0];
        $this->assertSame(['type' => 'Point', 'coordinates' => [5.099, 44.389]], $feature['geometry']);
        $this->assertSame('PointOnly', $feature['id']);
        $this->assertSame('Title of PointOnly', $feature['title']);
        $this->assertSame('Title of PointOnly', $feature['properties']['bf_titre']);
    }

    public function testAnEntryWithOnlyDrawnShapesKeepsEveryShape(): void
    {
        $geojson = $this->format([$this->entry('ShapesOnly', '', '', [$this->polygon(), $this->circle()])]);

        $this->assertCount(2, $geojson['features']);
        [$polygon, $circle] = $geojson['features'];
        $this->assertSame('Polygon', $polygon['geometry']['type']);
        $this->assertSame('#ff0000', $polygon['properties']['color']);
        $this->assertSame('Title of ShapesOnly', $polygon['properties']['bf_titre']);
        $this->assertSame('Point', $circle['geometry']['type']);
        $this->assertSame(83.4, $circle['properties']['radius']);
        $this->assertSame('ShapesOnly', $circle['properties']['tag']);
        $this->assertSame('ShapesOnly-1', $polygon['id']);
        $this->assertSame('ShapesOnly-2', $circle['id']);
    }

    public function testAnEntryWithAPointAndShapesHasAFeatureForEach(): void
    {
        $geojson = $this->format([$this->entry('Both', '44.389', '5.099', [$this->polygon()])]);

        $this->assertSame(['Point', 'Polygon'], array_map(fn ($feature) => $feature['geometry']['type'], $geojson['features']));
        $this->assertSame('Both', $geojson['features'][0]['id']);
    }

    public function testTheDrawnShapesAreNotRepeatedAsATextProperty(): void
    {
        $geojson = $this->format([$this->entry('Both', '44.389', '5.099', [$this->polygon()])]);

        foreach ($geojson['features'] as $feature) {
            $this->assertArrayNotHasKey('geometries', $feature['properties']['bf_geolocation']);
        }
    }

    public function testAnEntryWithoutLocationIsLeftOutAndAnEmptyListIsStillACollection(): void
    {
        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], $this->format([$this->entry('Nowhere', '', '', [])]));
        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], $this->format([]));
    }

    public function testAnEntryOfAFormWithoutMapFieldUsesItsLegacyCoordinates(): void
    {
        $geojson = $this->format([[
            'tag' => 'Legacy',
            'form_id' => 'not-a-form',
            'bf_titre' => 'Legacy',
            'bf_latitude' => '44.389',
            'bf_longitude' => '5.099',
        ]]);

        $this->assertSame([5.099, 44.389], $geojson['features'][0]['geometry']['coordinates']);
    }
}

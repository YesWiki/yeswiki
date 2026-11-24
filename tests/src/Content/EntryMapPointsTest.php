<?php

namespace YesWiki\Test\Content;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Action\EntryListAction;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The map gets one point per located entry, and a broken geometry leaves the entry out rather than the whole map. */
class EntryMapPointsTest extends YesWikiTestCase
{
    private const VALID = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[2.35,48.85]}}]}';
    private const NOT_GEOJSON = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null}]}';
    private const TRUNCATED = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polyg';

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, string>      $arguments
     *
     * @return array<string, mixed>
     */
    private function mapFor(array $entries, array $arguments = []): array
    {
        $wiki = $this->getWiki();
        $action = new EntryListAction();
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(ParameterBagInterface::class));
        $action->setTwig($wiki->services->get(TemplateEngine::class));
        $arguments += ['template' => 'map'];
        $action->setArguments($arguments);

        return (new \ReflectionMethod(EntryListAction::class, 'mapSetup'))->invoke($action, $entries, []);
    }

    /**
     * @param array<string, mixed> $setup
     *
     * @return list<string>
     */
    private function tags(array $setup): array
    {
        return array_column($setup['points'], 'tag');
    }

    public function testAGeometryIsSentAsItsFeatures(): void
    {
        $setup = $this->mapFor([['tag' => 'GeoValide', 'bf_titre' => 'Valide', 'bf_geolocation' => ['geometries' => self::VALID]]]);

        $this->assertSame(['GeoValide'], $this->tags($setup));
        $this->assertArrayNotHasKey('lat', $setup['points'][0], 'an entry with only a shape has no marker');
        $this->assertSame([2.35, 48.85], $setup['points'][0]['geometries']->features[0]->geometry->coordinates);
        $this->assertStringContainsString('"properties":{}', (string)json_encode($setup['points'][0]['geometries']), 'an empty object comes back as a list');
    }

    public function testAnUnreadableGeometryLeavesOnlyThatEntryOut(): void
    {
        $setup = $this->mapFor([
            ['tag' => 'GeoValide', 'bf_geolocation' => ['geometries' => self::VALID]],
            ['tag' => 'GeoSansForme', 'bf_geolocation' => ['geometries' => self::NOT_GEOJSON]],
            ['tag' => 'GeoTronquee', 'bf_geolocation' => ['geometries' => self::TRUNCATED]],
        ]);

        $this->assertSame(['GeoValide'], $this->tags($setup));
    }

    public function testAMarkerKeepsItsShapeWhenTheShapeIsBroken(): void
    {
        $setup = $this->mapFor([['tag' => 'GeoPoint', 'bf_geolocation' => ['latitude' => '45.75', 'longitude' => '4.85', 'geometries' => self::TRUNCATED]]]);

        $this->assertSame(45.75, $setup['points'][0]['lat']);
        $this->assertSame(4.85, $setup['points'][0]['lng']);
        $this->assertArrayNotHasKey('geometries', $setup['points'][0]);
    }

    public function testAnEntryWithoutAPlaceIsNotOnTheMap(): void
    {
        $setup = $this->mapFor([
            ['tag' => 'SansLieu', 'bf_titre' => 'Sans lieu'],
            ['tag' => 'LieuVide', 'bf_geolocation' => ['latitude' => '', 'longitude' => '']],
        ]);

        $this->assertSame([], $setup['points']);
    }

    public function testTheSettingsReachTheBrowserAsValues(): void
    {
        $setup = $this->mapFor([], [
            'smallmarker' => '1',
            'cluster' => 'true',
            'entrydisplay' => 'nonsense',
            'lat' => '46.5',
            'lon' => '2.5',
            'zoom' => '6',
        ]);

        $this->assertTrue($setup['cluster']);
        $this->assertSame([15, 20], $setup['marker']['iconSize']);
        $this->assertSame('bazar-marker', $setup['marker']['className']);
        $this->assertSame('sidebar', $setup['display'], 'an unknown way of opening an entry falls back to the side panel');
        $this->assertSame([46.5, 2.5], $setup['center']);
        $this->assertSame(6, $setup['zoom']);
    }

    public function testTheMapPageCarriesItsPointsAsJson(): void
    {
        $html = (string)$this->getWiki()->services->get(\YesWiki\Render\Service\Performer::class)
            ->run('entrylist', 'action', ['id' => '1', 'template' => 'map']);

        $this->assertMatchesRegularExpression('#<script type="application/json" class="yw-entry-map__data">\{.*"points":\[#s', $html);
        $this->assertStringNotContainsString('<Bazar-Map', $html, 'the map is drawn by a script of its own');
    }
}

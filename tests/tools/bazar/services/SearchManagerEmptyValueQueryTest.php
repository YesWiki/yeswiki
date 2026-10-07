<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A query for an empty value also finds the entries the field was never saved in, and `!=` with an empty value leaves them out. */
class SearchManagerEmptyValueQueryTest extends YesWikiTestCase
{
    private static string $formId;

    /** @var list<string> */
    private static array $tags = [];

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $entryManager = $wiki->services->get(EntryManager::class);
        self::$formId = $wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Empty value query test',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'map***bf_geolocation***Géolocalisation*** *** *** *** *** ***0***marker,line*** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
        $line = json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'properties' => [],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[5.50, 50.40], [5.51, 50.41]]],
        ]]]);
        foreach ([
            ['bf_titre' => 'NoGeo'],
            ['bf_titre' => 'Point', 'bf_geolocation' => ['latitude' => '50.4', 'longitude' => '5.5']],
            ['bf_titre' => 'Line', 'bf_geolocation' => ['geometries' => $line]],
        ] as $data) {
            self::$tags[] = $entryManager->create(self::$formId, ['antispam' => 1] + $data)['id_fiche'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $wiki->services->get(FormManager::class)->delete(self::$formId);
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $titles = array_column($this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::$formId],
            'queries' => $query,
        ]), 'bf_titre');
        sort($titles);

        return $titles;
    }

    public function testAnEmptyStringValueFindsEntriesWithoutTheField()
    {
        $this->assertSame(['NoGeo', 'Point'], $this->titles('bf_geolocation.geometries='));
    }

    public function testAnEmptyNumberValueFindsEntriesWithoutTheField()
    {
        $this->assertSame(['Line', 'NoGeo'], $this->titles('bf_geolocation.latitude='));
    }

    public function testNotEmptyLeavesOutEntriesWithoutTheField()
    {
        $this->assertSame(['Line'], $this->titles('bf_geolocation.geometries!='));
        $this->assertSame(['Point'], $this->titles('bf_geolocation.latitude!='));
    }

    public function testANonEmptyValueStillMatchesExactly()
    {
        $this->assertSame(['Point'], $this->titles('bf_geolocation.latitude=50.4'));
    }
}

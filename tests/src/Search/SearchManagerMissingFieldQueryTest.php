<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Queries on fields missing from an entry. */
class SearchManagerMissingFieldQueryTest extends YesWikiTestCase
{
    private const FORM_ID = '999963';
    private const LIST_ID = 'ListeMissingFieldQueryTest';

    /** @var list<string> */
    private static array $tags = [];

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $services->get(ListManager::class)->create(
            'Missing field query test list',
            [['id' => 'secret', 'label' => 'Secret'], ['id' => 'public', 'label' => 'Public']],
            self::LIST_ID,
        );
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Missing field query test',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1'],
                ['type' => 'map', 'name' => 'bf_geolocation', 'label' => 'Géolocalisation', 'geometries' => 'marker,line'],
                ['type' => 'checkbox', 'linked_object' => self::LIST_ID, 'name' => 'bf_protection', 'label' => 'Protection'],
            ]),
            'condition' => '',
        ]);
        $line = json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'properties' => [],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[5.50, 50.40], [5.51, 50.41]]],
        ]]]);
        $entryManager = $services->get(EntryManager::class);
        foreach ([
            'MissingFieldNoGeo' => ['bf_titre' => 'NoGeo'],
            'MissingFieldPoint' => ['bf_titre' => 'Point', 'bf_geolocation' => ['latitude' => '50.4', 'longitude' => '5.5'], 'bf_protection' => 'secret'],
            'MissingFieldLine' => ['bf_titre' => 'Line', 'bf_geolocation' => ['geometries' => $line], 'bf_protection' => 'public'],
        ] as $tag => $data) {
            $entryManager->create(self::FORM_ID, ['antispam' => 1, 'tag' => $tag] + $data);
            self::$tags[] = $tag;
        }
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $entryManager = $services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        $services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $rows = $this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::FORM_ID],
            'queries' => $query,
        ]);
        $titles = array_values(array_filter(array_map(static fn ($r) => $r['bf_titre'] ?? null, $rows)));
        sort($titles);

        return $titles;
    }

    public function testAnEmptyStringValueFindsEntriesWithoutTheField(): void
    {
        $this->assertSame(['NoGeo', 'Point'], $this->titles('bf_geolocation.geometries='));
    }

    public function testAnEmptyNumberValueFindsEntriesWithoutTheField(): void
    {
        $this->assertSame(['Line', 'NoGeo'], $this->titles('bf_geolocation.latitude='));
    }

    public function testNotEmptyLeavesOutEntriesWithoutTheField(): void
    {
        $this->assertSame(['Line'], $this->titles('bf_geolocation.geometries!='));
        $this->assertSame(['Point'], $this->titles('bf_geolocation.latitude!='));
    }

    public function testANonEmptyValueStillMatchesExactly(): void
    {
        $this->assertSame(['Point'], $this->titles('bf_geolocation.latitude=50.4'));
    }

    public function testExcludingANumberKeepsEntriesWithoutTheField(): void
    {
        $this->assertSame(['Line', 'NoGeo'], $this->titles('bf_geolocation.latitude!=50.4'));
    }

    public function testExcludingACheckboxValueKeepsEntriesWithNothingChecked(): void
    {
        $this->assertSame(['Line', 'NoGeo'], $this->titles('bf_protection!=secret'));
        $this->assertSame(['Point'], $this->titles('bf_protection=secret'));
    }

    public function testAnEmptyCheckboxValueFindsEntriesWithNothingChecked(): void
    {
        $this->assertSame(['NoGeo'], $this->titles('bf_protection='));
    }
}

<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class SearchManagerMetadataQueryTest extends YesWikiTestCase
{
    private function search(string $query): array
    {
        $wiki = $this->getWiki();

        return $wiki->services->get(EntryManager::class)->search([
            'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
        ]);
    }

    private function entries(): array
    {
        $entries = $this->getWiki()->services->get(EntryManager::class)->search([]);
        if (count($entries) < 2) {
            $this->markTestSkipped('needs at least two entries');
        }

        return $entries;
    }

    private function pivotDate(array $entries, string $key): string
    {
        $dates = array_values(array_filter(array_column($entries, $key)));
        sort($dates);

        return substr($dates[intdiv(count($dates), 2)], 0, 10);
    }

    public function testAQueryComparesTheUpdateDate()
    {
        $entries = $this->entries();
        $pivot = $this->pivotDate($entries, 'date_maj_fiche');

        $after = array_filter($entries, fn ($e) => ($e['date_maj_fiche'] ?? '') > $pivot);
        $before = array_filter($entries, fn ($e) => ($e['date_maj_fiche'] ?? '') < $pivot);

        $this->assertNotEmpty($after);
        $this->assertCount(count($after), $this->search("date_maj_fiche>$pivot"));
        $this->assertCount(count($before), $this->search("date_maj_fiche<$pivot"));
    }

    public function testAQueryComparesTheCreationDate()
    {
        $entries = $this->entries();
        $pivot = $this->pivotDate($entries, 'date_creation_fiche');

        $after = array_filter($entries, fn ($e) => ($e['date_creation_fiche'] ?? '') >= $pivot);

        $this->assertNotEmpty($after);
        $this->assertCount(count($after), $this->search("date_creation_fiche>=$pivot"));
    }

    public function testAQueryMatchesTheFormId()
    {
        $entries = $this->entries();
        $formId = $entries[array_key_first($entries)]['id_typeannonce'];

        $expected = array_filter($entries, fn ($e) => $e['id_typeannonce'] == $formId);

        $this->assertCount(count($expected), $this->search("id_typeannonce=$formId"));
    }
}

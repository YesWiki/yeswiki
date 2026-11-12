<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Action\EntryListAction;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Backgrounds leaflet-providers 4 dropped, or put behind a key, become OpenStreetMap wherever a page or the wiki names them. */
class RemovedMapBackgroundsBecomeOpenStreetMapTest extends YesWikiTestCase
{
    private const TAG = 'TestRemovedMapBackgroundsMigration';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261112120000_RemovedMapBackgroundsBecomeOpenStreetMap.php';
    }

    public function testARemovedBackgroundBecomesOpenStreetMap(): void
    {
        $this->assertSame(
            'Carte {{bazarcarto id="2" provider="OpenStreetMap.Mapnik" zoom="5"}} fin',
            \RemovedMapBackgroundsBecomeOpenStreetMap::rewrite('Carte {{bazarcarto id="2" provider="Stamen.Watercolor" zoom="5"}} fin')
        );
    }

    public function testAListLosesItsRemovedBackgroundsWithoutRepeats(): void
    {
        $this->assertSame(
            '{{bazarliste template="map" providers="OpenStreetMap.Mapnik,OpenTopoMap"}}',
            \RemovedMapBackgroundsBecomeOpenStreetMap::rewrite('{{bazarliste template="map" providers="CartoDB.Positron, OpenStreetMap.Mapnik, OpenTopoMap, HERE.normalDay"}}')
        );
    }

    public function testWhatStillExistsIsLeftAsWritten(): void
    {
        foreach ([
            '{{bazarcarto provider="Esri.WorldImagery"}}',
            '{{bazarliste providers="OpenTopoMap, Esri.WorldImagery"}}',
            'provider="Stamen.Toner" outside an action call',
            '```' . "\n" . 'no call here' . "\n" . '```',
        ] as $content) {
            $this->assertSame($content, \RemovedMapBackgroundsBecomeOpenStreetMap::rewrite($content));
        }
    }

    public function testTheRemovedBackgroundsAreNoLongerOffered(): void
    {
        $this->assertSame([], array_intersect(EntryListAction::TILE_BACKGROUNDS, \RemovedMapBackgroundsBecomeOpenStreetMap::REMOVED));
        $this->assertContains(\RemovedMapBackgroundsBecomeOpenStreetMap::REPLACEMENT, EntryListAction::TILE_BACKGROUNDS);
    }

    public function testTheMigrationRewritesPagesAndTheWikiSetting(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $settings = $services->get(WikiSettings::class);
        $pages = trim($db->prefixTable('pages'));
        $hadSetting = array_key_exists('baz_provider', $settings->all());
        $previous = $settings->all()['baz_provider'] ?? null;
        $db->query(
            "INSERT INTO {$pages} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::TAG, '2020-01-01 00:00:00', PageBody::encode(['content' => '{{bazarcarto id="1" provider="Stamen.Toner"}}']), '', '', 'Y', 'page', '']
        );
        $settings->store(['baz_provider' => 'CartoDB.DarkMatter']);
        $services->get(RuntimeConfig::class)['baz_provider'] = 'CartoDB.DarkMatter';

        try {
            $migration = new \RemovedMapBackgroundsBecomeOpenStreetMap();
            $migration->setServices($services);
            $migration->setDbService($db);
            $migration->run();

            $body = PageBody::decode((string)$db->scalar("SELECT body FROM {$pages} WHERE tag = ?", '', [self::TAG]));
            $this->assertSame('{{bazarcarto id="1" provider="OpenStreetMap.Mapnik"}}', PageBody::content($body));
            $settings->startNewRequest();
            $this->assertSame('OpenStreetMap.Mapnik', $settings->all()['baz_provider'] ?? null);
        } finally {
            $db->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
            $hadSetting ? $settings->store(['baz_provider' => $previous]) : $settings->store([], ['baz_provider']);
            $services->get(RuntimeConfig::class)['baz_provider'] = $previous ?? 'OpenStreetMap.Mapnik';
        }
    }
}

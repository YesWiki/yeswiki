<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The darkred every map marker inherited is forgotten, and a colour somebody chose is kept (end-users 30). */
class MarkerColourDefaultIsTheStylesheetsTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261117120000_MarkerColourDefaultIsTheStylesheets.php';
    }

    private function migrateWith(?string $stored): mixed
    {
        $services = self::getWiki()->services;
        $settings = $services->get(WikiSettings::class);
        $hadSetting = array_key_exists('baz_marker_color', $settings->all());
        $previous = $settings->all()['baz_marker_color'] ?? null;
        $settings->store(['baz_marker_color' => $stored]);

        try {
            $migration = new \MarkerColourDefaultIsTheStylesheets();
            $migration->setServices($services);
            $migration->setDbService($services->get(DbService::class));
            $migration->run();
            $settings->startNewRequest();

            return $settings->all()['baz_marker_color'] ?? null;
        } finally {
            $hadSetting ? $settings->store(['baz_marker_color' => $previous]) : $settings->store([], ['baz_marker_color']);
        }
    }

    public function testTheOldDefaultIsForgotten(): void
    {
        $this->assertNull($this->migrateWith('darkred'));
    }

    public function testAChosenColourIsKept(): void
    {
        $this->assertSame('#2a7a3b', $this->migrateWith('#2a7a3b'));
    }
}

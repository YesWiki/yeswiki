<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Federation\Service\SeenSignatures;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Seen signatures migration to triples. */
class SeenSignaturesMoveToTheTriplesTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261007160000_SeenSignaturesMoveToTheTriples.php';
    }

    public function testTheRowsMoveAndTheTableGoes(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $table = trim($dbService->prefixTable(\SeenSignaturesMoveToTheTriples::TABLE));
        $quoted = $dbService->quoteIdentifier($table);
        $triples = trim($dbService->prefixTable('triples'));
        $hash = hash('sha256', 'test-seen-signatures-move-' . bin2hex(random_bytes(4)));
        $resource = SeenSignatures::RESOURCE_PREFIX . $hash;
        $now = time();

        $dbService->query("CREATE TABLE IF NOT EXISTS {$quoted} (hash CHAR(64) NOT NULL PRIMARY KEY, seen_at BIGINT NOT NULL)");
        $dbService->query("INSERT INTO {$quoted} (hash, seen_at) VALUES (?, ?)", [$hash, $now]);

        try {
            $this->runMigration();
            $this->runMigration();

            $this->assertNotContains($table, $dbService->schema()->getTables());
            $rows = $dbService->loadAll("SELECT value FROM {$triples} WHERE resource = ? AND property = ?", [$resource, SeenSignatures::PROPERTY]);
            $this->assertCount(1, $rows);
            $this->assertStringStartsWith(sprintf('%020d', $now) . '|', (string)$rows[0]['value']);
        } finally {
            $dbService->query("DROP TABLE IF EXISTS {$quoted}");
            $dbService->query("DELETE FROM {$triples} WHERE resource = ?", [$resource]);
        }
    }

    private function runMigration(): void
    {
        $wiki = $this->getWiki();
        $migration = new \SeenSignaturesMoveToTheTriples();
        $migration->setServices($wiki->services);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }
}

<?php

namespace YesWiki\Test\Migrations;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A fresh install seeds the already-run-migrations list so the installer's own seed data
 * isn't immediately re-processed by migrations that predate it. That list is hand-written
 * in the seed SQL, and it has silently gone stale three times now (tickets 05, 27, and
 * again by ticket 09's own audit, which found RemoveLoginTplTemplate missing -- so every
 * fresh install was running a REGEXP scan and UPDATE over every page body for nothing).
 *
 * Nothing checked it, so this does. It is a file-name comparison, no wiki and no database.
 */
class SeededMigrationListTest extends TestCase
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../../src/migrations';

    private const SEED = __DIR__ . '/../../../templates/installation-default-content.sql.twig';

    public function testEveryCoreMigrationIsInTheSeededAlreadyRunList(): void
    {
        $onDisk = $this->migrationsOnDisk();
        $seeded = $this->seededMigrations();

        $this->assertNotEmpty($onDisk, 'no migrations found on disk -- the path is probably wrong');
        $this->assertSame(
            [],
            array_values(array_diff($onDisk, $seeded)),
            'These migrations are missing from the seeded already-run list in '
            . basename(self::SEED) . ', so a fresh install re-runs them against seed data '
            . 'that is already in the target shape. Add a row for each.'
        );
    }

    public function testTheSeededListNamesNoMigrationThatNoLongerExists(): void
    {
        $this->assertSame(
            [],
            array_values(array_diff($this->seededMigrations(), $this->migrationsOnDisk())),
            'The seeded already-run list names migrations that are not in src/migrations '
            . '-- a deleted or renamed migration left its row behind.'
        );
    }

    /** @return list<string> */
    private function migrationsOnDisk(): array
    {
        $files = glob(self::MIGRATIONS_DIR . '/*.php') ?: [];
        $names = array_map(fn (string $path) => basename($path, '.php'), $files);
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function seededMigrations(): array
    {
        $sql = (string)file_get_contents(self::SEED);
        preg_match_all(
            "/\('([^']+)', 'http:\/\/outils-reseaux\.org\/_vocabulary\/type', 'migration'\)/",
            $sql,
            $matches
        );
        $names = $matches[1];
        sort($names);

        return $names;
    }
}

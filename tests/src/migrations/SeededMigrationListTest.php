<?php

namespace YesWiki\Test\Migrations;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A fresh install seeds the already-run-migrations list so the installer's own seed data isn't immediately re-processed by migrations that predate it.
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

    /**
     * @return list<string>
     */
    private function migrationsOnDisk(): array
    {
        $files = glob(self::MIGRATIONS_DIR . '/*.php') ?: [];
        $names = array_map(fn (string $path) => basename($path, '.php'), $files);
        sort($names);

        return $names;
    }

    /** The seeded list says every migration has already run, so nothing ever rewrites the seed. */
    public function testTheSeedCarriesNoPreMigrationGeolocation(): void
    {
        $stale = [];
        foreach ($this->seededBodies() as $tag => $body) {
            foreach (['bf_latitude', 'bf_longitude', 'geolocation'] as $key) {
                if (array_key_exists($key, $body)) {
                    $stale[] = "{$tag}: entry key '{$key}'";
                }
            }

            foreach ($body['template'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'map') {
                    continue;
                }
                $name = $field['name'] ?? '';
                if ($name !== 'bf_geolocation') {
                    $stale[] = "{$tag}: map field named '{$name}'";
                }
            }
        }

        $this->assertSame([], $stale, 'the seed is in the shape a migration converts away from, and no migration will ever run on it');
    }

    /**
     * Every seeded body, keyed by tag.
     *
     * @return array<string, array<string, mixed>>
     */
    private function seededBodies(): array
    {
        preg_match_all(
            "/^\('([^']+)',\s+\[\[ now \]\], '(.*?)', '\{\{WikiName\}\}'/m",
            (string)file_get_contents(self::SEED),
            $matches,
            PREG_SET_ORDER
        );
        $this->assertNotEmpty($matches, 'no seeded rows matched -- the seed format changed and this test is now blind');

        $bodies = [];
        foreach ($matches as [, $tag, $raw]) {
            $decoded = json_decode(str_replace("''", "'", $raw), true);
            $this->assertIsArray($decoded, "the seeded body for {$tag} is not valid JSON");
            $bodies[$tag] = $decoded;
        }

        return $bodies;
    }

    /**
     * @return list<string>
     */
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

<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\ExtensionFolders;

/**
 * Whether this code's migrations and its database's agree (replicas 06): behind when one shipped has not run, ahead when `migrate` recorded a newer core one; deleted ones are ignored.
 */
class SchemaStep
{
    public const IN_STEP = 'in_step';

    public const BEHIND = 'behind';

    public const AHEAD = 'ahead';

    public const RESOURCE = 'yeswiki_schema';

    public const LATEST_CORE = 'latest_core_migration';

    private const DEMO = '0000000000000_DemoMigration';

    /** @var array<string, list<string>> */
    private static array $shipped = [];

    public function __construct(private readonly DbService $db, private readonly LocalFiles $files = new LocalFiles())
    {
    }

    /**
     * The folders holding migrations for that configuration, the core's first; what the readiness probe reads before the wiki boots.
     *
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    public static function foldersFor(array $config): array
    {
        $extensions = ExtensionFolders::active(ExtensionFolders::visible(YESWIKI_PROGRAM_DIR, YESWIKI_INSTANCE_DIR), $config['active_extensions'] ?? [], $config['forbidden_extensions'] ?? []);

        return [YESWIKI_PROGRAM_DIR . '/src/', ...array_values($extensions), YESWIKI_INSTANCE_DIR . '/custom/'];
    }

    /**
     * The migration names in a folder holding a `migrations/` one, sorted; kept for the process, since the code of a replica does not change under it.
     *
     * @return list<string>
     */
    public function shippedIn(string $folder): array
    {
        if (!isset(self::$shipped[$folder])) {
            $names = [];
            foreach ($this->files->matching($folder . 'migrations/*.php') as $file) {
                $name = basename($file, '.php');
                if ($name !== self::DEMO && preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1) {
                    $names[] = $name;
                }
            }
            sort($names);
            self::$shipped[$folder] = $names;
        }

        return self::$shipped[$folder];
    }

    /** @param list<string> $folders the core's first, as foldersFor() gives them */
    public function state(array $folders): string
    {
        $ran = $this->ran();
        foreach ($folders as $folder) {
            if (array_diff($this->shippedIn($folder), $ran) !== []) {
                return self::BEHIND;
            }
        }
        $core = $this->shippedIn($folders[0] ?? YESWIKI_PROGRAM_DIR . '/src/');
        $recorded = $this->latestCore();
        if ($recorded !== '' && $core !== [] && strcmp($recorded, (string)end($core)) > 0) {
            return self::AHEAD;
        }

        return self::IN_STEP;
    }

    /** Records the newest core migration the database has run, after a `migrate`. */
    public function record(string $coreFolder): void
    {
        $done = array_values(array_intersect($this->shippedIn($coreFolder), $this->ran()));
        if ($done === []) {
            return;
        }
        $latest = (string)end($done);
        if (strcmp($latest, $this->latestCore()) <= 0) {
            return;
        }
        $table = $this->db->prefixTable('triples');
        $this->db->transactional(function () use ($table, $latest): void {
            $this->db->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [self::RESOURCE, self::LATEST_CORE]);
            $this->db->query("INSERT INTO {$table} (resource, property, value) VALUES (?, ?, ?)", [self::RESOURCE, self::LATEST_CORE, $latest]);
        }, 3);
    }

    /** @return list<string> */
    private function ran(): array
    {
        $rows = $this->db->loadAll('SELECT resource FROM' . $this->db->prefixTable('triples') . 'WHERE property = ? AND value = ?', [TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID]);

        return array_map(static fn (array $row): string => (string)$row['resource'], $rows);
    }

    private function latestCore(): string
    {
        return (string)$this->db->scalar('SELECT value FROM' . $this->db->prefixTable('triples') . 'WHERE resource = ? AND property = ?', '', [self::RESOURCE, self::LATEST_CORE]);
    }
}

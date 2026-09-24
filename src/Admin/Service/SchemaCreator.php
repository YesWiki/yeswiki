<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Search\Service\SearchIndexSchema;

/** The empty tables of one wiki on any supported engine, exactly as a fresh installation creates them. */
class SchemaCreator
{
    /** Creates the content tables, the search index and the Journal under `$prefix` on `$db`. */
    public static function create(\PDO $db, string $prefix): void
    {
        self::createContentTables($db, $prefix);
        self::createSearchIndex($db, $prefix);
        self::createJournal($db, $prefix);
    }

    /** The tables and indexes `installation-create-tables.sql.twig` describes for this engine, without the seed rows the installer adds. */
    public static function createContentTables(\PDO $db, string $prefix): void
    {
        $driver = self::driver($db);
        $sql = InstallationService::renderSqlTemplate(
            YESWIKI_PROGRAM_DIR . '/templates/installation-create-tables.sql.twig',
            ['driver' => $driver, 'bodyType' => SqlDialectFactory::forDriver($driver)->jsonColumnType()]
        );
        $sql = str_replace('{{prefix}}', $prefix, $sql);

        $previousSqlMode = null;
        if ($driver === 'mysql') {
            $modeStatement = $db->query('SELECT @@SESSION.sql_mode');
            $previousSqlMode = $modeStatement === false ? '' : (string)$modeStatement->fetchColumn();
            $db->exec("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_BACKSLASH_ESCAPES')");
        }
        try {
            foreach (InstallationService::splitSqlStatements($sql) as $statement) {
                if (preg_match('/^\s*CREATE\b/i', $statement) === 1) {
                    $db->exec($statement);
                }
            }
        } finally {
            if ($previousSqlMode !== null) {
                $db->exec('SET SESSION sql_mode = ' . $db->quote($previousSqlMode));
            }
        }
    }

    /** The search index, its queue and its keyword table (ADR-0015). */
    public static function createSearchIndex(\PDO $db, string $prefix): void
    {
        $dialect = SqlDialectFactory::forDriver(self::driver($db));
        foreach ($dialect->searchIndexDdl($prefix . SearchIndexSchema::TABLE, $prefix . SearchIndexSchema::QUEUE_TABLE, $prefix . SearchIndexSchema::KEYWORDS_TABLE) as $statement) {
            $db->exec($statement);
        }
    }

    /** The Journal table (ADR-0025). */
    public static function createJournal(\PDO $db, string $prefix): void
    {
        foreach (SqlDialectFactory::forDriver(self::driver($db))->journalDdl($prefix . JournalSchema::TABLE) as $statement) {
            $db->exec($statement);
        }
    }

    private static function driver(\PDO $db): string
    {
        return (string)$db->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}

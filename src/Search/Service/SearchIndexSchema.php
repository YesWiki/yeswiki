<?php

namespace YesWiki\Search\Service;

use YesWiki\Kernel\Service\DbService;

/**
 * The search index's tables: what they are called, and creating or removing them
 * (ticket 18 / ADR-0015).
 *
 * Separated from the indexer so that the migration, the reindex command and the tests all
 * ask the same object rather than each spelling the table name out. The DDL itself lives in
 * the dialect, because the full-text index is the one part whose shape differs per driver.
 */
class SearchIndexSchema
{
    /** Unprefixed. */
    public const TABLE = 'search_index';
    public const QUEUE_TABLE = 'search_queue';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /**
     * Trimmed, unlike DbService::prefixTable(), which returns the name wrapped in spaces so
     * that legacy call sites can concatenate it straight into SQL (`'UPDATE' . prefixTable(...)
     * . "SET ..."`). That padding is invisible in an unquoted `FROM x` and catastrophic
     * inside a quoted identifier: `"​ prefix_search_index ​"` names a *different* table, and
     * SQLite will happily create it.
     */
    public function table(): string
    {
        return trim($this->dbService->prefixTable(self::TABLE));
    }

    public function queueTable(): string
    {
        return trim($this->dbService->prefixTable(self::QUEUE_TABLE));
    }

    public function create(): void
    {
        foreach ($this->dbService->dialect()->searchIndexDdl($this->table(), $this->queueTable()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function drop(): void
    {
        foreach ($this->dbService->dialect()->searchIndexDropDdl($this->table(), $this->queueTable()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    /** Drop and recreate: the reindex command's `--rebuild`, and the cheapest way to reset. */
    public function recreate(): void
    {
        $this->drop();
        $this->create();
    }

    /**
     * Whether the index tables are there at all.
     *
     * The surface asks this before searching, because "the index has not been built yet" and
     * "nothing matched" have to read differently to a visitor -- an upgraded wiki serves the
     * first for as long as the drain takes.
     */
    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->getTables(), true);
    }
}

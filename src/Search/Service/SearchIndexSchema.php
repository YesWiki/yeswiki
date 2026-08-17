<?php

namespace YesWiki\Search\Service;

use YesWiki\Kernel\Service\DbService;

/**
 * The search index's tables: what they are called, and creating or removing them (ticket 18 / ADR-0015).
 */
class SearchIndexSchema
{
    /** Unprefixed. */
    public const TABLE = 'search_index';
    public const QUEUE_TABLE = 'search_queue';
    public const KEYWORDS_TABLE = 'search_keywords';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /**
     * Trimmed, unlike DbService::prefixTable(), which returns the name wrapped in spaces so that legacy call sites can concatenate it straight into SQL (`'UPDATE' .
     */
    public function table(): string
    {
        return trim($this->dbService->prefixTable(self::TABLE));
    }

    public function queueTable(): string
    {
        return trim($this->dbService->prefixTable(self::QUEUE_TABLE));
    }

    /** One row per (Content, keyword): the inverted index behind `tags=` (ticket 35). */
    public function keywordsTable(): string
    {
        return trim($this->dbService->prefixTable(self::KEYWORDS_TABLE));
    }

    public function create(): void
    {
        foreach ($this->dbService->dialect()->searchIndexDdl($this->table(), $this->queueTable(), $this->keywordsTable()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function drop(): void
    {
        foreach ($this->dbService->dialect()->searchIndexDropDdl($this->table(), $this->queueTable(), $this->keywordsTable()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    /** Drop and recreate: the reindex command's `--rebuild`, and the cheapest way to reset. */
    public function recreate(): void
    {
        $this->drop();
        $this->create();
    }

    /** Whether the index tables are there at all. */
    public function exists(): bool
    {
        $tables = $this->dbService->schema()->getTables();

        return in_array($this->table(), $tables, true)
            && in_array($this->queueTable(), $tables, true)
            && in_array($this->keywordsTable(), $tables, true);
    }
}

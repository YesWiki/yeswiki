<?php

namespace YesWiki\Kernel\Service;

/** The Journal's table: what it is called, and creating or removing it (ticket 51 / ADR-0025). */
class JournalSchema
{
    /** Unprefixed. */
    public const TABLE = 'journal';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /** Trimmed, unlike DbService::prefixTable(), which pads the name for concatenation. */
    public function table(): string
    {
        return trim($this->dbService->prefixTable(self::TABLE));
    }

    public function create(): void
    {
        foreach ($this->dbService->dialect()->journalDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function drop(): void
    {
        foreach ($this->dbService->dialect()->journalDropDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->schema()->getTables(), true);
    }
}

<?php

namespace YesWiki\Kernel\Service;

/** The Journal's table: what it is called, and creating or removing it (ticket 51 / ADR-0025). */
class JournalSchema
{
    /** Unprefixed. */
    public const TABLE = 'journal';

    /** The queue channel's index, without the table prefix that PostgreSQL and SQLite put in front of every index name. */
    private const QUEUE_INDEX = 'idx_channel_target_at';

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

    /** Adds the `(channel, target, at)` index to a Journal created before the queue channel existed; a Journal created since has it already. */
    public function ensureQueueIndex(): void
    {
        $table = $this->table();
        $name = $this->dbService->getDriver() === 'mysql' ? self::QUEUE_INDEX : $table . '_' . self::QUEUE_INDEX;

        if ($this->dbService->schema()->indexExists(self::TABLE, $name)) {
            return;
        }

        foreach ($this->dbService->dialect()->journalQueueIndexDdl($table) as $statement) {
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

<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Service\DbService;

/**
 * The queue's two tables in the wiki's own database: what they are called, and creating or removing them. `queue` holds two
 * queues told apart by `queue_name`: `default` and `failed` (QueueTransport); `queue_history` holds what the queue did.
 */
class QueueSchema
{
    public const TABLE = 'queue';

    public const HISTORY_TABLE = 'queue_history';

    public const TABLES = [self::TABLE, self::HISTORY_TABLE];

    public const DEDUP_KEY_LENGTH = 191;

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

    public function historyTable(): string
    {
        return trim($this->dbService->prefixTable(self::HISTORY_TABLE));
    }

    public function create(): void
    {
        foreach ($this->dbService->dialect()->queueDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
        foreach ($this->dbService->dialect()->queueHistoryDdl($this->historyTable()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    /** No trigger nor sequence of their own to remove: the same statement on every engine. */
    public function drop(): void
    {
        foreach ([$this->table(), $this->historyTable()] as $table) {
            $this->dbService->query('DROP TABLE IF EXISTS ' . $this->dbService->quoteIdentifier($table));
        }
    }

    /** Both tables must be there. */
    public function exists(): bool
    {
        $tables = $this->dbService->schema()->getTables();

        return in_array($this->table(), $tables, true) && in_array($this->historyTable(), $tables, true);
    }
}

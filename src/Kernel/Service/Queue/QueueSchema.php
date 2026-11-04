<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Service\DbService;

/**
 * The queue's table in the wiki's own database: what it is called, and creating or removing it. It holds two
 * queues told apart by `queue_name`: `default` and `failed` (QueueTransport).
 */
class QueueSchema
{
    public const TABLE = 'queue';

    public const TABLES = [self::TABLE];

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

    public function create(): void
    {
        foreach ($this->dbService->dialect()->queueDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    /** One table and no trigger nor sequence of its own to remove: the same statement on every engine. */
    public function drop(): void
    {
        $this->dbService->query('DROP TABLE IF EXISTS ' . $this->dbService->quoteIdentifier($this->table()));
    }

    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->schema()->getTables(), true);
    }
}

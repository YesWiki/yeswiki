<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Service\DbService;

/**
 * The queue's table: what it is called, and creating or removing it (messenger / scheduler).
 *
 * It lives in the wiki's own database, like the Journal, so that a wiki on shared hosting needs
 * nothing beyond what it already has: no Redis, no AMQP, no second connection to configure. What
 * the consumer did, its runs and its passes, is not here but in the Journal's `queue` channel
 * (QueueRecorder).
 *
 * One table holds three queues, told apart by `queue_name`: `default`, the messages waiting to be
 * handled, `failed`, those an admin has to look at (QueueTransport), and SCHEDULE_QUEUE, the
 * scheduled tasks and the Scheduler's checkpoint, one row each (QueueTaskRows). Every query on it
 * says which queue it means: a task's row is never a message.
 */
class QueueSchema
{
    /** Unprefixed. */
    public const TABLE = 'queue';

    /**
     * The queue no consumer reads: a row per scheduled task, keyed by its `task_id`, and the checkpoint's (QueueCheckpoint::ROW).
     *
     * Two underscores, like the checkpoint's own id: no task id starts with them, and no queue of messages is named so.
     */
    public const SCHEDULE_QUEUE = '__schedule';

    /**
     * Unprefixed. The table holds the running instance's state, not the wiki's content: what this
     * instance still has to do and where its schedule stands. A backup leaves it out and a restore
     * leaves it alone (DumpRewriter::contentTables()); a restored copy taking the backup's rows
     * would hold the backup's own message as running, replay what was pending then, and send the
     * scheduled tasks back to an older checkpoint.
     */
    public const TABLES = [self::TABLE];

    /** How many characters `dedup_key` holds in every dialect: utf8mb4's widest indexable VARCHAR. */
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

    public function drop(): void
    {
        foreach ($this->dbService->dialect()->queueDropDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->schema()->getTables(), true);
    }
}

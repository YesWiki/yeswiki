<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * The scheduled tasks' state and the Scheduler's checkpoint: rows of the queue's table, in the queue no consumer reads (messenger / scheduler).
 *
 * Each row is found by its `task_id`, unique, which every message leaves NULL. Its fields are
 * JSON in `body`: for a task `label`, `every_seconds`, `last_dispatched_at`, `next_due_at`,
 * `last_finished_at`, `last_status`, `last_error` and `consecutive_failures` (QueueRecorder);
 * for the checkpoint, the row QueueCheckpoint::ROW, `next_due_at` and `state`. A field that is
 * null is left out. The other columns only make the row a valid one of the table: its type is
 * its task id, its headers `{}`, and it was created and made available when it was first written.
 *
 * Every query here is on QueueSchema::SCHEDULE_QUEUE, and every query elsewhere on a queue of
 * messages: the transport never takes, counts nor prunes a task's row.
 *
 * Times in the fields are written by the callers, in UTC, `Y-m-d H:i:s`; the checkpoint's state
 * keeps its own, to the microsecond.
 */
class QueueTaskRows
{
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** Replays of a transaction the engine undid to break a deadlock (see DbService::transactional()). */
    private const CONFLICT_ATTEMPTS = 3;

    private ClockInterface $clock;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * That row's fields, or null when it has none.
     *
     * @return array<string, mixed>|null
     */
    public function read(string $taskId): ?array
    {
        $row = $this->db->loadSingle(
            "SELECT body FROM {$this->schema->table()} WHERE queue_name = ? AND task_id = ?",
            [QueueSchema::SCHEDULE_QUEUE, $taskId]
        );

        return $row === null ? null : self::decode($row['body']);
    }

    /**
     * The tasks' fields by task id, in its order; the checkpoint is no task.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $tasks = [];
        foreach ($this->db->loadAll(
            "SELECT task_id, body FROM {$this->schema->table()} WHERE queue_name = ? AND task_id <> ? ORDER BY task_id",
            [QueueSchema::SCHEDULE_QUEUE, QueueCheckpoint::ROW]
        ) as $row) {
            $tasks[(string)$row['task_id']] = self::decode($row['body']);
        }

        return $tasks;
    }

    /**
     * The ids of the tasks that have a row, in order; the checkpoint is no task.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(
            static fn (array $row): string => (string)$row['task_id'],
            $this->db->loadAll(
                "SELECT task_id FROM {$this->schema->table()} WHERE queue_name = ? AND task_id <> ? ORDER BY task_id",
                [QueueSchema::SCHEDULE_QUEUE, QueueCheckpoint::ROW]
            )
        );
    }

    /**
     * Writes that row's fields as $change makes them from the ones it has, the row being created first if it is not there.
     *
     * Atomic: two processes changing the same row one after the other each see what the first
     * wrote. The row is made to exist by an insert that leaves one already there as it is, then
     * read under a row lock (FOR UPDATE on MySQL and PostgreSQL; SQLite's transaction is BEGIN
     * IMMEDIATE, one writer at a time) and written back in the same transaction.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $change given `[]` for a new row
     */
    public function update(string $taskId, callable $change): void
    {
        $table = $this->schema->table();
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format(self::TIME_FORMAT);

        $this->db->query(
            $this->db->dialect()->upsert(
                $table,
                array_fill_keys(['queue_name', 'task_id', 'message_type', 'body', 'headers', 'created_at', 'available_at', 'attempts'], '?'),
                ['task_id'],
                ['task_id' => ':old.task_id']
            ),
            [QueueSchema::SCHEDULE_QUEUE, $taskId, $taskId, '{}', '{}', $now, $now, 0]
        );

        $this->db->transactional(function () use ($table, $taskId, $change): void {
            $body = $this->db->scalar(
                "SELECT body FROM {$table} WHERE queue_name = ? AND task_id = ?" . $this->db->dialect()->lockRowsClause(),
                null,
                [QueueSchema::SCHEDULE_QUEUE, $taskId]
            );

            $this->db->query(
                "UPDATE {$table} SET body = ? WHERE queue_name = ? AND task_id = ?",
                [self::encode($change(self::decode($body))), QueueSchema::SCHEDULE_QUEUE, $taskId]
            );
        }, self::CONFLICT_ATTEMPTS);
    }

    /**
     * Forgets the tasks that are no longer declared; the checkpoint always stays.
     *
     * @param list<string> $declared the ids of the tasks the schedule has now
     */
    public function forgetExcept(array $declared): void
    {
        $keep = [QueueCheckpoint::ROW, ...$declared];
        $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE queue_name = ? AND task_id NOT IN (" . SqlParameters::placeholders(count($keep)) . ')',
            [QueueSchema::SCHEDULE_QUEUE, ...$keep]
        );
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $body): array
    {
        $fields = is_string($body) ? json_decode($body, true) : null;

        return is_array($fields) ? $fields : [];
    }

    /** @param array<string, mixed> $fields */
    private static function encode(array $fields): string
    {
        // An object even when empty: `{}`, as a new row starts.
        return json_encode(
            (object)array_filter($fields, static fn (mixed $value): bool => $value !== null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }
}

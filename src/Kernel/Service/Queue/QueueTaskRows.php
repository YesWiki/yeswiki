<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * The scheduled tasks' state: rows of the queue's table in QueueSchema::SCHEDULE_QUEUE, found by their unique
 * `task_id`, their fields JSON in `body` (QueueRecorder); the transport never takes, counts nor prunes them.
 */
class QueueTaskRows
{
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const CONFLICT_ATTEMPTS = 3;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
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
     * The tasks' fields by task id, in its order.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $tasks = [];
        foreach ($this->db->loadAll(
            "SELECT task_id, body FROM {$this->schema->table()} WHERE queue_name = ? ORDER BY task_id",
            [QueueSchema::SCHEDULE_QUEUE]
        ) as $row) {
            $tasks[(string)$row['task_id']] = self::decode($row['body']);
        }

        return $tasks;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(
            static fn (array $row): string => (string)$row['task_id'],
            $this->db->loadAll(
                "SELECT task_id FROM {$this->schema->table()} WHERE queue_name = ? ORDER BY task_id",
                [QueueSchema::SCHEDULE_QUEUE]
            )
        );
    }

    /**
     * Atomically writes that row's fields as $change makes them from the ones it has (`[]` for a new row).
     *
     * @param callable(array<string, mixed>): array<string, mixed> $change
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
     * Forgets the tasks that are no longer declared.
     *
     * @param list<string> $declared
     */
    public function forgetExcept(array $declared): void
    {
        if ($declared === []) {
            $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = ?", [QueueSchema::SCHEDULE_QUEUE]);

            return;
        }
        $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE queue_name = ? AND task_id NOT IN (" . SqlParameters::placeholders(count($declared)) . ')',
            [QueueSchema::SCHEDULE_QUEUE, ...$declared]
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
        return json_encode(
            (object)array_filter($fields, static fn (mixed $value): bool => $value !== null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }
}

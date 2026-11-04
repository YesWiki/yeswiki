<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\TaskHistory;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * What the Scheduler knows of each task, read from the last Journal row of each of its `queue.task.*` actions (QueueRecorder writes them).
 * One query, over the (channel, action, target) index; times come out in UTC.
 */
class ScheduleHistory
{
    public function __construct(
        private readonly DbService $db,
        private readonly JournalSchema $journal,
    ) {
    }

    /**
     * Every task the Journal has a row of, by task id.
     *
     * @return array<string, TaskHistory>
     */
    public function all(): array
    {
        return $this->read(null);
    }

    /** That task's history; null when the Journal has no row of it. */
    public function of(string $taskId): ?TaskHistory
    {
        return $this->read($taskId)[$taskId] ?? null;
    }

    /**
     * The tasks' history, of that task alone or of all.
     *
     * @return array<string, TaskHistory>
     */
    private function read(?string $taskId): array
    {
        $table = $this->journal->table();
        $actions = SqlParameters::placeholders(count(QueueJournalEntry::TASK_ACTIONS));
        $kept = "SELECT MAX(id) AS id FROM {$table} WHERE channel = ? AND action IN ({$actions})" . ($taskId === null ? '' : ' AND target = ?') . ' GROUP BY action, target';

        $rows = [];
        foreach ($this->db->loadAll(
            "SELECT action, target, at, context FROM {$table} WHERE id IN (SELECT id FROM ({$kept}) kept)",
            [JournalChannel::Queue->value, ...QueueJournalEntry::TASK_ACTIONS, ...($taskId === null ? [] : [$taskId])]
        ) as $row) {
            $rows[(string)$row['target']][(string)$row['action']] = $row;
        }
        ksort($rows);

        $tasks = [];
        foreach ($rows as $target => $byAction) {
            $tasks[(string)$target] = self::history((string)$target, $byAction);
        }

        return $tasks;
    }

    /**
     * @param array<string, array<string, mixed>> $rows the task's last row of each action
     */
    private static function history(string $taskId, array $rows): TaskHistory
    {
        $declared = $rows[QueueJournalEntry::TASK_DECLARED] ?? null;
        $dispatched = $rows[QueueJournalEntry::TASK_DISPATCHED] ?? null;
        $finished = $rows[QueueJournalEntry::TASK_FINISHED] ?? null;
        $run = $finished === null ? [] : self::context($finished);
        $label = self::context($dispatched ?? [])['label'] ?? self::context($declared ?? [])['label'] ?? null;
        $every = self::context($dispatched ?? [])['every_seconds'] ?? self::context($declared ?? [])['every_seconds'] ?? null;

        return new TaskHistory(
            $taskId,
            $label === null ? null : (string)$label,
            $declared === null ? null : self::time((string)$declared['at']),
            $dispatched === null ? null : self::time((string)$dispatched['at']),
            $finished === null ? null : self::time((string)$finished['at']),
            isset($run['status']) ? (string)$run['status'] : null,
            isset($run['message']) ? (string)$run['message'] : null,
            (int)($run['consecutive_failures'] ?? 0),
            $every === null ? null : (int)$every,
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function context(array $row): array
    {
        $context = json_decode((string)($row['context'] ?? ''), true);

        return is_array($context) ? $context : [];
    }

    private static function time(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}

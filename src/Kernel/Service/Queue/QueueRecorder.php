<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFailureCause;
use YesWiki\Kernel\Queue\QueueHistoryEntry;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\DbService;

/**
 * Writes what the consumer did: rows of the queue's history table, one per pass, per handling attempt and per task event (declared, dispatched, finished).
 * A run is dated by its end, a pass by its start, open while its `target` is empty; `last_at` is when it was last seen.
 * `context` never holds a null; times are the wiki's local time.
 */
class QueueRecorder
{
    public const HANDLED = 'handled';
    public const RETRIED = 'retried';
    public const FAILED = 'failed';
    public const KILLED = 'killed';
    public const IGNORED = 'ignored';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const TARGET_LENGTH = 191;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Opens the row of a pass and answers its id; only a pass given a $holder names its process.
     * $mode is who runs it: `api` or `worker` for that consumer alone, `request` for every pass the consumer did not start itself.
     */
    public function passStarted(QueueConsumerMode $mode, ?QueuePassLimits $limits, bool $beforeResponse, ?string $holder = null): int
    {
        $now = $this->format($this->now());
        $memory = $limits?->memoryCeilingBytes;

        $this->insert($now, $mode->value, QueueHistoryEntry::PASS, '', [
            'handled' => 0,
            'peak_memory' => 0,
            'memory_limit' => $memory === null || $memory === PHP_INT_MAX ? 0 : $memory,
            'pid' => (int)getmypid(),
            'before_response' => $beforeResponse ? 1 : 0,
            'holder' => $holder,
        ]);

        return $this->db->lastInsertId();
    }

    /** A blocking consumer is still alive: what it did so far, without closing its row. */
    public function passHeartbeat(int $passId, int $handled, int $peakMemory): void
    {
        $this->movePass($passId, $handled, $peakMemory, null);
    }

    public function passFinished(int $passId, QueuePassReport $report, int $peakMemory): void
    {
        $this->movePass($passId, $report->handled, $peakMemory, $report->stopReason);
    }

    /** Closes, as killed, a pass still open whose process is gone, leaving `last_at` its last sign of life; answers whether it was still open. */
    public function passKilled(int $passId): bool
    {
        return $this->db->query(
            "UPDATE {$this->schema->historyTable()} SET target = ? WHERE id = ? AND action = ? AND target = ''",
            [QueuePassReport::KILLED, $passId, QueueHistoryEntry::PASS]
        )->rowCount() === 1;
    }

    /**
     * Deletes the rows whose last sign of life is more than $days days old, bar two kinds of row kept however old: the last row of each
     * scheduled task's actions, all the Scheduler remembers of the task; and the last pass of each mode, all that tells when its consumer
     * was last seen (QueueStatistics::lastSeenAt()), so that one down for longer than the retention is not "never seen". Answers how many it deleted.
     */
    public function prune(int $days): int
    {
        $table = $this->schema->historyTable();
        $quote = $this->db->quoteIdentifier(...);
        $actions = SqlParameters::placeholders(count(QueueHistoryEntry::TASK_ACTIONS));
        $lastOfEachTask = "SELECT MAX({$quote('id')}) AS {$quote('id')} FROM {$table} WHERE {$quote('action')} IN ({$actions}) GROUP BY {$quote('action')}, {$quote('target')}";
        $lastPassOfEachMode = "SELECT MAX({$quote('id')}) AS {$quote('id')} FROM {$table} WHERE {$quote('action')} = ? GROUP BY {$quote('actor')}";
        // A derived table, so that MySQL lets the DELETE read its own table.
        $kept = "SELECT {$quote('id')} FROM ({$lastOfEachTask} UNION {$lastPassOfEachMode}) kept";
        $limit = $this->now()->modify("-{$days} days");

        return $this->db->query(
            "DELETE FROM {$table} WHERE {$quote('last_at')} < ? AND {$quote('id')} NOT IN ({$kept})",
            [$this->format($limit), ...QueueHistoryEntry::TASK_ACTIONS, QueueHistoryEntry::PASS]
        )->rowCount();
    }

    /** One handling attempt of a message, ended now; $startedAt is a Unix timestamp from the same clock. */
    public function messageFinished(int $messageId, string $type, ?string $taskId, string $status, QueueConsumerMode $mode, float $startedAt, ?string $error): void
    {
        $finished = $this->now();

        $this->insertRun($status, $mode->value, $type, $this->format($finished), [
            'message_id' => $messageId,
            'task_id' => $taskId,
            'duration_ms' => max(0, (int)round(((float)$finished->format('U.u') - $startedAt) * 1000)),
            'message' => $error,
        ]);

        if ($taskId !== null) {
            $this->taskFinished($taskId, $status, $error);
        }
    }

    /** An admin acknowledged a failed message (the jobs page's "ignore"): the trace it leaves in the history as it leaves `failed`. */
    public function messageIgnored(int $messageId, string $type, ?string $error): void
    {
        $now = $this->format($this->now());

        $this->insertRun(self::IGNORED, '', $type, $now, [
            'message_id' => $messageId,
            'duration_ms' => 0,
            'message' => $error,
        ]);
    }

    /** The Scheduler met that task for the first time: the date its schedule counts from until a run is queued. */
    public function taskDeclared(ScheduledTask $task): void
    {
        $this->insertTask(QueueHistoryEntry::TASK_DECLARED, $task->taskId(), []);
    }

    /** The queue took a run of that task, or a waiting one absorbed it (QueueBus): the date its schedule counts from. */
    public function taskDispatched(ScheduledTask $task): void
    {
        $this->insertTask(QueueHistoryEntry::TASK_DISPATCHED, $task->taskId(), []);
    }

    /** A handling attempt of that task's message ended, and how: what the tasks tab shows of its last run. */
    public function taskFinished(string $taskId, string $status, ?string $error): void
    {
        $this->insertTask(QueueHistoryEntry::TASK_FINISHED, $taskId, [
            'status' => $status,
            'message' => $error === null ? null : QueueFailureCause::fitted($error),
        ]);
    }

    /**
     * A run's row, dated by its end.
     *
     * @param array<string, int|string|null> $context
     */
    private function insertRun(string $status, string $actor, string $type, string $finishedAt, array $context): void
    {
        if (isset($context['message'])) {
            $context['message'] = QueueFailureCause::fitted((string)$context['message']);
        }

        $this->insert($finishedAt, $actor, QueueHistoryEntry::run($status), mb_substr($type, 0, self::TARGET_LENGTH), $context);
    }

    /** @param array<string, int|string|null> $context */
    private function insertTask(string $action, string $taskId, array $context): void
    {
        $this->insert($this->format($this->now()), '', $action, mb_substr($taskId, 0, self::TARGET_LENGTH), $context);
    }

    /** @param array<string, int|string|null> $context */
    private function insert(string $at, string $actor, string $action, string $target, array $context): void
    {
        $columns = implode(', ', array_map(
            $this->db->quoteIdentifier(...),
            ['at', 'last_at', 'actor', 'action', 'target', 'context']
        ));

        $this->db->query(
            "INSERT INTO {$this->schema->historyTable()} ({$columns}) VALUES (?, ?, ?, ?, ?, ?)",
            [$at, $at, $actor, $action, $target, self::encode($context)]
        );
    }

    /** Moves an open pass's row to now (`last_at`): what it did so far, and why it stopped when it did. */
    private function movePass(int $passId, int $handled, int $peakMemory, ?string $stopReason): void
    {
        $now = $this->format($this->now());
        $table = $this->schema->historyTable();
        $stored = $this->db->scalar("SELECT context FROM {$table} WHERE id = ?", null, [$passId]);
        $context = is_string($stored) ? json_decode($stored, true) : null;
        $context = array_merge(is_array($context) ? $context : [], ['handled' => $handled, 'peak_memory' => $peakMemory]);

        $this->db->query(
            "UPDATE {$table} SET last_at = ?, context = ?" . ($stopReason === null ? '' : ', target = ?') . ' WHERE id = ?',
            [$now, self::encode($context), ...($stopReason === null ? [] : [$stopReason]), $passId]
        );
    }

    /**
     * The context as the history keeps it: a JSON object, `{}` when it says nothing, its empty fields left out.
     *
     * @param array<string, mixed> $context
     */
    private static function encode(array $context): string
    {
        return (string)json_encode(
            (object)array_filter($context, static fn (mixed $value): bool => $value !== null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function format(\DateTimeImmutable $time): string
    {
        return $time->format(self::TIME_FORMAT);
    }
}

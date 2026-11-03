<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Psr\Log\LogLevel;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFailureCause;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * Writes what the consumer did: Journal `queue` rows, one per pass, per handling attempt and per task event (declared, dispatched, finished), and each task's last run (QueueTaskRows).
 * A run is dated by its end, a pass by its start, open while its `target` is empty; `last_at` is when it was last seen.
 * Written straight into the Journal's table, never through Journal; `context` never holds a null; times are UTC.
 */
class QueueRecorder
{
    public const HANDLED = 'handled';
    public const RETRIED = 'retried';
    public const FAILED = 'failed';
    public const KILLED = 'killed';
    public const IGNORED = 'ignored';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const LABEL_LENGTH = 191;

    private const TARGET_LENGTH = 191;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueTaskRows $tasks,
        private readonly JournalSchema $journal,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Opens the row of a pass and answers its id; only a pass that is not the consumer's own gets an `origin`. */
    public function passStarted(QueueConsumerMode $mode, ?QueueBudget $budget, bool $beforeResponse, QueuePassOrigin $origin = QueuePassOrigin::Consumer, ?QueueConsumerMode $fallbackFor = null): int
    {
        $now = $this->format($this->now());
        $seconds = $budget?->remainingSeconds();
        $memory = $budget?->memoryCeilingBytes;
        $consumer = $origin === QueuePassOrigin::Consumer;

        $this->insert($now, $mode->value, QueueJournalEntry::PASS, '', LogLevel::INFO, [
            'handled' => 0,
            'budget_ms' => $seconds === null || is_infinite($seconds) ? null : (int)round($seconds * 1000),
            'peak_memory' => 0,
            'memory_limit' => $memory === null || $memory === PHP_INT_MAX ? 0 : $memory,
            'pid' => (int)getmypid(),
            'before_response' => $beforeResponse ? 1 : 0,
            'origin' => $consumer ? null : $origin->value,
            'fallback_for' => $fallbackFor?->value,
            'holder' => $consumer ? QueueClaimHolder::thisProcess()->token() : null,
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

    /** An admin acknowledged a failed message (the jobs page's "ignore"): the trace that tells it apart from a deletion. */
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
        $this->insertTask(QueueJournalEntry::TASK_DECLARED, $task->taskId(), LogLevel::INFO, ['label' => mb_substr($task->label(), 0, self::LABEL_LENGTH)]);
    }

    /** The schedule has queued a run of that task, or one was asked for by hand; a null $nextDue keeps the one known. */
    public function taskDispatched(ScheduledTask $task, int $everySeconds, ?\DateTimeImmutable $nextDue): void
    {
        $label = mb_substr($task->label(), 0, self::LABEL_LENGTH);
        $fields = [
            'label' => $label,
            'every_seconds' => $everySeconds,
            'last_dispatched_at' => $this->format($this->now()),
        ];
        if ($nextDue !== null) {
            $fields['next_due_at'] = $this->format($nextDue->setTimezone(new \DateTimeZone('UTC')));
        }

        $this->tasks->update($task->taskId(), static fn (array $state): array => array_merge($state, $fields));
        $this->insertTask(QueueJournalEntry::TASK_DISPATCHED, $task->taskId(), LogLevel::INFO, ['label' => $label]);
    }

    /**
     * The ids of the tasks that have a row, the Scheduler's checkpoint aside.
     *
     * @return list<string>
     */
    public function taskIds(): array
    {
        return $this->tasks->ids();
    }

    /**
     * Forgets the tasks that are no longer declared, keeping the Scheduler's checkpoint.
     *
     * @param list<string> $declared
     */
    public function forgetTasksExcept(array $declared): void
    {
        $this->tasks->forgetExcept($declared);
    }

    /** A handling attempt of that task's message ended: a failed or killed run adds to `consecutive_failures`, a handled one resets it. */
    public function taskFinished(string $taskId, string $status, ?string $error): void
    {
        $before = $this->lastConsecutiveFailures($taskId);
        $fields = [
            'last_finished_at' => $this->format($this->now()),
            'last_status' => $status,
            'last_error' => $error === null ? null : QueueFailureCause::fitted($error),
        ];

        $this->tasks->update($taskId, static function (array $state) use ($fields, $status): array {
            $failures = (int)($state['consecutive_failures'] ?? 0);

            return array_merge($state, $fields, ['consecutive_failures' => match ($status) {
                self::HANDLED => 0,
                self::FAILED, self::KILLED => $failures + 1,
                default => $failures,
            }]);
        });

        $this->insertTask(QueueJournalEntry::TASK_FINISHED, $taskId, self::levelOf($status), [
            'status' => $status,
            'message' => $error === null ? null : QueueFailureCause::fitted($error),
            'consecutive_failures' => match ($status) {
                self::HANDLED => 0,
                self::FAILED, self::KILLED => $before + 1,
                default => $before,
            },
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

        $this->insert($finishedAt, $actor, QueueJournalEntry::run($status), mb_substr($type, 0, self::TARGET_LENGTH), self::levelOf($status), $context);
    }

    /** @param array<string, int|string|null> $context */
    private function insertTask(string $action, string $taskId, string $level, array $context): void
    {
        $this->insert($this->format($this->now()), '', $action, mb_substr($taskId, 0, self::TARGET_LENGTH), $level, $context);
    }

    /** The count its last `queue.task.finished` row left; 0 for a task that has none. */
    private function lastConsecutiveFailures(string $taskId): int
    {
        $stored = $this->db->scalar(
            "SELECT context FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND target = ? ORDER BY id DESC LIMIT 1",
            null,
            [JournalChannel::Queue->value, QueueJournalEntry::TASK_FINISHED, mb_substr($taskId, 0, self::TARGET_LENGTH)]
        );
        $context = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($context) ? (int)($context['consecutive_failures'] ?? 0) : 0;
    }

    /** The Journal level of a run that ended with that status. */
    private static function levelOf(string $status): string
    {
        return match ($status) {
            self::RETRIED => LogLevel::WARNING,
            self::FAILED, self::KILLED => LogLevel::ERROR,
            default => LogLevel::INFO,
        };
    }

    /** @param array<string, int|string|null> $context */
    private function insert(string $at, string $actor, string $action, string $target, string $level, array $context): void
    {
        $columns = implode(', ', array_map(
            $this->db->quoteIdentifier(...),
            ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'fingerprint', 'day', 'context']
        ));

        $this->db->query(
            "INSERT INTO {$this->journal->table()} ({$columns}) VALUES (?, ?, 1, ?, ?, ?, ?, ?, NULL, NULL, ?)",
            [$at, $at, JournalChannel::Queue->value, $level, $actor, $action, $target, self::encode($context)]
        );
    }

    /** Moves an open pass's row to now (`last_at`): what it did so far, and why it stopped when it did. */
    private function movePass(int $passId, int $handled, int $peakMemory, ?string $stopReason): void
    {
        $now = $this->format($this->now());
        $table = $this->journal->table();
        $stored = $this->db->scalar("SELECT context FROM {$table} WHERE id = ? AND channel = ?", null, [$passId, JournalChannel::Queue->value]);
        $context = is_string($stored) ? json_decode($stored, true) : null;
        $context = array_merge(is_array($context) ? $context : [], ['handled' => $handled, 'peak_memory' => $peakMemory]);

        $this->db->query(
            "UPDATE {$table} SET last_at = ?, context = ?" . ($stopReason === null ? '' : ', target = ?') . ' WHERE id = ? AND channel = ?',
            [$now, self::encode($context), ...($stopReason === null ? [] : [$stopReason]), $passId, JournalChannel::Queue->value]
        );
    }

    /**
     * The context as the Journal keeps it, its empty fields left out.
     *
     * @param array<string, mixed> $context
     */
    private static function encode(array $context): string
    {
        return (string)json_encode(
            array_filter($context, static fn (mixed $value): bool => $value !== null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }

    private function format(\DateTimeImmutable $time): string
    {
        return $time->format(self::TIME_FORMAT);
    }
}

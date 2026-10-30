<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * Writes what the consumer did: rows of the Journal's `queue` channel, one per pass and one per handling attempt, and on each scheduled task's row (QueueTaskRows) what it last did.
 *
 * These rows are all the health checks and the jobs page know of the queue (ADR-0026: they
 * re-derive every finding from them), and the runs are also where the consumer learns how long
 * a type of message takes, to decide whether the next one still fits in its budget.
 *
 * A run (`queue.run.<status>`) is dated by its end, its message type in `target`, its mode in
 * `actor`, and the cause it failed with in `message`, where every Journal entry keeps its own; it
 * began `duration_ms` before. A pass (`queue.pass`) is dated by its start; it is open while its
 * `target`, the reason it stopped, is empty. Its `last_at` is moved forward at each heartbeat and
 * at its end, so that `last_at` of an open row reads "last seen alive" and of a closed one "ended".
 * A blocking consumer keeps one open for its whole life: its `actor`, a blocking mode, is what
 * tells its row from a budgeted pass's.
 *
 * The rows are written here, straight into the Journal's table, and never through Journal: they
 * are not diagnostics, nothing of them goes to the server log, and neither the daily ceiling nor
 * the fingerprint applies (`fingerprint` and `day` stay NULL). Their `context` never holds a null:
 * an empty field is left out, since MySQL would read a JSON null back as the string 'null'.
 * The Journal prunes them itself (Journal::prune()).
 *
 * Times are PHP's, from the injected clock, in UTC, like the transport's.
 */
class QueueRecorder
{
    /** Run statuses: the handler returned. */
    public const HANDLED = 'handled';
    /** The handler threw and the message waits for another attempt, under a new id. */
    public const RETRIED = 'retried';
    /** The handler threw for the last time (or the message could not be decoded): it is in `failed`. */
    public const FAILED = 'failed';
    /** Its consumer died while handling it: it is in `failed`, not retried in the same mode. */
    public const KILLED = 'killed';
    /** An admin acknowledged the failure on the jobs page: the message left `failed`, this row is what remains of it. */
    public const IGNORED = 'ignored';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** Characters of a failure's cause kept in `message`, as the transport keeps in `last_error`. */
    private const ERROR_LENGTH = 1000;

    /** Characters of a task's label kept: what the jobs page has room for. */
    private const LABEL_LENGTH = 191;

    /** What the Journal's `target` holds. */
    private const TARGET_LENGTH = 191;

    private ClockInterface $clock;

    private QueueTaskRows $tasks;

    public function __construct(
        private readonly DbService $db,
        QueueSchema $schema,
        private readonly JournalSchema $journal,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $this->tasks = new QueueTaskRows($db, $schema, $this->clock);
    }

    /**
     * Opens the row of a pass and answers its id.
     *
     * The origin is written only when the pass is not the consumer's own, so that the rows written
     * before there was an origin read as the consumer's; a consumer's row names its process
     * (`holder`), as a claim it holds names it too.
     *
     * @param QueueBudget|null       $budget         what the pass may spend: `budget_ms` is its time left (none when it has no deadline), `memory_limit` the memory use it stops at (0 for no bound)
     * @param bool                   $beforeResponse whether a visitor is waiting for this pass to end (mod_php, CGI)
     * @param QueueConsumerMode|null $fallbackFor    the consumer a fallback pass stands in for
     */
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

    /**
     * One handling attempt of a message, ended now.
     *
     * @param string|null $taskId    the scheduled task the message ran for, if it is one
     * @param string      $status    one of the status constants
     * @param float       $startedAt when the attempt began, as a Unix timestamp from the same clock
     */
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

    /**
     * An admin acknowledged a failed message (the jobs page's "ignore"): the trace that tells it apart from a deletion.
     *
     * Not an attempt, so no mode and no duration; the cause it failed with is kept. Nothing is
     * muted by it: the next failure of the same kind is a new message in `failed`, and alerts again.
     */
    public function messageIgnored(int $messageId, string $type, ?string $error): void
    {
        $now = $this->format($this->now());

        $this->insertRun(self::IGNORED, '', $type, $now, [
            'message_id' => $messageId,
            'duration_ms' => 0,
            'message' => $error,
        ]);
    }

    /**
     * The schedule has queued a run of that task, or one was asked for by hand.
     *
     * @param int                     $everySeconds how far apart its runs are, for the jobs page and the "late" check
     * @param \DateTimeImmutable|null $nextDue      when the schedule runs it next; null keeps the time already known (a run asked for by hand does not move the schedule)
     */
    public function taskDispatched(ScheduledTask $task, int $everySeconds, ?\DateTimeImmutable $nextDue): void
    {
        $fields = [
            'label' => mb_substr($task->label(), 0, self::LABEL_LENGTH),
            'every_seconds' => $everySeconds,
            'last_dispatched_at' => $this->format($this->now()),
        ];
        if ($nextDue !== null) {
            $fields['next_due_at'] = $this->format($nextDue->setTimezone(new \DateTimeZone('UTC')));
        }

        $this->tasks->update($task->taskId(), static fn (array $state): array => array_merge($state, $fields));
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
     * @param list<string> $declared the ids of the tasks the schedule has now
     */
    public function forgetTasksExcept(array $declared): void
    {
        $this->tasks->forgetExcept($declared);
    }

    /**
     * A handling attempt of that task's message ended, with one of the status constants.
     *
     * `consecutive_failures` is what the "task keeps failing" check reads: a run that failed for
     * good or was killed adds one, a handled run resets it. An attempt that will be retried is
     * shown as the task's last status but counts for nothing yet: the run is not over.
     */
    public function taskFinished(string $taskId, string $status, ?string $error): void
    {
        $fields = [
            'last_finished_at' => $this->format($this->now()),
            'last_status' => $status,
            'last_error' => $error === null ? null : mb_substr($error, 0, self::ERROR_LENGTH),
        ];

        $this->tasks->update($taskId, static function (array $state) use ($fields, $status): array {
            $failures = (int)($state['consecutive_failures'] ?? 0);

            return array_merge($state, $fields, ['consecutive_failures' => match ($status) {
                self::HANDLED => 0,
                self::FAILED, self::KILLED => $failures + 1,
                default => $failures,
            }]);
        });
    }

    /**
     * A run's row, dated by its end.
     *
     * @param array<string, int|string|null> $context
     */
    private function insertRun(string $status, string $actor, string $type, string $finishedAt, array $context): void
    {
        if (isset($context['message'])) {
            $context['message'] = mb_substr((string)$context['message'], 0, self::ERROR_LENGTH);
        }
        $level = match ($status) {
            self::RETRIED => LogLevel::WARNING,
            self::FAILED, self::KILLED => LogLevel::ERROR,
            default => LogLevel::INFO,
        };

        $this->insert($finishedAt, $actor, QueueJournalEntry::run($status), mb_substr($type, 0, self::TARGET_LENGTH), $level, $context);
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

    /**
     * Moves an open pass's row to now (`last_at`): what it did so far, and why it stopped when it did.
     *
     * Only the pass's own process writes its row, so reading its context and writing it back needs no lock.
     *
     * @param string|null $stopReason null while it runs
     */
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

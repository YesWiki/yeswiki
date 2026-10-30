<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * Reads the queue back: what waits, what failed, what the passes and the scheduled tasks did (messenger / scheduler).
 *
 * Everything the health checks, the jobs page and the consumer's budget need to know, and
 * nothing else writes: the rows come from the transport and from QueueRecorder, the runs and the
 * passes being rows of the Journal's `queue` channel, the scheduled tasks rows of the queue's
 * table that no query on its messages sees (QueueTaskRows). Times come out as DateTimeImmutable
 * in UTC, since that is how they were written.
 */
class QueueStatistics
{
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** What failedRow() reads of a row of `failed`. */
    private const FAILED_COLUMNS = 'id, message_type, attempts, last_error, killed_in, created_at';

    private ClockInterface $clock;

    private QueueTaskRows $tasks;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly JournalSchema $journal,
        private readonly QueueTransport $transport,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $this->tasks = new QueueTaskRows($db, $schema, $this->clock);
    }

    /** Messages waiting to be handled, delayed retries included; not those being handled. */
    public function pendingCount(): int
    {
        return $this->transport->getMessageCount();
    }

    /** Messages of that class waiting to be handled, delayed retries included; not those being handled: what a module counts its own backlog with. */
    public function waitingOfType(string $type): int
    {
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE queue_name = ? AND message_type = ? AND delivered_at IS NULL",
            0,
            [QueueTransport::DEFAULT_QUEUE, $type]
        );
    }

    public function oldestPendingAt(): ?\DateTimeImmutable
    {
        return $this->transport->oldestPendingAt();
    }

    /**
     * The messages waiting in `failed` for an admin, most recent first.
     *
     * @return list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}>
     */
    public function failed(int $limit = 50): array
    {
        $rows = $this->db->loadAll(
            'SELECT ' . self::FAILED_COLUMNS . " FROM {$this->schema->table()} WHERE queue_name = ? ORDER BY id DESC LIMIT " . max(0, $limit),
            [QueueTransport::FAILED_QUEUE]
        );

        return array_map(self::failedRow(...), $rows);
    }

    /**
     * The messages of these types waiting in `failed`, most recent first, but those whose consumer was killed.
     *
     * A killed message is the killed alert's: retried in the mode that killed it, it would only be killed again.
     *
     * @param list<string> $types message classes
     *
     * @return list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}>
     */
    public function failedOfTypes(array $types, int $limit = 50): array
    {
        if ($types === []) {
            return [];
        }
        $rows = $this->db->loadAll(
            'SELECT ' . self::FAILED_COLUMNS . " FROM {$this->schema->table()}"
            . ' WHERE queue_name = ? AND message_type IN (' . SqlParameters::placeholders(count($types)) . ')'
            . ' AND killed_in IS NULL ORDER BY id DESC LIMIT ' . max(0, $limit),
            [QueueTransport::FAILED_QUEUE, ...$types]
        );

        return array_map(self::failedRow(...), $rows);
    }

    /**
     * That message, if it is waiting in `failed`: what an admin's "retry", "ignore" or "delete" acts on, and nothing else.
     *
     * @return array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}|null
     */
    public function failedMessage(int $id): ?array
    {
        $row = $this->db->loadSingle(
            'SELECT ' . self::FAILED_COLUMNS . " FROM {$this->schema->table()} WHERE id = ? AND queue_name = ?",
            [$id, QueueTransport::FAILED_QUEUE]
        );

        return $row === null ? null : self::failedRow($row);
    }

    /** Messages waiting in `failed`, all of them. */
    public function failedCount(): int
    {
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE queue_name = ?",
            0,
            [QueueTransport::FAILED_QUEUE]
        );
    }

    /** Messages a consumer has taken and not finished: being handled, or left behind by a consumer that died. */
    public function inProgressCount(): int
    {
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE queue_name = ? AND delivered_at IS NOT NULL",
            0,
            [QueueTransport::DEFAULT_QUEUE]
        );
    }

    /**
     * How the messages handled in the last $days ended, and how long the successful ones took on average.
     *
     * An attempt that will be retried is not an outcome, and an "ignored" row only acknowledges a
     * failure already counted: neither is here.
     *
     * @return array{handled: int, failed: int, killed: int, avgMs: ?int}
     */
    public function outcomes(int $days = 7): array
    {
        $counts = [];
        $average = null;
        foreach ($this->db->loadAll(
            "SELECT action, COUNT(*) AS finished, AVG({$this->durationMs()}) AS average FROM {$this->journal->table()}"
            . ' WHERE channel = ? AND action IN (?, ?, ?) AND at >= ? GROUP BY action',
            [JournalChannel::Queue->value, ...self::outcomeActions(), $this->since($days * 86400)]
        ) as $row) {
            $status = QueueJournalEntry::statusOf((string)$row['action']);
            $counts[$status] = (int)$row['finished'];
            if ($status === QueueRecorder::HANDLED && $row['average'] !== null) {
                $average = (int)round((float)$row['average']);
            }
        }

        return [
            'handled' => $counts[QueueRecorder::HANDLED] ?? 0,
            'failed' => $counts[QueueRecorder::FAILED] ?? 0,
            'killed' => $counts[QueueRecorder::KILLED] ?? 0,
            'avgMs' => $average,
        ];
    }

    /**
     * How the messages of one type that finished in the last $sinceSeconds ended: the same count as outcomes(), for a module's own check.
     *
     * @return array{handled: int, failed: int, killed: int}
     */
    public function outcomesOfType(string $type, int $sinceSeconds): array
    {
        $counts = [];
        foreach ($this->db->loadAll(
            "SELECT action, COUNT(*) AS finished FROM {$this->journal->table()}"
            . ' WHERE channel = ? AND target = ? AND at >= ? AND action IN (?, ?, ?) GROUP BY action',
            [JournalChannel::Queue->value, $type, $this->since($sinceSeconds), ...self::outcomeActions()]
        ) as $row) {
            $counts[QueueJournalEntry::statusOf((string)$row['action'])] = (int)$row['finished'];
        }

        return [
            'handled' => $counts[QueueRecorder::HANDLED] ?? 0,
            'failed' => $counts[QueueRecorder::FAILED] ?? 0,
            'killed' => $counts[QueueRecorder::KILLED] ?? 0,
        ];
    }

    /**
     * The deliveries stuck "in progress" past the limit of their mode, which no pass has yet moved to `failed`.
     *
     * @return list<array{id: int, type: string, mode: string, deliveredAt: string}>
     */
    public function expiredClaims(): array
    {
        return array_map(
            static fn (array $claim): array => ['id' => $claim['id'], 'type' => $claim['type'], 'mode' => $claim['mode'], 'deliveredAt' => $claim['deliveredAt']],
            $this->transport->expiredClaims()
        );
    }

    /**
     * The last time a consumer was seen at work: the latest start, end or heartbeat of a pass (a pass row's `last_at` is the latest of them), or now while one holds a message whose limit has not passed.
     *
     * The message in progress counts because a blocking consumer does not beat while one handler
     * runs: an hour-long backup would otherwise read as an hour without a consumer. Its delivery
     * time would not do, it is just as old. The cost is that a consumer that died mid-message is
     * seen gone only once that message's limit is over, when `queue-killed` speaks as well.
     *
     * The row a consumer's pass leaves when it finds the wiki hibernated counts: the stall check is
     * silent while the wiki sleeps, and a consumer that kept coming through the sleep is not stalled
     * when it wakes. Not the row it leaves when it finds the lock taken (QueueConsumer::leaveATrace()),
     * which says the consumer came, not that the queue moved: a pass stuck in a handler holds that lock.
     * Null when no pass ever ran on this wiki.
     */
    public function lastActivityAt(): ?\DateTimeImmutable
    {
        if ($this->transport->isHandling()) {
            return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        }

        $last = $this->db->scalar(
            "SELECT MAX(last_at) FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND target <> ?",
            null,
            [JournalChannel::Queue->value, QueueJournalEntry::PASS, QueuePassReport::LOCKED]
        );

        return $last === null ? null : self::time((string)$last);
    }

    /**
     * What waits in `failed`, by message type and by whether the consumer was killed mid-message, all of it rather than the page `failed()` gives.
     *
     * `killedIn` is the mode a message's consumer was killed in by PHP's time limit or the OOM
     * killer, null for a message that failed by itself.
     *
     * @return list<array{type: string, killedIn: ?string, count: int}>
     */
    public function failedGroups(): array
    {
        $rows = $this->db->loadAll(
            "SELECT message_type, killed_in, COUNT(*) AS waiting FROM {$this->schema->table()}"
            // Those that failed by themselves first, whichever end of the order the database puts NULL at.
            . " WHERE queue_name = ? GROUP BY message_type, killed_in ORDER BY message_type, COALESCE(killed_in, '')",
            [QueueTransport::FAILED_QUEUE]
        );

        return array_map(static fn (array $row): array => [
            'type' => (string)$row['message_type'],
            'killedIn' => $row['killed_in'] === null ? null : (string)$row['killed_in'],
            'count' => (int)$row['waiting'],
        ], $rows);
    }

    /**
     * How long the passes that a visitor waited for lasted, in seconds, over the last $sinceSeconds.
     *
     * The most recent $limit of them: in `request` mode there can be one per page view, and an
     * average needs no more than a few hundred. Computed here rather than in SQL, where the
     * difference of two dates is not written the same way on the three databases.
     *
     * @return list<float>
     */
    public function visitorWaits(int $sinceSeconds, int $limit = 500): array
    {
        // A closed pass was last seen when it ended.
        $rows = $this->db->loadAll(
            "SELECT at, last_at FROM {$this->journal->table()}"
            . " WHERE channel = ? AND action = ? AND target <> '' AND at >= ?"
            . " AND {$this->db->dialect()->castToInteger($this->field('before_response'))} = 1"
            . ' ORDER BY id DESC LIMIT ' . max(0, $limit),
            [JournalChannel::Queue->value, QueueJournalEntry::PASS, $this->since($sinceSeconds)]
        );

        return array_map(
            static fn (array $row): float => max(0.0, (float)self::time((string)$row['last_at'])->format('U.u') - (float)self::time((string)$row['at'])->format('U.u')),
            $rows
        );
    }

    /**
     * Per message type: how many wait, how many failed, and how the ones handled in the last $days went.
     *
     * A type is listed when it has messages in the queue or history in the window.
     *
     * @return list<array{type: string, pending: int, failed: int, lastSuccessAt: ?\DateTimeImmutable, avgMs: ?int}>
     */
    public function byType(int $days = 7): array
    {
        $types = [];
        $blank = ['pending' => 0, 'failed' => 0, 'lastSuccessAt' => null, 'avgMs' => null];

        foreach ($this->db->loadAll(
            "SELECT message_type, queue_name, COUNT(*) AS waiting FROM {$this->schema->table()}"
            . ' WHERE queue_name IN (' . SqlParameters::placeholders(count(QueueTransport::QUEUES)) . ') AND delivered_at IS NULL GROUP BY message_type, queue_name',
            QueueTransport::QUEUES
        ) as $row) {
            $type = (string)$row['message_type'];
            $types[$type] ??= ['type' => $type] + $blank;
            $types[$type][$row['queue_name'] === QueueTransport::FAILED_QUEUE ? 'failed' : 'pending'] += (int)$row['waiting'];
        }

        foreach ($this->db->loadAll(
            "SELECT target, MAX(CASE WHEN action = ? THEN at END) AS last_success, AVG(CASE WHEN action = ? THEN {$this->durationMs()} END) AS average"
            . " FROM {$this->journal->table()} WHERE channel = ? AND action LIKE ? AND at >= ? GROUP BY target",
            [
                QueueJournalEntry::run(QueueRecorder::HANDLED),
                QueueJournalEntry::run(QueueRecorder::HANDLED),
                JournalChannel::Queue->value,
                QueueJournalEntry::RUN_PREFIX . '%',
                $this->since($days * 86400),
            ]
        ) as $row) {
            $type = (string)$row['target'];
            $types[$type] ??= ['type' => $type] + $blank;
            $types[$type]['lastSuccessAt'] = $row['last_success'] === null ? null : self::time((string)$row['last_success']);
            $types[$type]['avgMs'] = $row['average'] === null ? null : (int)round((float)$row['average']);
        }

        ksort($types);

        return array_values($types);
    }

    /** How long a message of that type takes when it succeeds, in milliseconds; null before the first success. */
    public function averageDurationMs(string $type): ?int
    {
        $average = $this->db->scalar(
            "SELECT AVG({$this->durationMs()}) FROM {$this->journal->table()} WHERE channel = ? AND target = ? AND action = ?",
            null,
            [JournalChannel::Queue->value, $type, QueueJournalEntry::run(QueueRecorder::HANDLED)]
        );

        return $average === null ? null : (int)round((float)$average);
    }

    /**
     * The most recent pass, open or not.
     *
     * @return array<string, mixed>|null shaped as recentPasses() rows
     */
    public function lastPass(): ?array
    {
        return $this->recentPasses(1)[0] ?? null;
    }

    /**
     * The most recent passes, newest first.
     *
     * A pass whose `stopReason` is empty is still running, and its `finishedAt` is when it was last
     * seen: its last heartbeat, or its start until the first one. `blocking` is a long-lived
     * consumer's single pass, which starts when its process does.
     *
     * @return list<array{id: int, mode: string, blocking: bool, startedAt: \DateTimeImmutable, finishedAt: \DateTimeImmutable, handled: int, stopReason: string, budgetMs: ?int, peakMemory: int, memoryLimit: int, pid: int, beforeResponse: bool, origin: string, fallbackFor: ?string}>
     */
    public function recentPasses(int $limit): array
    {
        $rows = $this->db->loadAll(
            "SELECT id, actor, target, at, last_at, context FROM {$this->journal->table()} WHERE channel = ? AND action = ? ORDER BY id DESC LIMIT " . max(0, $limit),
            [JournalChannel::Queue->value, QueueJournalEntry::PASS]
        );

        return array_map(static function (array $row): array {
            $context = self::context($row);

            return [
                'id' => (int)$row['id'],
                'mode' => (string)$row['actor'],
                'blocking' => QueueConsumerMode::tryFrom((string)$row['actor'])?->isBudgeted() === false,
                'startedAt' => self::time((string)$row['at']),
                'finishedAt' => self::time((string)$row['last_at']),
                'handled' => (int)($context['handled'] ?? 0),
                'stopReason' => (string)$row['target'],
                'budgetMs' => isset($context['budget_ms']) ? (int)$context['budget_ms'] : null,
                'peakMemory' => (int)($context['peak_memory'] ?? 0),
                'memoryLimit' => (int)($context['memory_limit'] ?? 0),
                'pid' => (int)($context['pid'] ?? 0),
                'beforeResponse' => (bool)(int)($context['before_response'] ?? 0),
                'origin' => (string)($context['origin'] ?? QueuePassOrigin::Consumer->value),
                'fallbackFor' => isset($context['fallback_for']) ? (string)$context['fallback_for'] : null,
            ];
        }, $rows);
    }

    /**
     * When the consumer of that mode was last seen at work; null when it never was.
     *
     * Only its own passes count (QueuePassOrigin): those of a pinned message, of the jobs page's
     * button or of the fallback run under the same mode without proving anything about it. A
     * blocking consumer inside one long message does not beat, so a claim of that mode held by the
     * process of one of its open passes, and not dead, makes it seen now.
     */
    public function lastSeenAt(QueueConsumerMode $mode): ?\DateTimeImmutable
    {
        if ($this->holdsAClaim($mode)) {
            return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        }

        $last = $this->db->scalar(
            "SELECT MAX(last_at) FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND actor = ? AND " . $this->ownPass(),
            null,
            [JournalChannel::Queue->value, QueueJournalEntry::PASS, $mode->value]
        );

        return $last === null ? null : self::time((string)$last);
    }

    /**
     * When the end of requests began consuming for the consumer of that mode, as it still does: its first fallback pass since that consumer was last seen; null when none came after.
     *
     * Only a pass that started once the consumer had been gone for $patienceSeconds counts: no
     * fallback starts sooner (QueueFallback). One that started sooner ran in the minute a request
     * keeps the last answer for, after the consumer came back, and would start the fallback again.
     *
     * @param int $patienceSeconds how long the consumer must have been unseen before the end of requests stands in for it
     */
    public function fallbackSince(QueueConsumerMode $mode, int $patienceSeconds = 0): ?\DateTimeImmutable
    {
        $seen = $this->lastSeenAt($mode);
        $first = $this->db->scalar(
            "SELECT MIN(at) FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND {$this->field('fallback_for')} = ?" . ($seen === null ? '' : ' AND at > ?'),
            null,
            [
                JournalChannel::Queue->value,
                QueueJournalEntry::PASS,
                $mode->value,
                ...($seen === null ? [] : [$seen->modify("+{$patienceSeconds} seconds")->format(self::TIME_FORMAT)]),
            ]
        );

        return $first === null ? null : self::time((string)$first);
    }

    /** Whether a live claim of that mode is held by the process of one of that mode's open consumer passes. */
    private function holdsAClaim(QueueConsumerMode $mode): bool
    {
        $claims = array_filter($this->transport->liveHolders(), static fn (array $claim): bool => $claim['mode'] === $mode->value);
        if ($claims === []) {
            return false;
        }

        $open = $this->db->loadAll(
            "SELECT context FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND actor = ? AND target = ? AND " . $this->ownPass(),
            [JournalChannel::Queue->value, QueueJournalEntry::PASS, $mode->value, '']
        );
        foreach ($open as $row) {
            $pass = QueueClaimHolder::ofToken((string)(self::context($row)['holder'] ?? ''));
            foreach ($claims as $claim) {
                $holder = QueueClaimHolder::ofToken($claim['holder']);
                if ($pass !== null && $holder !== null && $holder->isSameProcessAs($pass)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * How many blocking consumers started in the last $sinceSeconds: more than a few means one keeps dying.
     *
     * A consumer that stopped because the configuration changed asked for the restart that
     * followed it, and does not count: an admin saving settings several times is not a loop.
     */
    public function consumerStarts(int $sinceSeconds): int
    {
        // A blocking consumer's pass starts when its process does (QueueRecorder::passStarted()).
        $blocking = array_values(array_map(
            static fn (QueueConsumerMode $mode): string => $mode->value,
            array_filter(QueueConsumerMode::cases(), static fn (QueueConsumerMode $mode): bool => !$mode->isBudgeted())
        ));

        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->journal->table()} WHERE channel = ? AND action = ? AND at >= ? AND target <> ?"
            . ' AND actor IN (' . SqlParameters::placeholders(count($blocking)) . ')',
            0,
            [JournalChannel::Queue->value, QueueJournalEntry::PASS, $this->since($sinceSeconds), QueuePassReport::CONFIG_CHANGED, ...$blocking]
        );
    }

    /**
     * The handling attempts, newest first, all of them or those with that status; `error` is the cause a run's row keeps as its `message`.
     *
     * @return list<array{id: int, messageId: ?int, type: string, taskId: ?string, status: string, mode: string, finishedAt: \DateTimeImmutable, durationMs: int, error: ?string}>
     */
    public function history(int $limit, ?string $status = null): array
    {
        $rows = $this->db->loadAll(
            "SELECT id, action, target, actor, at, context FROM {$this->journal->table()} WHERE channel = ?"
            . ($status === null ? ' AND action LIKE ?' : ' AND action = ?')
            . ' ORDER BY id DESC LIMIT ' . max(0, $limit),
            [JournalChannel::Queue->value, $status === null ? QueueJournalEntry::RUN_PREFIX . '%' : QueueJournalEntry::run($status)]
        );

        return array_map(static function (array $row): array {
            $context = self::context($row);

            return [
                'id' => (int)$row['id'],
                'messageId' => isset($context['message_id']) ? (int)$context['message_id'] : null,
                'type' => (string)$row['target'],
                'taskId' => isset($context['task_id']) ? (string)$context['task_id'] : null,
                'status' => (string)QueueJournalEntry::statusOf((string)$row['action']),
                'mode' => (string)$row['actor'],
                'finishedAt' => self::time((string)$row['at']),
                'durationMs' => (int)($context['duration_ms'] ?? 0),
                'error' => isset($context['message']) ? (string)$context['message'] : null,
            ];
        }, $rows);
    }

    /**
     * The scheduled tasks, by id; a task whose row has no label is called by its id.
     *
     * @return list<array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}>
     */
    public function tasks(): array
    {
        $time = static fn (mixed $value): ?\DateTimeImmutable => $value === null ? null : self::time((string)$value);
        $tasks = [];
        foreach ($this->tasks->all() as $taskId => $task) {
            $tasks[] = [
                'taskId' => (string)$taskId,
                'label' => (string)($task['label'] ?? '') ?: (string)$taskId,
                'everySeconds' => (int)($task['every_seconds'] ?? 0),
                'lastDispatchedAt' => $time($task['last_dispatched_at'] ?? null),
                'nextDueAt' => $time($task['next_due_at'] ?? null),
                'lastFinishedAt' => $time($task['last_finished_at'] ?? null),
                'lastStatus' => isset($task['last_status']) ? (string)$task['last_status'] : null,
                'lastError' => isset($task['last_error']) ? (string)$task['last_error'] : null,
                'consecutiveFailures' => (int)($task['consecutive_failures'] ?? 0),
            ];
        }

        return $tasks;
    }

    /**
     * When the schedule is next due, which is when a pass has something to do even with no message waiting.
     *
     * Null when the schedule never ticked on this wiki: the first tick is due at once, to start it.
     * (A schedule where no trigger will ever fire again would also leave it null; the kernel's own
     * daily prune keeps that from happening.)
     */
    public function scheduleDueAt(): ?\DateTimeImmutable
    {
        $due = $this->tasks->read(QueueCheckpoint::ROW)['next_due_at'] ?? null;

        return $due === null ? null : self::time((string)$due);
    }

    /**
     * Where a message is in its life: `pending`, `running`, `handled`, `failed`, `killed`, or `unknown` when neither the queue nor the history knows it.
     *
     * The queue is asked first: a message requeued from `failed` keeps its id and is pending again,
     * whatever its history says. Gone from the queue, its last history row tells; a `retried`
     * message is still to do, under the new id Messenger gave the retry.
     *
     * A claim whose holder must be dead (expiredClaims()) is `killed` already: a pass moves it to
     * `failed`, but only once one runs, and on a wiki nobody visits that can be a long time, while
     * whoever follows the message asks now.
     */
    public function messageState(int $messageId): string
    {
        $row = $this->db->loadSingle(
            "SELECT queue_name, delivered_at, killed_in FROM {$this->schema->table()} WHERE id = ? AND queue_name IN (" . SqlParameters::placeholders(count(QueueTransport::QUEUES)) . ')',
            [$messageId, ...QueueTransport::QUEUES]
        );
        if ($row !== null) {
            if ($row['queue_name'] === QueueTransport::FAILED_QUEUE) {
                return $row['killed_in'] === null ? 'failed' : 'killed';
            }
            if ($row['delivered_at'] === null) {
                return 'pending';
            }

            return in_array($messageId, array_column($this->transport->expiredClaims(), 'id'), true) ? 'killed' : 'running';
        }

        $action = $this->db->scalar(
            "SELECT action FROM {$this->journal->table()} WHERE channel = ? AND action LIKE ?"
            . " AND {$this->db->dialect()->castToInteger($this->field('message_id'))} = ? ORDER BY id DESC LIMIT 1",
            null,
            [JournalChannel::Queue->value, QueueJournalEntry::RUN_PREFIX . '%', $messageId]
        );
        $status = $action === null ? null : QueueJournalEntry::statusOf((string)$action);

        return match ($status) {
            QueueRecorder::HANDLED => 'handled',
            QueueRecorder::RETRIED => 'pending',
            // An admin acknowledged the failure: the work was still not done.
            QueueRecorder::FAILED, QueueRecorder::IGNORED => 'failed',
            QueueRecorder::KILLED => 'killed',
            default => 'unknown',
        };
    }

    /** @return list<string> the actions of the runs that are an outcome: handled, failed, killed */
    private static function outcomeActions(): array
    {
        return array_map(QueueJournalEntry::run(...), [QueueRecorder::HANDLED, QueueRecorder::FAILED, QueueRecorder::KILLED]);
    }

    /** A field of a queue row's `context`, as text in SQL; NULL when the row has none. */
    private function field(string $key): string
    {
        $dialect = $this->db->dialect();

        return $dialect->jsonExtractText($dialect->jsonAsText('context'), '$.' . $key);
    }

    /**
     * A pass row of the consumer itself: QueueRecorder writes an origin only for the others.
     *
     * Read as a field, not matched as text: MySQL gives JSON back reformatted, and PostgreSQL has no LIKE on JSONB.
     */
    private function ownPass(): string
    {
        return $this->field('origin') . ' IS NULL';
    }

    /** A run's `duration_ms`, as a number SQL can average. */
    private function durationMs(): string
    {
        return $this->db->dialect()->castToNumber($this->field('duration_ms'));
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

    /** The UTC time $seconds ago, formatted as the queue stores it. */
    private function since(int $seconds): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify("-{$seconds} seconds")->format(self::TIME_FORMAT);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}
     */
    private static function failedRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'type' => (string)$row['message_type'],
            'attempts' => (int)$row['attempts'],
            'error' => $row['last_error'] === null ? null : (string)$row['last_error'],
            'killedIn' => $row['killed_in'] === null ? null : (string)$row['killed_in'],
            'createdAt' => self::time((string)$row['created_at']),
        ];
    }

    private static function time(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}

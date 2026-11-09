<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueHistoryEntry;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\DbService;

/**
 * Reads the queue back for the health checks and the jobs page (messenger / scheduler).
 * Rows come from the transport and QueueRecorder's history table; times come out in the wiki's local time.
 */
class QueueStatistics
{
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const FAILED_COLUMNS = 'id, message_type, attempts, last_error, killed_in, created_at';

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly QueueTransport $transport,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Messages waiting to be handled, delayed retries included; not those being handled. */
    public function pendingCount(): int
    {
        return $this->transport->getMessageCount();
    }

    /** Messages of that class waiting, delayed retries included, not those being handled: a module's own backlog. */
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
     * The messages waiting in `failed`, most recent first.
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
     * @param list<string> $types
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
     * That message, if it is waiting in `failed`: what an admin's "retry" or "ignore" acts on.
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
     * How the messages finished in the last $sinceSeconds ended, of every type or of that one, and the successes' average duration.
     *
     * @return array{handled: int, failed: int, killed: int, avgMs: ?int}
     */
    public function outcomes(int $sinceSeconds, ?string $type = null): array
    {
        $counts = [];
        $average = null;
        foreach ($this->db->loadAll(
            "SELECT action, COUNT(*) AS finished, AVG({$this->durationMs()}) AS average FROM {$this->schema->historyTable()}"
            . ' WHERE action IN (?, ?, ?) AND at >= ?' . ($type === null ? '' : ' AND target = ?') . ' GROUP BY action',
            [...self::outcomeActions(), $this->since($sinceSeconds), ...($type === null ? [] : [$type])]
        ) as $row) {
            $status = QueueHistoryEntry::statusOf((string)$row['action']);
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
     * The deliveries stuck "in progress" whose holder is gone (its lock file free, or none for an hour and a minute), which no pass has yet moved to `failed`.
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

    /** Last time a pass of any mode was seen at work: now while one is (atWork()), its latest `last_at` otherwise; null if no pass ran. */
    public function lastActivityAt(): ?\DateTimeImmutable
    {
        return $this->atWork() ? $this->clock->now() : $this->lastPassAt(null);
    }

    /**
     * All that waits in `failed`, by message type and by the mode its consumer was killed in (null: it failed by itself).
     *
     * @return list<array{type: string, killedIn: ?string, count: int}>
     */
    public function failedGroups(): array
    {
        $rows = $this->db->loadAll(
            "SELECT message_type, killed_in, COUNT(*) AS waiting FROM {$this->schema->table()}"
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
     * How long the $limit latest passes a visitor waited for lasted, in seconds, over the last $sinceSeconds; a pass found dead measures no wait.
     *
     * @return list<float>
     */
    public function visitorWaits(int $sinceSeconds, int $limit = 500): array
    {
        $rows = $this->db->loadAll(
            "SELECT at, last_at FROM {$this->schema->historyTable()}"
            . " WHERE action = ? AND target <> '' AND target <> ? AND at >= ?"
            . " AND {$this->db->dialect()->castToInteger($this->field('before_response'))} = 1"
            . ' ORDER BY id DESC LIMIT ' . max(0, $limit),
            [QueueHistoryEntry::PASS, QueuePassReport::KILLED, $this->since($sinceSeconds)]
        );

        return array_map(
            static fn (array $row): float => max(0.0, (float)self::time((string)$row['last_at'])->format('U.u') - (float)self::time((string)$row['at'])->format('U.u')),
            $rows
        );
    }

    /**
     * Per message type: how many wait, how many failed, and how the ones handled in the last $days went.
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
            . " FROM {$this->schema->historyTable()} WHERE action LIKE ? AND at >= ? GROUP BY target",
            [
                QueueHistoryEntry::run(QueueRecorder::HANDLED),
                QueueHistoryEntry::run(QueueRecorder::HANDLED),
                QueueHistoryEntry::RUN_PREFIX . '%',
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

    /**
     * The most recent pass, open or not.
     *
     * @return array<string, mixed>|null
     */
    public function lastPass(): ?array
    {
        return $this->recentPasses(1)[0] ?? null;
    }

    /**
     * The most recent passes, newest first; one with an empty `stopReason` still runs, its `finishedAt` when last seen.
     *
     * @return list<array{id: int, mode: string, blocking: bool, startedAt: \DateTimeImmutable, finishedAt: \DateTimeImmutable, handled: int, stopReason: string, peakMemory: int, memoryLimit: int, pid: int, beforeResponse: bool}>
     */
    public function recentPasses(int $limit): array
    {
        $rows = $this->db->loadAll(
            "SELECT id, actor, target, at, last_at, context FROM {$this->schema->historyTable()} WHERE action = ? ORDER BY id DESC LIMIT " . max(0, $limit),
            [QueueHistoryEntry::PASS]
        );

        return array_map(static function (array $row): array {
            $context = self::context($row);

            return [
                'id' => (int)$row['id'],
                'mode' => (string)$row['actor'],
                'blocking' => QueueConsumerMode::tryFrom((string)$row['actor'])?->endsByItself() === false,
                'startedAt' => self::time((string)$row['at']),
                'finishedAt' => self::time((string)$row['last_at']),
                'handled' => (int)($context['handled'] ?? 0),
                'stopReason' => (string)$row['target'],
                'peakMemory' => (int)($context['peak_memory'] ?? 0),
                'memoryLimit' => (int)($context['memory_limit'] ?? 0),
                'pid' => (int)($context['pid'] ?? 0),
                'beforeResponse' => (bool)(int)($context['before_response'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * The passes still open, of every mode or of that one, with the token of the process that runs them ('' when the row names none) and when it was last seen.
     *
     * @return list<array{id: int, holder: string, lastAt: \DateTimeImmutable}>
     */
    public function openPasses(?QueueConsumerMode $mode = null): array
    {
        $rows = $this->db->loadAll(
            "SELECT id, last_at, context FROM {$this->schema->historyTable()} WHERE action = ? AND target = ''" . ($mode === null ? '' : ' AND actor = ?') . ' ORDER BY id',
            [QueueHistoryEntry::PASS, ...($mode === null ? [] : [$mode->value])]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'holder' => (string)(self::context($row)['holder'] ?? ''),
            'lastAt' => self::time((string)$row['last_at']),
        ], $rows);
    }

    /** Whether a pass, of that mode when one is given, is open and its process holds its lock: at work now, however long since it last beat. */
    public function atWork(?QueueConsumerMode $mode = null): bool
    {
        foreach ($this->openPasses($mode) as $pass) {
            if ($pass['holder'] !== '' && $this->transport->holderState($pass['holder']) === QueueHolderLock::HELD) {
                return true;
            }
        }

        return false;
    }

    /**
     * When that mode's consumer was last seen at work: now while one of its passes is (atWork()), its passes' latest `last_at` otherwise; null when it never was.
     * Only the consumer itself writes passes of `api` or `worker`: whatever else runs a pass writes it as `request`.
     */
    public function lastSeenAt(QueueConsumerMode $mode): ?\DateTimeImmutable
    {
        return $this->atWork($mode) ? $this->clock->now() : $this->lastPassAt($mode);
    }

    /** The latest `last_at` of the passes, of every mode or of that one; null when none ran. */
    private function lastPassAt(?QueueConsumerMode $mode): ?\DateTimeImmutable
    {
        $last = $this->db->scalar(
            "SELECT MAX(last_at) FROM {$this->schema->historyTable()} WHERE action = ?" . ($mode === null ? '' : ' AND actor = ?'),
            null,
            [QueueHistoryEntry::PASS, ...($mode === null ? [] : [$mode->value])]
        );

        return $last === null ? null : self::time((string)$last);
    }

    /** How many blocking consumers started in the last $sinceSeconds, restarts for a configuration change aside. */
    public function consumerStarts(int $sinceSeconds): int
    {
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->historyTable()} WHERE action = ? AND at >= ? AND target <> ? AND actor = ?",
            0,
            [QueueHistoryEntry::PASS, $this->since($sinceSeconds), QueuePassReport::CONFIG_CHANGED, QueueConsumerMode::Worker->value]
        );
    }

    /**
     * The handling attempts, newest first, all or those with that status; `error` is the run row's `message`.
     *
     * @return list<array{id: int, messageId: ?int, type: string, taskId: ?string, status: string, mode: string, finishedAt: \DateTimeImmutable, durationMs: int, error: ?string}>
     */
    public function history(int $limit, ?string $status = null): array
    {
        $rows = $this->db->loadAll(
            "SELECT id, action, target, actor, at, context FROM {$this->schema->historyTable()} WHERE "
            . ($status === null ? 'action LIKE ?' : 'action = ?')
            . ' ORDER BY id DESC LIMIT ' . max(0, $limit),
            [$status === null ? QueueHistoryEntry::RUN_PREFIX . '%' : QueueHistoryEntry::run($status)]
        );

        return array_map(static function (array $row): array {
            $context = self::context($row);

            return [
                'id' => (int)$row['id'],
                'messageId' => isset($context['message_id']) ? (int)$context['message_id'] : null,
                'type' => (string)$row['target'],
                'taskId' => isset($context['task_id']) ? (string)$context['task_id'] : null,
                'status' => (string)QueueHistoryEntry::statusOf((string)$row['action']),
                'mode' => (string)$row['actor'],
                'finishedAt' => self::time((string)$row['at']),
                'durationMs' => (int)($context['duration_ms'] ?? 0),
                'error' => isset($context['message']) ? (string)$context['message'] : null,
            ];
        }, $rows);
    }

    /** Where a message is: `pending`, `running`, `handled`, `failed`, `killed`, or `unknown` to the queue and the history. */
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
            "SELECT action FROM {$this->schema->historyTable()} WHERE action LIKE ?"
            . " AND {$this->db->dialect()->castToInteger($this->field('message_id'))} = ? ORDER BY id DESC LIMIT 1",
            null,
            [QueueHistoryEntry::RUN_PREFIX . '%', $messageId]
        );
        $status = $action === null ? null : QueueHistoryEntry::statusOf((string)$action);

        return match ($status) {
            QueueRecorder::HANDLED => 'handled',
            QueueRecorder::RETRIED => 'pending',
            QueueRecorder::FAILED, QueueRecorder::IGNORED => 'failed',
            QueueRecorder::KILLED => 'killed',
            default => 'unknown',
        };
    }

    /** @return list<string> */
    private static function outcomeActions(): array
    {
        return array_map(QueueHistoryEntry::run(...), [QueueRecorder::HANDLED, QueueRecorder::FAILED, QueueRecorder::KILLED]);
    }

    /** A field of a queue row's `context`, as text in SQL; NULL when the row has none. */
    private function field(string $key): string
    {
        $dialect = $this->db->dialect();

        return $dialect->jsonExtractText($dialect->jsonAsText('context'), '$.' . $key);
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

    /** The local time $seconds ago, formatted as the queue stores it. */
    private function since(int $seconds): string
    {
        return $this->clock->now()->modify("-{$seconds} seconds")->format(self::TIME_FORMAT);
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
        return new \DateTimeImmutable($value);
    }
}

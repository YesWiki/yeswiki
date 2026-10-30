<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\ToleratedFailure;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * What the admin is told when a background job stops, fails or is misconfigured (messenger / scheduler).
 *
 * The point of moving the deferred work onto a queue is that a job that fails on a poorly
 * maintained install does not go unnoticed: every way the queue can stop being useful is a check
 * here, re-derived from the queue's table and its Journal rows whenever it is asked (ADR-0026), and each links to
 * the jobs page that explains and fixes it. The delays are judged against the mode, since "no pass
 * for an hour" is an outage under a worker and the normal state of a quiet wiki that consumes at the
 * end of a request.
 *
 * Every check but the first stays silent while the table is missing: that wiki is not migrated
 * yet, `queue-tables` says so, and thirteen alerts for one cause would not help.
 */
class QueueHealthChecks implements ProvidesHealthChecks
{
    /** Seconds the oldest waiting message may wait before nothing seems to be consuming. */
    private const STALLED_AFTER_BLOCKING = 600;
    private const STALLED_AFTER_SCHEDULED = 3 * 3600;
    private const STALLED_AFTER_VISITS = 24 * 3600;

    /** Seconds after which a queue that still moves is allowed to be called a backlog. */
    private const BACKLOG_AFTER_BLOCKING = 3600;
    private const BACKLOG_AFTER_OTHERS = 6 * 3600;

    private const TASK_FAILURES = 3;
    private const RESTARTS_WINDOW_SECONDS = 600;
    private const RESTARTS_TOLERATED = 5;
    private const MEMORY_RATIO = 0.9;
    private const NO_BUDGET_WINDOW = 5;
    private const NO_BUDGET_TRIPS = 3;
    private const VISITOR_WINDOW_SECONDS = 86400;
    private const VISITOR_WAIT_SECONDS = 1.0;
    private const SPAWN_WINDOW_HOURS = 24;

    /** The jobs page, where every check here sends the admin. */
    public const ROUTE = 'admin/jobs';

    /** The prefix of the offer that asks the jobs page for the failed-messages buttons of one type. */
    public const OFFER_FAILED_OF = 'failed-of:';

    /** Types named in a message before it says "and others". */
    private const TYPES_LISTED = 5;

    private ClockInterface $clock;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly QueueSchema $schema,
        private readonly HibernationService $hibernation,
        private readonly DbService $db,
        private readonly JournalSchema $journal,
        private readonly QueueScheduleTicker $ticker,
        private readonly QueueFallback $fallback,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /** How long no consumer may have been seen at work, in the mode the wiki consumes in, while messages are available, before `queue-stalled` fires. */
    public static function stalledAfterSeconds(QueueConsumerMode $mode): int
    {
        return match ($mode) {
            QueueConsumerMode::Worker, QueueConsumerMode::Binary => self::STALLED_AFTER_BLOCKING,
            QueueConsumerMode::Command, QueueConsumerMode::Api => self::STALLED_AFTER_SCHEDULED,
            QueueConsumerMode::Request, QueueConsumerMode::RequestSpawn => self::STALLED_AFTER_VISITS,
        };
    }

    /**
     * What a module's check passes to HealthCheck::offers() to get, on its alert, the buttons that retry or acknowledge every failed message of one type.
     *
     * For the messages the generic `queue-failed` check leaves out (ToleratedFailure), which the
     * "all" buttons of that check do not reach: the module's own alert is where they are dealt with.
     */
    public static function offeringFailedOf(string $messageClass): string
    {
        return self::OFFER_FAILED_OF . $messageClass;
    }

    public function healthChecks(): array
    {
        // What several checks of this list read is read once for all of them: reading the schema, the failures, the declared tasks are queries.
        $once = static function (\Closure $read): \Closure {
            $done = false;
            $value = null;

            return static function () use ($read, &$done, &$value): mixed {
                if (!$done) {
                    // Set once it is read: a read that throws throws again for the next check, rather than answering null.
                    $value = $read();
                    $done = true;
                }

                return $value;
            };
        };
        $tables = $once(function (): ?string {
            try {
                return $this->schema->exists() ? null : _t('QUEUE_HEALTH_TABLES_MISSING');
            } catch (\Throwable $unreachable) {
                return _t('QUEUE_HEALTH_TABLES_UNREACHABLE', ['error' => $unreachable->getMessage()]);
            }
        });
        $failedGroups = $once($this->statistics->failedGroups(...));
        $declaredTasks = $once($this->declaredTasks(...));
        $stalled = $once($this->stalled(...));
        // What a check does once the table is there.
        $onceMigrated = static fn (callable $run): callable => static fn (): ?string => $tables() === null ? $run() : null;
        // Every check here sends the admin to the jobs page, and says what it watches under `<key>_SAYS`.
        $check = static fn (string $id, string $key): HealthCheck => HealthCheck::named($id)
            ->label(_t($key))
            ->says(_t($key . '_SAYS'))
            ->linkedTo(self::ROUTE);

        return [
            $check('queue-tables', 'QUEUE_HEALTH_TABLES')->runs($tables),
            $check('queue-fallback', 'QUEUE_HEALTH_FALLBACK')->runs($onceMigrated($this->fallback(...))),
            $check('queue-stalled', 'QUEUE_HEALTH_STALLED')->runs($onceMigrated($stalled)),
            $check('queue-failed', 'QUEUE_HEALTH_FAILED')->runs($onceMigrated(fn (): ?string => $this->failed($failedGroups()))),
            $check('queue-killed', 'QUEUE_HEALTH_KILLED')->runs($onceMigrated(fn (): ?string => $this->killed($failedGroups()))),
            $check('queue-task-overdue', 'QUEUE_HEALTH_TASK_OVERDUE')->runs($onceMigrated(fn (): ?string => $this->taskOverdue($declaredTasks()))),
            $check('queue-task-failing', 'QUEUE_HEALTH_TASK_FAILING')->runs($onceMigrated(fn (): ?string => $this->taskFailing($declaredTasks()))),
            $check('queue-backlog', 'QUEUE_HEALTH_BACKLOG')->degraded()->runs($onceMigrated(fn (): ?string => $this->backlog($stalled() !== null))),
            $check('queue-consumer-restarts', 'QUEUE_HEALTH_RESTARTS')->runs($onceMigrated($this->consumerRestarts(...))),
            $check('queue-consumer-memory', 'QUEUE_HEALTH_MEMORY')->degraded()->runs($onceMigrated($this->consumerMemory(...))),
            // Reads the settings, not the table, and is the one to say a mode is wrong even before the migration ran.
            $check('queue-config', 'QUEUE_HEALTH_CONFIG')->runs($this->config(...)),
            $check('queue-spawn-unavailable', 'QUEUE_HEALTH_SPAWN')->degraded()->runs($this->spawnUnavailable(...)),
            $check('queue-no-budget', 'QUEUE_HEALTH_NO_BUDGET')->degraded()->runs($onceMigrated($this->noBudget(...))),
            $check('queue-visitor-cost', 'QUEUE_HEALTH_VISITOR_COST')->degraded()->runs($onceMigrated($this->visitorCost(...))),
        ];
    }

    /**
     * The end of requests consumes for the consumer the admin chose, which is no longer seen at work (QueueFallback).
     *
     * Broken, not degraded: the queue moves, but on a request's budget and only when someone visits,
     * and what the admin chose is down. It goes away by itself once that consumer is seen again.
     */
    private function fallback(): ?string
    {
        $status = $this->fallback->status();
        if ($status === null) {
            return null;
        }
        $now = $this->clock->now()->getTimestamp();
        $respondsEarly = QueueRequestTrigger::respondsEarly();
        $values = [
            'mode' => $status->mode->value,
            'since' => $this->duration($now - $status->since->getTimestamp()),
            'budget' => (int)round((new QueueBudgetFactory())->forMode(QueueConsumerMode::Request, $respondsEarly)->remainingSeconds()),
        ];
        $sentence = $status->lastSeen === null
            ? _t('QUEUE_HEALTH_FALLBACK_NEVER', $values)
            : _t('QUEUE_HEALTH_FALLBACK_FAILED', $values + ['idle' => $this->duration($now - $status->lastSeen->getTimestamp())]);

        return $respondsEarly ? $sentence : $sentence . _t('QUEUE_HEALTH_FALLBACK_VISITORS_WAIT');
    }

    /**
     * Messages are available and no consumer has been seen at work for longer than the mode allows (spec 11.2).
     *
     * Judged on the consumer, not on the age of the oldest message: a worker that is alive and
     * draining a long queue is a backlog, not a stall. A wiki on which no pass ever ran has no
     * activity to measure, so the oldest available message stands in for it.
     *
     * While the end of requests consumes for a consumer that is gone, `queue-fallback` says so, and
     * the queue is judged as `request` would be: its passes are what moves it, and with no visit
     * for a day it is stalled all the same.
     */
    private function stalled(): ?string
    {
        $age = $this->availableAge();
        if ($age === null) {
            return null;
        }
        $lastActivity = $this->statistics->lastActivityAt();
        $idle = $lastActivity === null ? $age : $this->clock->now()->getTimestamp() - $lastActivity->getTimestamp();
        $judgedAs = $this->fallback->status() === null ? $this->settings->mode() : QueueConsumerMode::Request;
        if ($idle <= self::stalledAfterSeconds($judgedAs) || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        return $lastActivity === null
            ? _t('QUEUE_HEALTH_STALLED_NEVER', ['count' => $this->statistics->pendingCount(), 'age' => $this->duration($age)])
            : _t('QUEUE_HEALTH_STALLED_FAILED', ['count' => $this->statistics->pendingCount(), 'age' => $this->duration($age), 'idle' => $this->duration($idle)]);
    }

    /**
     * Messages that have waited too long, whether or not a consumer is working through them; left to `queue-stalled` when nothing is.
     *
     * @param bool $stalled whether `queue-stalled` fires
     */
    private function backlog(bool $stalled): ?string
    {
        $age = $this->availableAge();
        $threshold = $this->settings->mode()->isBudgeted() ? self::BACKLOG_AFTER_OTHERS : self::BACKLOG_AFTER_BLOCKING;
        if ($age === null || $age <= $threshold || $stalled || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        return _t('QUEUE_HEALTH_BACKLOG_FAILED', ['count' => $this->statistics->pendingCount(), 'age' => $this->duration($age)]);
    }

    /** Seconds since the longest-waiting available message became available; null when none is (a delayed retry is not waiting yet). */
    private function availableAge(): ?int
    {
        $oldest = $this->statistics->oldestPendingAt();
        $age = $oldest === null ? null : $this->clock->now()->getTimestamp() - $oldest->getTimestamp();

        return $age !== null && $age >= 0 ? $age : null;
    }

    /** @param list<array{type: string, killedIn: ?string, count: int}> $failedGroups as QueueStatistics::failedGroups() gives them */
    private function failed(array $failedGroups): ?string
    {
        $counts = [];
        foreach ($failedGroups as $group) {
            if (self::countsAsFailed($group['type'], $group['killedIn'])) {
                $counts[$group['type']] = ($counts[$group['type']] ?? 0) + $group['count'];
            }
        }
        if ($counts === []) {
            return null;
        }

        return _t('QUEUE_HEALTH_FAILED_FAILED', ['count' => array_sum($counts), 'types' => $this->typesIn($counts)]);
    }

    /**
     * Whether a failed message is one `queue-failed` counts, and so one the jobs page's "retry all" and "ignore all" act on.
     *
     * Not one whose consumer was killed: the killed alert speaks of it, and retrying it in the
     * mode that killed it only kills it again. Nor one whose class declares its failures routine,
     * such as a delivery to a fediverse server that is gone for good; a class that no longer
     * exists cannot.
     *
     * @param string|null $killedIn the mode its consumer was killed in, as its row's `killed_in` says
     */
    public static function countsAsFailed(string $type, ?string $killedIn): bool
    {
        return $killedIn === null && !(class_exists($type) && is_a($type, ToleratedFailure::class, true));
    }

    /**
     * Messages whose consumer was cut off, and those whose consumer must have been without anyone having noticed yet.
     *
     * The advice depends on what the wiki runs in now: a job killed under `request` is told to move
     * to a mode without a time limit only while the wiki is still in `request`.
     *
     * @param list<array{type: string, killedIn: ?string, count: int}> $failedGroups as QueueStatistics::failedGroups() gives them
     */
    private function killed(array $failedGroups): ?string
    {
        $current = $this->settings->mode();
        /** @var array<string, array<string, int>> $byMode types and counts, by the mode that was killed */
        $byMode = [];
        foreach ($failedGroups as $group) {
            if ($group['killedIn'] !== null) {
                $byMode[$group['killedIn']][$group['type']] = $group['count'];
            }
        }

        $sentences = [];
        foreach ($byMode as $mode => $counts) {
            $killedIn = QueueConsumerMode::tryFrom($mode);
            $key = match (true) {
                $killedIn?->isBudgeted() === false => 'QUEUE_HEALTH_KILLED_BLOCKING',
                $killedIn === $current && $current->isBudgeted() => 'QUEUE_HEALTH_KILLED_FAILED',
                default => 'QUEUE_HEALTH_KILLED_PREVIOUS',
            };
            $sentences[] = _t($key, ['types' => $this->typesIn($counts), 'mode' => $mode, 'current' => $current->value]);
        }

        /** @var array<string, array<string, int>> $stuck */
        $stuck = [];
        foreach ($this->statistics->expiredClaims() as $claim) {
            $stuck[$claim['mode']][$claim['type']] = ($stuck[$claim['mode']][$claim['type']] ?? 0) + 1;
        }
        foreach ($stuck as $mode => $counts) {
            $sentences[] = _t('QUEUE_HEALTH_KILLED_STUCK', ['types' => $this->typesIn($counts), 'mode' => $mode]);
        }

        return $sentences === [] ? null : implode(' ', $sentences);
    }

    /** @param list<array<string, mixed>> $declaredTasks as declaredTasks() gives them */
    private function taskOverdue(array $declaredTasks): ?string
    {
        $mode = $this->settings->mode();
        $now = $this->clock->now()->getTimestamp();
        $late = [];

        foreach ($declaredTasks as $task) {
            if ($task['nextDueAt'] === null) {
                continue;
            }
            // Not before it was due plus a period (a task due every 23 h that runs 2 h late is not lost) nor before the mode had a chance to run it.
            $lateBy = $now - $task['nextDueAt']->getTimestamp();
            if ($lateBy > max($task['everySeconds'], self::stalledAfterSeconds($mode))) {
                $late[] = _t('QUEUE_HEALTH_TASK_OVERDUE_ITEM', [
                    'task' => $task['label'],
                    'every' => $this->duration($task['everySeconds']),
                    'late' => $this->duration($lateBy),
                ]);
            }
        }
        if ($late === [] || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        return implode(' ; ', $late);
    }

    /** @param list<array<string, mixed>> $declaredTasks as declaredTasks() gives them */
    private function taskFailing(array $declaredTasks): ?string
    {
        $failing = [];
        foreach ($declaredTasks as $task) {
            if ($task['consecutiveFailures'] >= self::TASK_FAILURES) {
                $failing[] = _t('QUEUE_HEALTH_TASK_FAILING_ITEM', [
                    'task' => $task['label'],
                    'count' => $task['consecutiveFailures'],
                    'error' => $task['lastError'] ?? '?',
                ]);
            }
        }

        return $failing === [] ? null : implode(' ; ', $failing);
    }

    /**
     * The scheduled tasks the schedule still declares: a row left by a task that is gone (a deleted data source, a disabled extension) would be late or failing for ever, and nobody could clear it.
     *
     * @return list<array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}>
     */
    private function declaredTasks(): array
    {
        $declared = array_flip($this->ticker->declaredTaskIds());

        return array_values(array_filter($this->statistics->tasks(), static fn (array $task): bool => isset($declared[$task['taskId']])));
    }

    private function consumerRestarts(): ?string
    {
        if ($this->settings->mode()->isBudgeted()) {
            return null;
        }
        $starts = $this->statistics->consumerStarts(self::RESTARTS_WINDOW_SECONDS);

        return $starts > self::RESTARTS_TOLERATED
            ? _t('QUEUE_HEALTH_RESTARTS_FAILED', ['count' => $starts, 'minutes' => intdiv(self::RESTARTS_WINDOW_SECONDS, 60)])
            : null;
    }

    private function consumerMemory(): ?string
    {
        if ($this->settings->mode()->isBudgeted()) {
            return null;
        }
        // The most recent blocking consumer's row.
        foreach ($this->statistics->recentPasses(200) as $pass) {
            if (!$pass['blocking']) {
                continue;
            }

            return $pass['memoryLimit'] > 0 && $pass['peakMemory'] > self::MEMORY_RATIO * $pass['memoryLimit']
                ? _t('QUEUE_HEALTH_MEMORY_FAILED', [
                    'used' => $this->megabytes($pass['peakMemory']),
                    'limit' => $this->megabytes($pass['memoryLimit']),
                ])
                : null;
        }

        return null;
    }

    private function config(): ?string
    {
        $problems = [];
        if ($this->settings->isUnknownValue()) {
            $problems[] = _t('QUEUE_HEALTH_CONFIG_UNKNOWN', [
                'value' => $this->settings->rawValue(),
                'origin' => _t('QUEUE_ORIGIN_' . strtoupper($this->settings->origin())),
            ]);
        }

        $mode = $this->settings->mode();
        if ($mode === QueueConsumerMode::Binary && !$this->settings->isServedByBinary()) {
            $problems[] = _t('QUEUE_HEALTH_CONFIG_BINARY');
        }
        if ($mode === QueueConsumerMode::Api && $this->settings->consumeSecret() === '') {
            $problems[] = _t('QUEUE_HEALTH_CONFIG_API');
        }

        return $problems === [] ? null : implode(' ', $problems);
    }

    /**
     * Whether `request-spawn` had to fall back to consuming in the visitor's request, today or yesterday.
     *
     * The Journal dates its rows by the database's own clock, not PHP's, so the window is cut by
     * the database too: the one place where the injected clock is not the one to compare with.
     */
    private function spawnUnavailable(): ?string
    {
        if ($this->settings->mode() !== QueueConsumerMode::RequestSpawn || !$this->journal->exists()) {
            return null;
        }

        $quote = fn (string $column): string => $this->db->quoteIdentifier($column);
        $count = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$quote($this->journal->table())} WHERE {$quote('channel')} = ? AND {$quote('action')} = ? AND {$quote('last_at')} >= {$this->db->dateSubHours(self::SPAWN_WINDOW_HOURS)}",
            0,
            [JournalChannel::Diagnostic->value, 'queue.spawn_unavailable']
        );

        return $count > 0 ? _t('QUEUE_HEALTH_SPAWN_FAILED') : null;
    }

    /**
     * Whether the passes that have run were mostly too late in the request to take a single message.
     *
     * What is counted is the last passes that did start: the ones that tell whether the time PHP
     * gives a request is enough. The rows a consumer leaves when it finds the lock taken, or the
     * wiki hibernated, are not among them.
     */
    private function noBudget(): ?string
    {
        if (!$this->settings->mode()->isBudgeted()) {
            return null;
        }

        $passes = array_slice(array_values(array_filter(
            $this->statistics->recentPasses(200),
            // A pass that found the lock taken, or the wiki hibernated, did nothing, and says nothing of the budget.
            static fn (array $pass): bool => !$pass['blocking'] && !in_array($pass['stopReason'], [QueuePassReport::LOCKED, QueuePassReport::PAUSED], true)
        )), 0, self::NO_BUDGET_WINDOW);
        $starved = count(array_filter($passes, static fn (array $pass): bool => $pass['stopReason'] === QueuePassReport::NO_BUDGET));

        return $starved >= self::NO_BUDGET_TRIPS
            ? _t('QUEUE_HEALTH_NO_BUDGET_FAILED', ['starved' => $starved, 'passes' => count($passes)])
            : null;
    }

    private function visitorCost(): ?string
    {
        if ($this->settings->mode() !== QueueConsumerMode::Request) {
            return null;
        }
        $waits = $this->statistics->visitorWaits(self::VISITOR_WINDOW_SECONDS);
        if ($waits === []) {
            return null;
        }
        $average = array_sum($waits) / count($waits);

        return $average > self::VISITOR_WAIT_SECONDS
            ? _t('QUEUE_HEALTH_VISITOR_COST_FAILED', ['seconds' => number_format($average, 1, '.', ''), 'count' => count($waits)])
            : null;
    }

    /**
     * "Foo (3), Bar (1)": the types as an admin recognises them, the busiest first.
     *
     * @param array<string, int> $counts message class => how many
     */
    private function typesIn(array $counts): string
    {
        arsort($counts);
        $named = [];
        foreach ($counts as $type => $count) {
            $named[] = ($pos = strrpos($type, '\\')) === false ? "$type ($count)" : substr($type, $pos + 1) . " ($count)";
        }
        $more = count($named) - self::TYPES_LISTED;

        return implode(', ', array_slice($named, 0, self::TYPES_LISTED)) . ($more > 0 ? ', ' . _t('QUEUE_HEALTH_AND_OTHERS', ['count' => $more]) : '');
    }

    private function duration(int $seconds): string
    {
        return match (true) {
            $seconds >= 86400 => _t('QUEUE_HEALTH_DAYS', ['count' => intdiv($seconds, 86400)]),
            $seconds >= 3600 => _t('QUEUE_HEALTH_HOURS', ['count' => intdiv($seconds, 3600)]),
            default => _t('QUEUE_HEALTH_MINUTES', ['count' => max(1, intdiv($seconds, 60))]),
        };
    }

    private function megabytes(int $bytes): string
    {
        return _t('QUEUE_HEALTH_MEGABYTES', ['count' => (int)round($bytes / 1048576)]);
    }
}

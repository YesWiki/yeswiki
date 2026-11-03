<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\QueueWording;
use YesWiki\Kernel\Queue\ToleratedFailure;
use YesWiki\Kernel\Service\HibernationService;

/**
 * What the admin is told when a background job stops, fails or is misconfigured (messenger / scheduler).
 * Each check is re-derived from the queue's table and Journal rows (ADR-0026), judged against the mode, linked to the jobs page.
 * Every check but `queue-tables` stays silent while the table is missing.
 */
class QueueHealthChecks implements ProvidesHealthChecks
{
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

    public const ROUTE = 'admin/jobs';

    public const OFFER_FAILED_OF = 'failed-of:';

    private const TYPES_LISTED = 5;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly QueueSchema $schema,
        private readonly HibernationService $hibernation,
        private readonly QueueScheduleTicker $ticker,
        private readonly QueueFallback $fallback,
        private readonly QueueBudgetFactory $budgets,
        private readonly ClockInterface $clock,
    ) {
    }

    /** What a module's check passes to HealthCheck::offers() for buttons that retry or acknowledge every failed message of one type. */
    public static function offeringFailedOf(string $messageClass): string
    {
        return self::OFFER_FAILED_OF . $messageClass;
    }

    public function healthChecks(): array
    {
        $once = static function (\Closure $read): \Closure {
            $done = false;
            $value = null;

            return static function () use ($read, &$done, &$value): mixed {
                if (!$done) {
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
        $tasks = $once($this->statistics->tasks(...));
        $declaredIds = $once(fn (): array => $this->ticker->declaredTaskIds());
        $stalled = $once($this->stalled(...));
        $onceMigrated = static fn (callable $run): callable => static fn (): ?string => $tables() === null ? $run() : null;
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
            $check('queue-task-overdue', 'QUEUE_HEALTH_TASK_OVERDUE')->runs($onceMigrated(fn (): ?string => $this->taskOverdue($tasks(), $declaredIds))),
            $check('queue-task-failing', 'QUEUE_HEALTH_TASK_FAILING')->runs($onceMigrated(fn (): ?string => $this->taskFailing($tasks(), $declaredIds))),
            $check('queue-backlog', 'QUEUE_HEALTH_BACKLOG')->degraded()->runs($onceMigrated(fn (): ?string => $this->backlog($stalled() !== null))),
            $check('queue-consumer-restarts', 'QUEUE_HEALTH_RESTARTS')->runs($onceMigrated($this->consumerRestarts(...))),
            $check('queue-consumer-memory', 'QUEUE_HEALTH_MEMORY')->degraded()->runs($onceMigrated($this->consumerMemory(...))),
            $check('queue-config', 'QUEUE_HEALTH_CONFIG')->runs($this->config(...)),
            $check('queue-no-budget', 'QUEUE_HEALTH_NO_BUDGET')->degraded()->runs($onceMigrated($this->noBudget(...))),
            $check('queue-visitor-cost', 'QUEUE_HEALTH_VISITOR_COST')->degraded()->runs($onceMigrated($this->visitorCost(...))),
        ];
    }

    /** The end of requests consumes for the consumer the admin chose, which is no longer seen at work (QueueFallback). */
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
            'since' => QueueWording::duration($now - $status->since->getTimestamp()),
            'budget' => $this->budgets->secondsPerPass(QueueConsumerMode::Request, $respondsEarly),
        ];
        $sentence = $status->lastSeen === null
            ? _t('QUEUE_HEALTH_FALLBACK_NEVER', $values)
            : _t('QUEUE_HEALTH_FALLBACK_FAILED', $values + ['idle' => QueueWording::duration($now - $status->lastSeen->getTimestamp())]);

        return $respondsEarly ? $sentence : $sentence . _t('QUEUE_HEALTH_FALLBACK_VISITORS_WAIT');
    }

    /** Messages are available and no consumer has been seen at work for longer than the mode allows (spec 11.2). */
    private function stalled(): ?string
    {
        $age = $this->availableAge();
        if ($age === null) {
            return null;
        }
        $lastActivity = $this->statistics->lastActivityAt();
        $idle = $lastActivity === null ? $age : $this->clock->now()->getTimestamp() - $lastActivity->getTimestamp();
        if ($idle <= $this->fallback->consumingMode()->stalledAfterSeconds() || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        return $lastActivity === null
            ? _t('QUEUE_HEALTH_STALLED_NEVER', ['count' => $this->statistics->pendingCount(), 'age' => QueueWording::duration($age)])
            : _t('QUEUE_HEALTH_STALLED_FAILED', ['count' => $this->statistics->pendingCount(), 'age' => QueueWording::duration($age), 'idle' => QueueWording::duration($idle)]);
    }

    /** Messages that have waited too long, whether or not a consumer is working through them; left to `queue-stalled` when nothing is. */
    private function backlog(bool $stalled): ?string
    {
        $age = $this->availableAge();
        $threshold = $this->settings->mode()->isBudgeted() ? self::BACKLOG_AFTER_OTHERS : self::BACKLOG_AFTER_BLOCKING;
        if ($age === null || $age <= $threshold || $stalled || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        return _t('QUEUE_HEALTH_BACKLOG_FAILED', ['count' => $this->statistics->pendingCount(), 'age' => QueueWording::duration($age)]);
    }

    /** Seconds since the longest-waiting available message became available; null when none is (a delayed retry is not waiting yet). */
    private function availableAge(): ?int
    {
        $oldest = $this->statistics->oldestPendingAt();
        $age = $oldest === null ? null : $this->clock->now()->getTimestamp() - $oldest->getTimestamp();

        return $age !== null && $age >= 0 ? $age : null;
    }

    /** @param list<array{type: string, killedIn: ?string, count: int}> $failedGroups */
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

    /** Whether `queue-failed` counts a failed message, and so the jobs page's "retry all" and "ignore all" act on it. */
    public static function countsAsFailed(string $type, ?string $killedIn): bool
    {
        return $killedIn === null && !(class_exists($type) && is_a($type, ToleratedFailure::class, true));
    }

    /**
     * Messages whose consumer was cut off, and those whose consumer must have been without anyone having noticed yet.
     *
     * @param list<array{type: string, killedIn: ?string, count: int}> $failedGroups
     */
    private function killed(array $failedGroups): ?string
    {
        $current = $this->settings->mode();
        /** @var array<string, array<string, int>> $byMode */
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

    /**
     * Whether that task is overdue: a period past due, and past what the mode needs to run it; the tasks tab agrees.
     *
     * @param array{nextDueAt: ?\DateTimeImmutable, everySeconds: int} $task
     */
    public static function isOverdue(array $task, QueueConsumerMode $mode, int $now): bool
    {
        return $task['nextDueAt'] !== null && $now - $task['nextDueAt']->getTimestamp() > max($task['everySeconds'], $mode->stalledAfterSeconds());
    }

    /**
     * The tasks the schedule still declares: a row left by a task that is gone would be late or failing for ever.
     *
     * @template T of array{taskId: string}
     *
     * @param list<T>      $tasks
     * @param list<string> $declaredIds
     *
     * @return list<T>
     */
    public static function onlyDeclared(array $tasks, array $declaredIds): array
    {
        $declared = array_flip($declaredIds);

        return array_values(array_filter($tasks, static fn (array $task): bool => isset($declared[$task['taskId']])));
    }

    /**
     * @param list<array{taskId: string, label: string, everySeconds: int, nextDueAt: ?\DateTimeImmutable}> $tasks
     * @param \Closure(): list<string>                                                                      $declaredIds
     */
    private function taskOverdue(array $tasks, \Closure $declaredIds): ?string
    {
        $mode = $this->settings->mode();
        $now = $this->clock->now()->getTimestamp();
        $late = array_values(array_filter($tasks, static fn (array $task): bool => self::isOverdue($task, $mode, $now)));
        if ($late === [] || ($late = self::onlyDeclared($late, $declaredIds())) === [] || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }

        $items = [];
        foreach ($late as $task) {
            $items[] = _t('QUEUE_HEALTH_TASK_OVERDUE_ITEM', [
                'task' => $task['label'],
                'every' => QueueWording::duration($task['everySeconds']),
                'late' => QueueWording::duration($now - ($task['nextDueAt']?->getTimestamp() ?? $now)),
            ]);
        }

        return implode(' ; ', $items);
    }

    /**
     * @param list<array{taskId: string, label: string, consecutiveFailures: int, lastError: ?string}> $tasks
     * @param \Closure(): list<string>                                                                 $declaredIds
     */
    private function taskFailing(array $tasks, \Closure $declaredIds): ?string
    {
        $failing = array_values(array_filter($tasks, static fn (array $task): bool => $task['consecutiveFailures'] >= self::TASK_FAILURES));
        if ($failing !== []) {
            $failing = self::onlyDeclared($failing, $declaredIds());
        }

        return $failing === [] ? null : implode(' ; ', array_map(static fn (array $task): string => _t('QUEUE_HEALTH_TASK_FAILING_ITEM', [
            'task' => $task['label'],
            'count' => $task['consecutiveFailures'],
            'error' => $task['lastError'] ?? '?',
        ]), $failing));
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
        if ($mode === QueueConsumerMode::Api && $this->settings->consumeSecret() === '') {
            $problems[] = _t('QUEUE_HEALTH_CONFIG_API');
        }

        return $problems === [] ? null : implode(' ', $problems);
    }

    /** Whether the last passes that did start were mostly too late in the request to take a single message. */
    private function noBudget(): ?string
    {
        if (!$this->settings->mode()->isBudgeted()) {
            return null;
        }

        $passes = array_slice(array_values(array_filter(
            $this->statistics->recentPasses(200),
            static fn (array $pass): bool => !$pass['blocking'] && !in_array($pass['stopReason'], [QueuePassReport::LOCKED, QueuePassReport::PAUSED], true)
        )), 0, self::NO_BUDGET_WINDOW);
        $starved = count(array_filter($passes, static fn (array $pass): bool => $pass['stopReason'] === QueuePassReport::NO_BUDGET));

        return $starved >= self::NO_BUDGET_TRIPS
            ? _t('QUEUE_HEALTH_NO_BUDGET_FAILED', ['starved' => $starved, 'passes' => count($passes)])
            : null;
    }

    private function visitorCost(): ?string
    {
        if (!$this->settings->mode()->consumesAtEndOfRequests()) {
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
     * @param array<string, int> $counts
     */
    private function typesIn(array $counts): string
    {
        arsort($counts);
        $named = [];
        foreach ($counts as $type => $count) {
            $named[] = QueueWording::type($type) . " ($count)";
        }
        $more = count($named) - self::TYPES_LISTED;

        return implode(', ', array_slice($named, 0, self::TYPES_LISTED)) . ($more > 0 ? ', ' . _t('QUEUE_HEALTH_AND_OTHERS', ['count' => $more]) : '');
    }

    private function megabytes(int $bytes): string
    {
        return _t('QUEUE_HEALTH_MEGABYTES', ['count' => (int)round($bytes / 1048576)]);
    }
}

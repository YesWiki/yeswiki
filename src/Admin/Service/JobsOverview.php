<?php

namespace YesWiki\Admin\Service;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Health\Finding;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;

/**
 * What the background jobs page shows (messenger / scheduler, spec §11.3).
 *
 * The page exists so that a job that fails on a poorly maintained install does not go unnoticed:
 * its top half -- the mode, the alerts, the tiles -- answers "is something wrong, and what do I do",
 * and it is what every action on the page refreshes. The alerts are the health findings of the
 * checks that send the admin here (`linkedTo('admin/jobs')`), the queue's own and those a module
 * declares, so the page never judges anything a second time. The tabs below are the detail.
 *
 * Everything is shaped for the templates here, already translated and formatted; the raw texts a
 * failure carries (`last_error`, an exception message) are passed as they are and escaped by Twig.
 */
class JobsOverview
{
    public const TABS = ['types', 'failed', 'tasks', 'history', 'consumers'];

    /** The route the alerts of this page are linked to. */
    public const ROUTE = QueueHealthChecks::ROUTE;

    /** Rows a tab lists: the most recent. */
    private const LISTED = 100;

    /** The window the tiles and the "by type" tab count over, as the history keeps it. */
    private const DAYS = 7;

    /** Messages one "retry all" or "ignore all" acts on: what is left takes another click. */
    public const BULK_LIMIT = 500;

    private ClockInterface $clock;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly QueueBudgetFactory $budgets,
        private readonly HealthService $health,
        private readonly QueueScheduleTicker $ticker,
        private readonly LanguageService $languages,
        private readonly QueueFallback $fallback,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * The part of the page a pass changes: the mode, the alerts, the tiles.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $alerts = $this->alerts();
        $ids = array_column($alerts, 'id');

        try {
            $tiles = $this->tiles($ids);
            $lastPass = $this->statistics->lastPass();
            $lastActivity = $this->statistics->lastActivityAt();
            $unreadable = null;
        } catch (\Throwable $unreadable) {
            // The table is missing or the database is gone: `queue-tables` says so among the alerts.
            $tiles = null;
            $lastPass = null;
            $lastActivity = null;
        }

        return [
            'mode' => $this->mode($lastPass, $lastActivity),
            'alerts' => $alerts,
            'tiles' => $tiles,
            'unreadable' => $unreadable?->getMessage(),
        ];
    }

    /**
     * One tab, with the nav that leads to the others.
     *
     * @return array<string, mixed>
     */
    public function tab(string $tab): array
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : self::TABS[0];

        try {
            $failedCount = $this->statistics->failedCount();
            $rows = match ($tab) {
                'types' => $this->types(),
                'failed' => $this->failed(),
                'tasks' => $this->tasks(),
                'history' => $this->history(),
                'consumers' => $this->consumers(),
            };
            $error = null;
        } catch (\Throwable $unreadable) {
            $failedCount = 0;
            $rows = [];
            $error = $unreadable->getMessage();
        }

        return ['name' => $tab, 'tabs' => self::TABS, 'rows' => $rows, 'failedCount' => $failedCount, 'error' => $error, 'listed' => self::LISTED];
    }

    /**
     * The failed messages a "retry all" or "ignore all" from the failures alert acts on: the ones that alert counts (QueueHealthChecks::countsAsFailed()).
     *
     * Chosen before the limit, not after: routine failures of a tolerated type, or killed ones,
     * newer than those would otherwise fill the batch, and the alert's own buttons act on nothing.
     *
     * @return list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}>
     */
    public function failedToActOn(): array
    {
        $types = [];
        foreach ($this->statistics->failedGroups() as $group) {
            if (QueueHealthChecks::countsAsFailed($group['type'], null)) {
                $types[$group['type']] = $group['type'];
            }
        }

        return $this->statistics->failedOfTypes(array_values($types), self::BULK_LIMIT);
    }

    /**
     * Every failed message of one type, killed ones apart, for the buttons a module's alert offers: tolerated types included, since the type is named.
     *
     * The type is named by its short class name, which is what the page shows and what a URL can
     * carry; it stands for every failed class that ends so. At most BULK_LIMIT, the rest takes another click.
     *
     * @return list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}>
     */
    public function failedOfType(string $shortName): array
    {
        $types = [];
        foreach ($this->statistics->failedGroups() as $group) {
            if (self::shortName($group['type']) === $shortName) {
                $types[$group['type']] = $group['type'];
            }
        }

        return $this->statistics->failedOfTypes(array_values($types), self::BULK_LIMIT);
    }

    /** What a message type is called on the page: its `QUEUE_TYPE_<SHORT NAME>` translation, or its short class name. */
    public static function typeLabel(string $type): string
    {
        $short = self::shortName($type);
        $key = 'QUEUE_TYPE_' . strtoupper($short);
        $label = _t($key);

        return $label === $key ? $short : $label;
    }

    private static function shortName(string $type): string
    {
        return ($position = strrpos($type, '\\')) === false ? $type : substr($type, $position + 1);
    }

    public static function stopReasonLabel(string $reason): string
    {
        return _t($reason === '' ? 'QUEUE_STOP_RUNNING' : 'QUEUE_STOP_' . strtoupper(str_replace('-', '_', $reason)));
    }

    /** "il y a 3 j 4 h", "dans 12 min": a time as far as the reader is concerned. */
    private function ago(?\DateTimeImmutable $time): ?string
    {
        return $this->fromNow($time, CarbonInterface::DIFF_RELATIVE_TO_NOW);
    }

    /** "3 j 4 h": how long ago, as a length of time. */
    private function age(?\DateTimeImmutable $time): ?string
    {
        return $this->fromNow($time, CarbonInterface::DIFF_ABSOLUTE);
    }

    private function fromNow(?\DateTimeImmutable $time, int $syntax): ?string
    {
        if ($time === null) {
            return null;
        }

        $moment = Carbon::instance($time);
        $moment->locale($this->languages->preferredLanguage());

        return $moment->diffForHumans(Carbon::instance($this->clock->now()), ['parts' => 2, 'short' => true, 'syntax' => $syntax]);
    }

    /**
     * The findings of the checks that send the admin to this page, Broken first, each with the actions that answer it.
     *
     * @return list<array{id: string, broken: bool, label: string, detail: string, says: string, actions: list<string>}>
     */
    private function alerts(): array
    {
        $alerts = [];
        foreach ($this->health->findings() as $finding) {
            if ($finding->check->route() !== self::ROUTE) {
                continue;
            }
            $alerts[] = [
                'id' => $finding->check->id(),
                'broken' => $finding->isBroken(),
                'label' => $finding->check->labelText(),
                'detail' => $finding->detail,
                'says' => $finding->check->sentence(),
                'actions' => $this->actionsFor($finding),
            ];
        }

        return $alerts;
    }

    /**
     * The buttons an alert gets, by what fixes it: a pass for what waits, the failures tab for what failed, the modes documentation for a choice of mode.
     *
     * @return list<string>
     */
    private function actionsFor(Finding $finding): array
    {
        return match ($finding->check->id()) {
            'queue-stalled', 'queue-backlog', 'queue-no-budget' => ['consume', 'docs'],
            'queue-failed' => ['retry-all', 'view-failed', 'ignore-all'],
            'queue-killed' => ['view-failed', 'consume', 'docs'],
            'queue-task-overdue' => ['consume', 'view-tasks'],
            'queue-task-failing' => ['view-tasks', 'view-history'],
            'queue-config' => $this->settings->mode() === QueueConsumerMode::Api && $this->settings->consumeSecret() === ''
                ? ['secret', 'docs']
                : ['docs'],
            'queue-tables', 'queue-fallback', 'queue-consumer-restarts', 'queue-consumer-memory', 'queue-spawn-unavailable', 'queue-visitor-cost' => ['docs'],
            // A module's own check about its messages (federation deliveries): the failures are where it shows, and it may ask for the buttons that deal with them all.
            default => $this->offeredActions($finding),
        };
    }

    /**
     * The buttons a module's check asked for, around the failures tab: "retry all of this type", "ignore all of this type".
     *
     * @return list<string>
     */
    private function offeredActions(Finding $finding): array
    {
        $types = [];
        foreach ($finding->check->offered() as $offer) {
            if (str_starts_with($offer, QueueHealthChecks::OFFER_FAILED_OF)) {
                $types[] = self::shortName(substr($offer, strlen(QueueHealthChecks::OFFER_FAILED_OF)));
            }
        }
        if ($types === []) {
            return ['view-failed', 'docs'];
        }

        return [
            ...array_map(static fn (string $type): string => 'retry-all:' . $type, $types),
            'view-failed',
            ...array_map(static fn (string $type): string => 'ignore-all:' . $type, $types),
        ];
    }

    /**
     * The mode banner: which mode, who chose it, what a pass may spend, when one last ran and how often one should.
     *
     * @param array<string, mixed>|null $lastPass as QueueStatistics::lastPass() gives it
     *
     * @return array<string, mixed>
     */
    private function mode(?array $lastPass, ?\DateTimeImmutable $lastActivity): array
    {
        $mode = $this->settings->mode();
        $origin = $this->settings->origin();
        $respondsEarly = QueueRequestTrigger::respondsEarly();

        $idle = $lastActivity === null ? null : $this->clock->now()->getTimestamp() - $lastActivity->getTimestamp();
        try {
            $fallback = $this->fallback->status();
        } catch (\Throwable) {
            // The table is missing or the database is gone: `queue-tables` says so among the alerts.
            $fallback = null;
        }

        return [
            'label' => _t('QUEUE_MODE_' . self::keyOf($mode)),
            'origin' => $origin === QueueSettings::ORIGIN_DEFAULT
                ? _t('ADMIN_JOBS_ORIGIN_DEFAULT')
                : _t('ADMIN_JOBS_ORIGIN_SET', ['origin' => _t('QUEUE_ORIGIN_' . strtoupper($origin))]),
            'unknownValue' => $this->settings->isUnknownValue() ? $this->settings->rawValue() : null,
            'budget' => $this->budget($mode, $respondsEarly),
            'limits' => _t('ADMIN_JOBS_LIMITS', [
                'time' => (int)ini_get('max_execution_time') > 0 ? ini_get('max_execution_time') . ' s' : _t('ADMIN_JOBS_UNLIMITED'),
                'memory' => (string)ini_get('memory_limit'),
            ]),
            'cli' => self::passesInTheCli($mode),
            'visitorsWait' => $mode === QueueConsumerMode::Request && !$respondsEarly,
            'pace' => _t('QUEUE_PACE_' . self::keyOf($mode)),
            'lastPass' => $fallback !== null
                ? $this->ago($fallback->lastSeen)
                : ($lastPass === null ? null : $this->ago($lastPass['stopReason'] === '' ? $lastPass['finishedAt'] : $lastPass['startedAt'])),
            'running' => $fallback === null && $lastPass !== null && $lastPass['stopReason'] === '',
            // In a fallback, the last pass is the chosen consumer's, which is late by definition: the requests' passes are the alert's to tell.
            'lastSeenMode' => $fallback === null ? null : _t('QUEUE_MODE_' . self::keyOf($mode)),
            'late' => $fallback !== null || ($idle !== null && $idle > QueueHealthChecks::stalledAfterSeconds($mode)),
            'api' => $mode === QueueConsumerMode::Api,
            'hasSecret' => $this->settings->consumeSecret() !== '',
        ];
    }

    /**
     * What one pass may spend in this mode, as QueueBudgetFactory works it out, in whole seconds; null for the blocking modes, which have no deadline.
     *
     * The page is a web request, so the factory reads this server's PHP limits: right for `request`
     * and `api`, which run under them. `command` and `request-spawn` run in the CLI, where PHP sets
     * no time limit unless told to: they are worked out without one.
     */
    private function budget(QueueConsumerMode $mode, bool $respondsEarly): ?int
    {
        if (!$mode->isBudgeted()) {
            return null;
        }
        $budgets = self::passesInTheCli($mode)
            ? new QueueBudgetFactory(static fn (string $name) => $name === 'max_execution_time' ? '0' : ini_get($name))
            : $this->budgets;

        return (int)round($budgets->forMode($mode, $respondsEarly)->remainingSeconds());
    }

    /** Whether the budgeted passes of this mode run in a CLI process rather than under this server's PHP limits. */
    private static function passesInTheCli(QueueConsumerMode $mode): bool
    {
        return $mode === QueueConsumerMode::Command || $mode === QueueConsumerMode::RequestSpawn;
    }

    /**
     * @param list<string> $alerting the ids of the checks that fired
     *
     * @return array<string, mixed>
     */
    private function tiles(array $alerting): array
    {
        $pending = $this->statistics->pendingCount();
        $oldest = $this->statistics->oldestPendingAt();
        $outcomes = $this->statistics->outcomes(self::DAYS);
        $ended = $outcomes['handled'] + $outcomes['failed'] + $outcomes['killed'];

        return [
            'pending' => $pending,
            'oldest' => $pending > 0 && $oldest !== null && $oldest <= $this->clock->now() ? $this->age($oldest) : null,
            'pendingLevel' => match (true) {
                in_array('queue-stalled', $alerting, true) => 'danger',
                in_array('queue-backlog', $alerting, true) => 'warning',
                default => '',
            },
            'running' => $this->statistics->inProgressCount(),
            'failed' => $this->statistics->failedCount(),
            'handled' => $outcomes['handled'],
            'rate' => $ended === 0 ? null : $this->decimal(100 * $outcomes['handled'] / $ended),
            'duration' => $this->milliseconds($outcomes['avgMs']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function types(): array
    {
        return array_map(fn (array $type): array => [
            'label' => self::typeLabel($type['type']),
            'type' => $type['type'],
            'pending' => $type['pending'],
            'failed' => $type['failed'],
            'lastSuccess' => $this->ago($type['lastSuccessAt']),
            'duration' => $this->milliseconds($type['avgMs']),
        ], $this->statistics->byType(self::DAYS));
    }

    /** @return list<array<string, mixed>> */
    private function failed(): array
    {
        return array_map(fn (array $message): array => [
            'id' => $message['id'],
            'label' => self::typeLabel($message['type']),
            'type' => $message['type'],
            'attempts' => $message['attempts'],
            'error' => $message['error'],
            'killedIn' => $message['killedIn'],
            'since' => $this->ago($message['createdAt']),
        ], $this->statistics->failed(self::LISTED));
    }

    /**
     * The tasks the schedule declares, as their rows say they last ran; a task never queued yet has no row and is not listed.
     *
     * @return list<array<string, mixed>>
     */
    private function tasks(): array
    {
        $declared = array_flip($this->ticker->declaredTaskIds());
        $now = $this->clock->now()->getTimestamp();
        $patience = QueueHealthChecks::stalledAfterSeconds($this->settings->mode());
        $rows = [];

        foreach ($this->statistics->tasks() as $task) {
            if (!isset($declared[$task['taskId']])) {
                continue;
            }
            $due = $task['nextDueAt'];
            // Late as the overdue check judges it, so the table and the alert agree.
            $lateBy = $due === null ? null : $now - $due->getTimestamp();
            $rows[] = [
                'taskId' => $task['taskId'],
                'label' => $task['label'],
                'every' => $task['everySeconds'] > 0 ? $this->interval($task['everySeconds']) : null,
                'lastRun' => $this->ago($task['lastFinishedAt']),
                'status' => $task['lastStatus'],
                'statusLabel' => $task['lastStatus'] === null ? null : _t('QUEUE_STATUS_' . strtoupper($task['lastStatus'])),
                'error' => $task['lastError'],
                'failures' => $task['consecutiveFailures'],
                'next' => $this->ago($due),
                'due' => $lateBy !== null && $lateBy >= 0,
                'late' => $lateBy !== null && $lateBy > max($task['everySeconds'], $patience),
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function history(): array
    {
        return array_map(fn (array $attempt): array => [
            'when' => $this->ago($attempt['finishedAt']),
            'at' => $attempt['finishedAt']->format('Y-m-d H:i:s'),
            'label' => self::typeLabel($attempt['type']),
            'type' => $attempt['type'],
            'status' => $attempt['status'],
            'statusLabel' => _t('QUEUE_STATUS_' . strtoupper($attempt['status'])),
            'mode' => $attempt['mode'],
            'duration' => $attempt['status'] === 'ignored' ? null : $this->milliseconds($attempt['durationMs']),
            'error' => $attempt['error'],
        ], $this->statistics->history(self::LISTED));
    }

    /** @return list<array<string, mixed>> */
    private function consumers(): array
    {
        return array_map(function (array $pass): array {
            $end = $pass['finishedAt'];

            return [
                'started' => $this->ago($pass['startedAt']),
                'at' => $pass['startedAt']->format('Y-m-d H:i:s'),
                'mode' => $pass['mode'],
                'running' => $pass['stopReason'] === '',
                // A blocking consumer's first heartbeat comes a minute after it starts: until then, its start is when it was last seen.
                'lastSeen' => $this->ago($end),
                // The rows keep whole seconds: a pass that took less shows as such, not as "0 ms".
                'duration' => $end->getTimestamp() - $pass['startedAt']->getTimestamp() < 1
                    ? _t('ADMIN_JOBS_UNDER_A_SECOND')
                    : $this->milliseconds(1000 * ($end->getTimestamp() - $pass['startedAt']->getTimestamp())),
                'handled' => $pass['handled'],
                'stop' => self::stopReasonLabel($pass['stopReason']),
                'stopReason' => $pass['stopReason'],
                'budget' => $pass['budgetMs'] === null ? null : $this->milliseconds($pass['budgetMs']),
                'memory' => $pass['peakMemory'] > 0 ? (int)round($pass['peakMemory'] / 1048576) : null,
                'memoryLimit' => $pass['memoryLimit'] > 0 ? (int)round($pass['memoryLimit'] / 1048576) : null,
                'pid' => $pass['pid'],
                'visitorWaited' => $pass['beforeResponse'],
                'fallbackFor' => $pass['fallbackFor'],
            ];
        }, $this->statistics->recentPasses(self::LISTED));
    }

    /** "420 ms", "1,8 s", "300 s"; null when there is nothing to say. */
    private function milliseconds(?int $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        return match (true) {
            $milliseconds < 1000 => _t('ADMIN_JOBS_MILLISECONDS', ['count' => $milliseconds]),
            $milliseconds < 10000 => _t('ADMIN_JOBS_SECONDS', ['count' => $this->decimal($milliseconds / 1000)]),
            default => _t('ADMIN_JOBS_SECONDS', ['count' => (int)round($milliseconds / 1000)]),
        };
    }

    /** "30 minutes", "1 jour": how far apart a task's runs are. */
    private function interval(int $seconds): string
    {
        return CarbonInterval::seconds($seconds)->cascade()->locale($this->languages->preferredLanguage())->forHumans(['parts' => 2]);
    }

    /** One decimal, with the separator of the reader's language. */
    private function decimal(float $number): string
    {
        return number_format($number, 1, _t('ADMIN_JOBS_DECIMAL_SEPARATOR'), '');
    }

    /** `request-spawn` → `REQUEST_SPAWN`, the shape of the translation keys. */
    private static function keyOf(QueueConsumerMode $mode): string
    {
        return strtoupper(str_replace('-', '_', $mode->value));
    }
}

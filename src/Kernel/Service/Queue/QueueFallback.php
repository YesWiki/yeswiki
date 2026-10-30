<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFallbackStatus;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Whether the end of requests has to consume the queue for the consumer the admin chose, because that consumer is no longer seen at work.
 *
 * A worker that died, a crontab an host dropped, an online cron that stopped calling: on a wiki
 * nobody watches, the search index and the maintenance would stop for weeks without a visitor
 * noticing. Once the chosen consumer has not been seen for longer than its mode allows while work
 * waits, each request consumes at its end as `request` does, and the pages say so; as soon as
 * that consumer is seen again, the requests leave the queue to it. `request` is that fallback, and
 * has none.
 *
 * Nothing is stored for it: it is derived from the pass rows (QueueStatistics::lastSeenAt(),
 * fallbackSince()). Its fallback passes carry the mode they stand in for, so the fallback lasts
 * until that consumer is seen again, not until the queue is empty -- otherwise each pass that
 * empties it would end it, and the work piling up again would start it. A few `cache/` stamps
 * keep requests from reading the database for it more than once a minute, and remember the first
 * launch of `request-spawn` that went unanswered.
 */
class QueueFallback
{
    /** Seconds between two readings of the rule outside the fallback, and of the consumer's return during it. */
    public const CHECK_EVERY_SECONDS = 60;

    /** Seconds a launch of `request-spawn` may go unanswered while work waits: its own delay (a day, the visits') is far too long for a process that never starts. */
    public const SPAWN_SILENT_SECONDS = 300;

    /** When the rule was last read. */
    public const CHECK_STAMP = 'cache/queue-fallback-check.lock';

    /** There while the last reading said the end of requests consumes for that mode: one per mode, so that a mode the admin just chose does not inherit the last one's answer. */
    public const ACTIVE_STAMP = 'cache/queue-fallback-active-%s.lock';

    /** When the rule was first read: what has waited since, on a wiki whose chosen consumer never ran and whose schedule never ticked. */
    public const WATCH_STAMP = 'cache/queue-fallback-watch.lock';

    /** The first launch of `request-spawn` no pass of its own has answered yet. */
    public const SPAWN_UNANSWERED = 'cache/queue-spawn-unanswered.lock';

    private ClockInterface $clock;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly HibernationService $hibernation,
        private readonly RuntimeLock $runtimeLock,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /** Whether a consumer of that mode can be replaced by the end of requests: all of them but the end of requests itself. */
    public static function canFallBack(QueueConsumerMode $mode): bool
    {
        return $mode !== QueueConsumerMode::Request;
    }

    /** The fallback the wiki is in, read now: for the pages, which a request does not open often. */
    public function status(): ?QueueFallbackStatus
    {
        $mode = $this->settings->mode();
        if (!self::canFallBack($mode)) {
            return null;
        }
        $since = $this->statistics->fallbackSince($mode, self::patience($mode));

        return $since === null ? null : new QueueFallbackStatus($mode, $since, $this->statistics->lastSeenAt($mode));
    }

    /**
     * Whether the chosen consumer cannot be counted on to take a long message now: the end of requests already stands in for it, or the worker it is has not been seen for longer than its mode allows.
     *
     * For whoever sends a message the end of a request must not take on a request's budget (a
     * backup an admin starts, ArchiveService). Read only: the minute's answer shouldConsume()
     * keeps for the end of requests is left as it is. A worker beats every minute and holds a live
     * claim while it handles, so one unseen for its delay is gone, or never ran; a consumer that
     * only comes when called -- a cron, an API call, a launched process -- shows nothing that says
     * it will come, and is gone only once the fallback runs for it.
     */
    public function consumerIsGone(): bool
    {
        $mode = $this->settings->mode();
        if (!self::canFallBack($mode)) {
            return false;
        }
        if ($this->status() !== null) {
            return true;
        }
        if ($mode->isBudgeted()) {
            return false;
        }
        $seen = $this->statistics->lastSeenAt($mode);

        return $seen === null || $this->clock->now()->getTimestamp() - $seen->getTimestamp() > QueueHealthChecks::stalledAfterSeconds($mode);
    }

    /** How long the consumer of that mode must have been unseen before the end of requests stands in for it. */
    private static function patience(QueueConsumerMode $mode): int
    {
        return $mode === QueueConsumerMode::RequestSpawn ? self::SPAWN_SILENT_SECONDS : QueueHealthChecks::stalledAfterSeconds($mode);
    }

    /**
     * Whether this request consumes at its end for the chosen consumer.
     *
     * Read at most once a minute; in between, the last answer stands, so that a request with
     * nothing to do in a mode that does not consume at the end of requests costs a file's `stat`
     * and no query.
     *
     * @param \Closure(): bool $hasWork whether a message can be taken now or the schedule is due; only asked when the answer depends on it
     */
    public function shouldConsume(\Closure $hasWork): bool
    {
        $mode = $this->settings->mode();
        if (!self::canFallBack($mode)) {
            return false;
        }
        $now = $this->clock->now()->getTimestamp();
        $active = sprintf(self::ACTIVE_STAMP, $mode->value);
        if ($now - $this->runtimeLock->lastTaken(self::CHECK_STAMP) < self::CHECK_EVERY_SECONDS) {
            return $this->runtimeLock->lastTaken($active) > 0;
        }

        // Stamped before reading, and the last answer dropped: a reading that fails (the table missing) is tried again in a minute, not on every request.
        $this->runtimeLock->stampAt(self::CHECK_STAMP, $now);
        $this->runtimeLock->forget($active);
        $consumes = $this->statistics->fallbackSince($mode, self::patience($mode)) !== null || $this->entersNow($mode, $now, $hasWork);
        if ($consumes) {
            $this->runtimeLock->stampAt($active, $now);
        }

        return $consumes;
    }

    /**
     * A launch of `request-spawn` was attempted, whether or not a process started: the first of those no pass of its own has answered yet is kept.
     *
     * Attempts come once a minute, so only the first unanswered one can show a process that never
     * consumes: the last one is never five minutes old on a wiki with a visit a minute.
     */
    public function spawnAttempted(): void
    {
        $first = $this->runtimeLock->lastTaken(self::SPAWN_UNANSWERED);
        if ($first > 0) {
            $seen = $this->statistics->lastSeenAt(QueueConsumerMode::RequestSpawn);
            if ($seen === null || $first > $seen->getTimestamp()) {
                return;
            }
        }
        $this->runtimeLock->stampAt(self::SPAWN_UNANSWERED, $this->clock->now()->getTimestamp());
    }

    /**
     * Whether the chosen consumer is to be replaced now, when the end of requests does not consume for it yet.
     *
     * Work has waited longer than the mode allows (QueueHealthChecks::stalledAfterSeconds()), and
     * the consumer has not been seen for as long. On a quiet wiki nothing is queued while nothing
     * ticks the schedule: the schedule being due is the work that waits. A schedule that never
     * ticked has waited since the rule was first read.
     *
     * @param \Closure(): bool $hasWork
     */
    private function entersNow(QueueConsumerMode $mode, int $now, \Closure $hasWork): bool
    {
        if ($this->hibernation->isWikiHibernatedNow()) {
            return false;
        }
        $seen = $this->statistics->lastSeenAt($mode)?->getTimestamp();

        if ($mode === QueueConsumerMode::RequestSpawn) {
            $first = $this->runtimeLock->lastTaken(self::SPAWN_UNANSWERED);

            return $first > 0 && ($seen === null || $first > $seen) && $now - $first > self::SPAWN_SILENT_SECONDS && $hasWork();
        }

        if ($this->runtimeLock->lastTaken(self::WATCH_STAMP) === 0) {
            $this->runtimeLock->stampAt(self::WATCH_STAMP, $now);
        }
        $waiting = array_filter([
            $this->statistics->oldestPendingAt()?->getTimestamp(),
            $this->statistics->scheduleDueAt()?->getTimestamp() ?? $this->runtimeLock->lastTaken(self::WATCH_STAMP),
        ], static fn (?int $since): bool => $since !== null);
        $patience = QueueHealthChecks::stalledAfterSeconds($mode);

        return $now - min($waiting) > $patience && ($seen === null || $now - $seen > $patience);
    }
}

<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFallbackStatus;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Whether the end of requests has to consume the queue for the consumer the admin chose, because that consumer is no longer seen at work.
 *
 * A worker that died, an online cron that stopped calling: on a wiki
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
 * keep requests from reading the database for it more than once a minute.
 */
class QueueFallback implements RequestScopedState
{
    /** Seconds between two readings of the rule outside the fallback, and of the consumer's return during it. */
    public const CHECK_EVERY_SECONDS = 60;

    /** When the rule was last read. */
    public const CHECK_STAMP = 'cache/queue-fallback-check.lock';

    /** There while the last reading said the end of requests consumes for that mode: one per mode, so that a mode the admin just chose does not inherit the last one's answer. */
    public const ACTIVE_STAMP = 'cache/queue-fallback-active-%s.lock';

    /** When the rule was first read: what has waited since, on a wiki whose chosen consumer never ran and whose schedule never ticked. */
    public const WATCH_STAMP = 'cache/queue-fallback-watch.lock';

    /**
     * What the Journal said of the chosen consumer this request: the badge, the jobs page and a backup being started all ask.
     *
     * @var array{mode: QueueConsumerMode, seen: ?\DateTimeImmutable, status: ?QueueFallbackStatus}|null
     */
    private ?array $observed = null;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly HibernationService $hibernation,
        private readonly RuntimeLock $runtimeLock,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Whether a consumer of that mode can be replaced by the end of requests: all of them but the end of requests itself. */
    public static function canFallBack(QueueConsumerMode $mode): bool
    {
        return $mode !== QueueConsumerMode::Request;
    }

    public function startNewRequest(): void
    {
        $this->observed = null;
    }

    /** The fallback the wiki is in, read once per request: for the pages, which a request does not open often. */
    public function status(): ?QueueFallbackStatus
    {
        $mode = $this->settings->mode();

        return self::canFallBack($mode) ? $this->observed($mode)['status'] : null;
    }

    /**
     * Whether the chosen consumer cannot be counted on to take a long message now: the end of requests already stands in for it, or the worker it is has not been seen for longer than its mode allows.
     *
     * For whoever sends a message the end of a request must not take on a request's budget (a
     * backup an admin starts, ArchiveService). Read only: the minute's answer shouldConsume()
     * keeps for the end of requests is left as it is. A worker beats every minute and holds a live
     * claim while it handles, so one unseen for its delay is gone, or never ran; the API route
     * only comes when called, shows nothing that says it will come, and is gone only once the
     * fallback runs for it.
     */
    public function consumerIsGone(): bool
    {
        $mode = $this->settings->mode();
        if (!self::canFallBack($mode)) {
            return false;
        }
        ['seen' => $seen, 'status' => $status] = $this->observed($mode);
        if ($status !== null) {
            return true;
        }
        if ($mode->isBudgeted()) {
            return false;
        }

        return $seen === null || $this->clock->now()->getTimestamp() - $seen->getTimestamp() > QueueHealthChecks::stalledAfterSeconds($mode);
    }

    /**
     * Whether this request consumes at its end for the chosen consumer.
     *
     * Read at most once a minute; in between, the last answer stands, so that a request with
     * nothing to do in a mode that does not consume at the end of requests costs a file's `stat`
     * and no query.
     */
    public function shouldConsume(): bool
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
        $seen = $this->statistics->lastSeenAt($mode);
        $consumes = $this->statistics->fallbackSince($mode, $seen, QueueHealthChecks::stalledAfterSeconds($mode)) !== null || $this->entersNow($mode, $now, $seen);
        if ($consumes) {
            $this->runtimeLock->stampAt($active, $now);
        }

        return $consumes;
    }

    /**
     * Whether the chosen consumer is to be replaced now, when the end of requests does not consume for it yet.
     *
     * Work has waited longer than the mode allows (QueueHealthChecks::stalledAfterSeconds()), and
     * the consumer has not been seen for as long. On a quiet wiki nothing is queued while nothing
     * ticks the schedule: the schedule being due is the work that waits. A schedule that never
     * ticked has waited since the rule was first read.
     */
    private function entersNow(QueueConsumerMode $mode, int $now, ?\DateTimeImmutable $lastSeen): bool
    {
        if ($this->hibernation->isWikiHibernatedNow()) {
            return false;
        }
        $seen = $lastSeen?->getTimestamp();

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

    /**
     * When the chosen consumer was last seen, and the fallback that stands in for it, if any.
     *
     * @return array{mode: QueueConsumerMode, seen: ?\DateTimeImmutable, status: ?QueueFallbackStatus}
     */
    private function observed(QueueConsumerMode $mode): array
    {
        if ($this->observed === null || $this->observed['mode'] !== $mode) {
            $seen = $this->statistics->lastSeenAt($mode);
            $since = $this->statistics->fallbackSince($mode, $seen, QueueHealthChecks::stalledAfterSeconds($mode));
            $this->observed = ['mode' => $mode, 'seen' => $seen, 'status' => $since === null ? null : new QueueFallbackStatus($mode, $since, $seen)];
        }

        return $this->observed;
    }
}

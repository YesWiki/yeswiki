<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFallbackStatus;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Whether the end of requests has to consume the queue for the consumer the admin chose, because that consumer is
 * no longer seen at work; derived from the pass rows (QueueStatistics), with `cache/` stamps read once a minute.
 */
class QueueFallback implements RequestScopedState
{
    public const CHECK_EVERY_SECONDS = 60;

    public const CHECK_STAMP = 'cache/queue-fallback-check.lock';

    public const ACTIVE_STAMP = 'cache/queue-fallback-active-%s.lock';

    public const WATCH_STAMP = 'cache/queue-fallback-watch.lock';

    /** @var array{mode: QueueConsumerMode, seen: ?\DateTimeImmutable, status: ?QueueFallbackStatus}|null */
    private ?array $observed = null;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
        private readonly QueueScheduleTicker $ticker,
        private readonly HibernationService $hibernation,
        private readonly RuntimeLock $runtimeLock,
        private readonly ClockInterface $clock,
    ) {
    }

    public function startNewRequest(): void
    {
        $this->observed = null;
    }

    /** Who consumes now: the chosen mode, or `request` while the end of requests stands in for it. */
    public function consumingMode(): QueueConsumerMode
    {
        return $this->status() === null ? $this->settings->mode() : QueueConsumerMode::Request;
    }

    /** The fallback the wiki is in, read once per request: for the pages, which a request does not open often. */
    public function status(): ?QueueFallbackStatus
    {
        $mode = $this->settings->mode();

        return $mode->consumesAtEndOfRequests() ? null : $this->observed($mode)['status'];
    }

    /** Whether the chosen consumer cannot be counted on to take a long message now (read only, for senders). */
    public function consumerIsGone(): bool
    {
        $mode = $this->settings->mode();
        if ($mode->consumesAtEndOfRequests()) {
            return false;
        }
        ['seen' => $seen, 'status' => $status] = $this->observed($mode);
        if ($status !== null) {
            return true;
        }
        if ($mode->isBudgeted()) {
            return false;
        }

        return $seen === null || $this->clock->now()->getTimestamp() - $seen->getTimestamp() > $mode->stalledAfterSeconds();
    }

    /** Whether this request consumes at its end for the chosen consumer; read at most once a minute. */
    public function shouldConsume(): bool
    {
        $mode = $this->settings->mode();
        if ($mode->consumesAtEndOfRequests()) {
            return false;
        }
        $now = $this->clock->now()->getTimestamp();
        $active = sprintf(self::ACTIVE_STAMP, $mode->value);
        if ($now - $this->runtimeLock->lastTaken(self::CHECK_STAMP) < self::CHECK_EVERY_SECONDS) {
            return $this->runtimeLock->lastTaken($active) > 0;
        }

        $this->runtimeLock->stampAt(self::CHECK_STAMP, $now);
        $this->runtimeLock->forget($active);
        $seen = $this->statistics->lastSeenAt($mode);
        $consumes = $this->statistics->fallbackSince($mode, $seen, $mode->stalledAfterSeconds()) !== null || $this->entersNow($mode, $now, $seen);
        if ($consumes) {
            $this->runtimeLock->stampAt($active, $now);
        }

        return $consumes;
    }

    /** Whether work and the consumer have both waited longer than the mode allows, when no fallback runs yet. */
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
            $this->ticker->dueAt()?->getTimestamp() ?? $this->runtimeLock->lastTaken(self::WATCH_STAMP),
        ], static fn (?int $since): bool => $since !== null);
        $patience = $mode->stalledAfterSeconds();

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
            $since = $this->statistics->fallbackSince($mode, $seen, $mode->stalledAfterSeconds());
            $this->observed = ['mode' => $mode, 'seen' => $seen, 'status' => $since === null ? null : new QueueFallbackStatus($mode, $since, $seen)];
        }

        return $this->observed;
    }
}

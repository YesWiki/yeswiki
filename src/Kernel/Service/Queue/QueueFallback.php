<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFallbackStatus;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Whether the end of requests has to consume the queue for the consumer the admin chose: a rule with no memory.
 * In `api` or `worker`, the wiki awake, that consumer not seen (QueueStatistics::lastSeenAt()) for longer than its mode's delay;
 * as soon as it is seen again, the rule no longer holds. The end of requests reads it at most once a minute, from two `cache/` stamps.
 */
class QueueFallback implements RequestScopedState
{
    public const CHECK_EVERY_SECONDS = 60;

    public const CHECK_STAMP = 'cache/queue-fallback-check.lock';

    public const ACTIVE_STAMP = 'cache/queue-fallback-active-%s.lock';

    /** @var array{mode: QueueConsumerMode, status: ?QueueFallbackStatus}|null */
    private ?array $observed = null;

    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueStatistics $statistics,
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

    /** The fallback the wiki is in, read once per request; null when the chosen consumer needs none. */
    public function status(): ?QueueFallbackStatus
    {
        $mode = $this->settings->mode();
        if ($this->observed === null || $this->observed['mode'] !== $mode) {
            $this->observed = ['mode' => $mode, 'status' => $this->judged($mode)];
        }

        return $this->observed['status'];
    }

    /** Whether this request consumes at its end for the chosen consumer; the rule is read at most once a minute, its answer kept for that mode in between. */
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
        $consumes = $this->status() !== null;
        if ($consumes) {
            $this->runtimeLock->stampAt($active, $now);
        }

        return $consumes;
    }

    /** The rule itself: the fallback for that mode's consumer when it has not been seen within its delay, null otherwise. */
    private function judged(QueueConsumerMode $mode): ?QueueFallbackStatus
    {
        if ($mode->consumesAtEndOfRequests() || $this->hibernation->isWikiHibernatedNow()) {
            return null;
        }
        $delay = $mode->stalledAfterSeconds();
        $lastSeen = $this->statistics->lastSeenAt($mode);
        if ($lastSeen !== null && $this->clock->now()->getTimestamp() - $lastSeen->getTimestamp() <= $delay) {
            return null;
        }

        return new QueueFallbackStatus($mode, $lastSeen?->modify("+{$delay} seconds"), $lastSeen);
    }
}

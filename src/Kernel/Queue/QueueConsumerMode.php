<?php

namespace YesWiki\Kernel\Queue;

/**
 * Who consumes the queue, and when: `request` at the end of each web request, `api` from an external HTTP call,
 * `worker` a long-lived process. The value is what an admin writes in YESWIKI_QUEUE_CONSUMER or `queue_consumer`.
 */
enum QueueConsumerMode: string
{
    case Request = 'request';
    case Api = 'api';
    case Worker = 'worker';

    /** Whether a pass in this mode has to end by itself, before the time PHP gives it runs out. */
    public function isBudgeted(): bool
    {
        return match ($this) {
            self::Request, self::Api => true,
            self::Worker => false,
        };
    }

    /** Whether the end of each request is this mode's consumer; for the others, it only stands in for one that is gone (QueueFallback). */
    public function consumesAtEndOfRequests(): bool
    {
        return $this === self::Request;
    }

    /** How long no consumer of this mode may be seen at work while messages wait before it counts as stalled (`queue-stalled`). */
    public function stalledAfterSeconds(): int
    {
        return match ($this) {
            self::Worker => 600,
            self::Api => 3 * 3600,
            self::Request => 24 * 3600,
        };
    }

    /** The longest a pass of this mode runs, whatever PHP allows; INF for a mode with no deadline. */
    public function passCeilingSeconds(bool $responseAlreadySent): float
    {
        return match ($this) {
            self::Request => $responseAlreadySent ? 20.0 : 3.0,
            self::Api => 50.0,
            self::Worker => INF,
        };
    }

    /** The mode a pass started from a web page runs in: this one when it ends by itself, `request` for a consumer that would block. */
    public function forPassFromTheWeb(): self
    {
        return $this->isBudgeted() ? $this : self::Request;
    }
}

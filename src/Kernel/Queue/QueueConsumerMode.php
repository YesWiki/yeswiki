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

    /** Whether a pass in this mode stops by itself once the queue is empty, rather than waiting for messages. */
    public function endsByItself(): bool
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

    /** How long this mode's consumer may go unseen: past it, the end of requests stands in for a cron or a worker (QueueFallback), and counts as stalled itself (`queue-stalled`). */
    public function stalledAfterSeconds(): int
    {
        return match ($this) {
            self::Worker => 600,
            self::Api => 3 * 3600,
            self::Request => 24 * 3600,
        };
    }
}

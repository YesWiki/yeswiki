<?php

namespace YesWiki\Kernel\Queue;

/**
 * Who consumes the queue, and when. The value is what an admin writes in YESWIKI_QUEUE_CONSUMER or `queue_consumer`.
 *
 * What a mode implies is said here, once: what its passes may spend, when its consumer is late,
 * whether the end of a request is its consumer.
 */
enum QueueConsumerMode: string
{
    /** At the end of each web request, in its process. */
    case Request = 'request';
    /** From an external HTTP call (online cron, webhook, monitor). */
    case Api = 'api';
    /** A long-lived process kept alive by the Go binary when it serves the wiki, by the host otherwise (systemd, supervisord, a container). */
    case Worker = 'worker';

    /** Whether a pass in this mode has to end by itself, before the time PHP gives it runs out (the others block until told to stop). */
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

    /**
     * How long no consumer of this mode may have been seen at work while messages are available, before it counts as stalled: what `queue-stalled` waits, and the fallback with it.
     *
     * "No pass for an hour" is an outage under a worker, and the normal state of a quiet wiki that
     * consumes at the end of a request.
     */
    public function stalledAfterSeconds(): int
    {
        return match ($this) {
            self::Worker => 600,
            self::Api => 3 * 3600,
            self::Request => 24 * 3600,
        };
    }

    /**
     * The longest a pass of this mode runs, whatever PHP allows: a visitor who waits for the response must not wait for the queue, and an HTTP caller gives up long before a command would. INF for a mode with no deadline.
     *
     * @param bool $responseAlreadySent whether the visitor already has the response (FPM, LiteSpeed, the FrankenPHP worker) rather than waiting for it (mod_php, CGI); only `request` cares
     */
    public function passCeilingSeconds(bool $responseAlreadySent): float
    {
        return match ($this) {
            self::Request => $responseAlreadySent ? 20.0 : 3.0,
            self::Api => 50.0,
            self::Worker => INF,
        };
    }

    /** The mode a pass started from a web page runs in, the jobs page's button: this one when it ends by itself, `request` for a consumer that would block the page. */
    public function forPassFromTheWeb(): self
    {
        return $this->isBudgeted() ? $this : self::Request;
    }
}

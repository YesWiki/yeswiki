<?php

namespace YesWiki\Kernel\Queue;

/** Who consumes the queue, and when. The value is what an admin writes in YESWIKI_QUEUE_CONSUMER or `queue_consumer`. */
enum QueueConsumerMode: string
{
    /** At the end of each web request, in its process. */
    case Request = 'request';
    /** At the end of each web request, in a CLI process it starts. */
    case RequestSpawn = 'request-spawn';
    /** From a crontab or any external scheduler, through the non-blocking command. */
    case Command = 'command';
    /** From an external HTTP call (online cron, webhook, monitor). */
    case Api = 'api';
    /** A long-lived process kept alive by the host (systemd, supervisord, a container). */
    case Worker = 'worker';
    /** The Go binary supervises a long-lived consumer per wiki. */
    case Binary = 'binary';

    /** Whether a pass in this mode has to end by itself, before the time PHP gives it runs out (the others block until told to stop). */
    public function isBudgeted(): bool
    {
        return match ($this) {
            self::Request, self::RequestSpawn, self::Command, self::Api => true,
            self::Worker, self::Binary => false,
        };
    }
}

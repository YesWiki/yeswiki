<?php

namespace YesWiki\Kernel\Queue;

/**
 * What started a pass, which its row keeps: only the consumer the admin chose proves that consumer is at work (QueueFallback).
 *
 * The row's actor is the mode the pass ran under, which is the configured mode whatever started
 * it: a backup an admin pinned in a web process, or the jobs page's button, writes `worker` as the
 * worker itself does. Without the origin, either would make a dead worker look alive.
 */
enum QueuePassOrigin: string
{
    /** The consumer of the mode: the worker, the binary, a cron's or a spawned `messenger:drain`, the API route, the end of a request in `request`. */
    case Consumer = 'consumer';
    /** The messages a request pinned, run at its end whatever the mode. */
    case Pinned = 'pinned';
    /** The jobs page's "consume now" button. */
    case Manual = 'manual';
    /** The end of a request, consuming for a consumer that is no longer seen. */
    case Fallback = 'fallback';
}

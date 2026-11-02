<?php

namespace YesWiki\Kernel\Queue;

/**
 * What started a pass, which its row keeps: only the consumer the admin chose proves that consumer is at work (QueueFallback).
 *
 * The row's actor is the mode the pass ran under, which is mostly the configured mode whatever
 * started it: a backup an admin pinned in a web process writes `worker` as the worker itself does,
 * the jobs page's button `api` as the API route does. Without the origin, either would make a
 * consumer that is gone look alive.
 */
enum QueuePassOrigin: string
{
    /** The consumer of the mode: the worker (kept up by the binary or by the host), the API route, the end of a request in `request`. */
    case Consumer = 'consumer';
    /** The messages a request pinned, run at its end whatever the mode. */
    case Pinned = 'pinned';
    /** A pass started by hand: the jobs page's "consume now" button, or `messenger:consume --drain`. */
    case Manual = 'manual';
    /** The end of a request, consuming for a consumer that is no longer seen. */
    case Fallback = 'fallback';
}

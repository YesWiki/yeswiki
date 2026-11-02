<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a message that must not run on a request's budget, such as a backup or a restore an admin starts.
 *
 * Where no consumer of its own will take it soon (the budgeted modes, or a worker that is gone),
 * the request that sent it runs it once its response has left, without PHP's time limit where the
 * host allows it (QueueRequestTrigger::pin()). Meanwhile it is held back from every other pass, so
 * that another visitor's end of request does not take it on a request's budget; should the request
 * end without running it, the queue takes it once the hold is over.
 */
interface RunsUnbudgeted
{
    /** How long such a message is held back from every pass but the one of the request that sent it. */
    public const HELD_FOR_THE_SENDER_SECONDS = 300;
}

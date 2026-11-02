<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a message the jobs page must not send again, such as a backup or a restore: once failed, it is started again where it was started, never retried behind the admin's back.
 *
 * Its copy in `failed` follows nothing any more (the run's progress, the request that would have
 * run it), so the transport refuses to requeue it (QueueTransport::requeue()), and the page offers
 * no button that would.
 */
interface NotReplayableByHand
{
}

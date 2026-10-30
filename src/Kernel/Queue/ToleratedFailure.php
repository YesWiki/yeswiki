<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a message whose failures are routine, such as an ActivityPub delivery to a server that is gone for good.
 *
 * It is retried, failed and journaled like any other message. The only difference is that the
 * generic health check on the `failed` queue leaves it out: a permanent red badge for dead
 * fediverse inboxes would teach the admin to ignore every badge, so the module that sends such
 * messages declares a check of its own that judges them (for instance, only when all of them fail).
 */
interface ToleratedFailure
{
}

<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a message that does not want the default retries (3 attempts, 60 s x 5^n, capped at 30 min).
 *
 * Zero means a failure goes straight to the `failed` queue: for work whose second run would be
 * wrong or pointless, such as a backup archive or a digest mail.
 */
interface RetryPolicy
{
    /** How many times a failed handling is attempted again; 0 for never. */
    public function maxRetries(): int;
}

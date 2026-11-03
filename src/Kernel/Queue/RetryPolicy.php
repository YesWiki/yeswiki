<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message that does not want the default retries (3 attempts, 60 s x 5^n, capped at 30 min). */
interface RetryPolicy
{
    /** How many times a failed handling is attempted again; 0 for never. */
    public function maxRetries(): int;
}

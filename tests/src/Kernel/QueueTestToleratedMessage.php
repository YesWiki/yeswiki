<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ToleratedFailure;

/** A message whose failures are routine, like a delivery to a server that is gone; failed at the first error to keep the test short. */
final class QueueTestToleratedMessage implements ToleratedFailure, RetryPolicy
{
    public function __construct(public string $value)
    {
    }

    public function maxRetries(): int
    {
        return 0;
    }
}

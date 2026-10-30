<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Queue\RetryPolicy;

/** A message whose work must not run twice, like a backup archive: a failure sends it straight to `failed`. */
final class QueueTestOnceMessage implements RetryPolicy
{
    public function __construct(public string $value)
    {
    }

    public function maxRetries(): int
    {
        return 0;
    }
}

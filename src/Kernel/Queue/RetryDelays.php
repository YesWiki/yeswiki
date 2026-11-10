<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message whose retries follow waits of its own rather than QueueRetryStrategy's. */
interface RetryDelays
{
    /** @return non-empty-list<int> the wait before each retry, in seconds, the first one first; the last one repeats */
    public function retryDelays(): array;
}

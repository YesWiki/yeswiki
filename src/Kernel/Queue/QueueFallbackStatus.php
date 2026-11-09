<?php

namespace YesWiki\Kernel\Queue;

/**
 * The end of requests consumes for a consumer that is no longer seen (QueueFallback::status()): which one, when it was last seen,
 * and since when the requests consume, its mode's delay after that; both null for a consumer never seen.
 */
final class QueueFallbackStatus
{
    public function __construct(
        public readonly QueueConsumerMode $mode,
        public readonly ?\DateTimeImmutable $since,
        public readonly ?\DateTimeImmutable $lastSeen,
    ) {
    }
}

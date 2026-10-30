<?php

namespace YesWiki\Kernel\Queue;

/** The end of requests consumes for a consumer that is no longer seen: which one, since when, and when it was last seen (QueueFallback::status()). */
final class QueueFallbackStatus
{
    /**
     * @param \DateTimeImmutable|null $lastSeen null when that consumer never was
     */
    public function __construct(
        public readonly QueueConsumerMode $mode,
        public readonly \DateTimeImmutable $since,
        public readonly ?\DateTimeImmutable $lastSeen,
    ) {
    }
}

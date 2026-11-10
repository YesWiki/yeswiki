<?php

namespace YesWiki\Kernel\Queue;

/**
 * What stops one consumption pass from the outside: the memory it has to stop before PHP kills it, and the instant
 * PHP's own time limit (less a margin) makes it stop taking messages. Read between two messages only.
 */
final class QueuePassLimits
{
    /**
     * @param float|null $phpStopsAt        microtime(true) at which the pass stops taking messages; null when no limit of PHP's holds
     * @param bool       $phpLimitIsCeiling whether that limit is at the wiki's ceiling, rather than below it for want of the host's leave
     */
    public function __construct(
        public readonly int $memoryCeilingBytes,
        public readonly ?float $phpStopsAt = null,
        public readonly bool $phpLimitIsCeiling = false
    ) {
    }

    public function memoryExhausted(): bool
    {
        return memory_get_usage(true) > $this->memoryCeilingBytes;
    }

    public function phpLimitReached(): bool
    {
        return $this->phpStopsAt !== null && microtime(true) >= $this->phpStopsAt;
    }

    /** Why the pass stops once phpLimitReached(): the wiki's own ceiling, or a limit the host would not let it raise that far. */
    public function timeStopReason(): string
    {
        return $this->phpLimitIsCeiling ? QueuePassReport::TIME_CAP : QueuePassReport::PHP_LIMIT;
    }
}

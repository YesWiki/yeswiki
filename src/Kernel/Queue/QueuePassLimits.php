<?php

namespace YesWiki\Kernel\Queue;

/**
 * What stops one consumption pass from the outside: the memory it has to stop before PHP kills it, how long a handler
 * may run before it can only have been killed, and the instant PHP's own time limit (less a margin) makes it stop
 * taking messages. Read between two messages only.
 */
final class QueuePassLimits
{
    /**
     * @param float|null $phpStopsAt microtime(true) at which the pass stops taking messages; null when no limit of PHP's holds
     */
    public function __construct(
        public readonly int $memoryCeilingBytes,
        public readonly int $killLimitSeconds,
        public readonly ?float $phpStopsAt = null
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

    /** The same pass once PHP's limit is lifted: same memory ceiling, that kill limit, no PHP stop. */
    public function lifted(int $killLimitSeconds): self
    {
        return new self($this->memoryCeilingBytes, $killLimitSeconds);
    }
}

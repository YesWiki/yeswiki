<?php

namespace YesWiki\Kernel\Queue;

/**
 * What one consumption pass may spend: time until it has to stop by itself, memory until it has to stop before PHP kills it, and how long a handler may run before it can only have been killed.
 *
 * A pass reads it between two messages, never during one: a handler that runs past the deadline is not interrupted, it is what the "killed" detection is for.
 */
final class QueueBudget
{
    /**
     * @param float $deadline           microtime after which the pass takes no new message; INF when nothing but the consumer itself decides when to stop
     * @param int   $memoryCeilingBytes memory use (real usage) above which the pass stops; PHP_INT_MAX when PHP sets no limit
     * @param int   $killLimitSeconds   how long a message claimed under this budget may stay in progress before it is presumed killed (the transport adds its own grace)
     */
    public function __construct(
        public readonly float $deadline,
        public readonly int $memoryCeilingBytes,
        public readonly int $killLimitSeconds
    ) {
    }

    /** A budget with no time and no memory bound, for a pass that blocks until told to stop. */
    public static function unlimited(int $killLimitSeconds): self
    {
        return new self(INF, PHP_INT_MAX, $killLimitSeconds);
    }

    /** The same budget, but with no more than that many seconds from now: an admin who runs a pass by hand may want it shorter than the server allows. */
    public function cappedTo(float $seconds): self
    {
        return new self(min($this->deadline, microtime(true) + $seconds), $this->memoryCeilingBytes, $this->killLimitSeconds);
    }

    /** Seconds before the deadline; never negative, INF without a deadline. */
    public function remainingSeconds(): float
    {
        return max(0.0, $this->deadline - microtime(true));
    }

    /** Whether a message expected to take that long still fits. */
    public function canAfford(float $expectedSeconds): bool
    {
        return $this->remainingSeconds() >= $expectedSeconds;
    }

    public function memoryExhausted(): bool
    {
        return memory_get_usage(true) > $this->memoryCeilingBytes;
    }
}

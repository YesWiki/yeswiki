<?php

namespace YesWiki\Kernel\Queue;

/**
 * What one consumption pass may spend: time until it has to stop by itself, memory until it has to stop before PHP
 * kills it, and how long a handler may run before it can only have been killed. Read between two messages only.
 */
final class QueueBudget
{
    public function __construct(
        public readonly float $deadline,
        public readonly int $memoryCeilingBytes,
        public readonly int $killLimitSeconds
    ) {
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

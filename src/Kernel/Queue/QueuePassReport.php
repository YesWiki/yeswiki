<?php

namespace YesWiki\Kernel\Queue;

/**
 * What one consumption pass did, and why it stopped: what the commands print, the API route answers and the pass's
 * row in the queue's history keeps. `handled` counts the messages the pass took and ran, whatever came of them.
 */
final class QueuePassReport
{
    public const EMPTY = 'empty';
    public const PHP_LIMIT = 'php-limit';
    /** PHP's limit, at the wiki's own ceiling: the host refused nothing. */
    public const TIME_CAP = 'time-cap';
    public const MEMORY = 'memory';
    public const PAUSED = 'paused';
    public const TIME_LIMIT = 'time-limit';
    public const LIMIT = 'limit';
    public const STOPPED = 'stopped';
    public const CONFIG_CHANGED = 'config-changed';
    public const ERROR = 'error';
    /** Closed by another pass: its process is gone and will never close it. */
    public const KILLED = 'killed';

    public function __construct(
        public readonly int $handled,
        public readonly int $remaining,
        public readonly int $durationMs,
        public readonly string $stopReason,
    ) {
    }

    /** The line the commands print. */
    public function summary(): string
    {
        return "handled {$this->handled}, remaining {$this->remaining}, stopped: {$this->stopReason} ({$this->durationMs} ms)";
    }
}

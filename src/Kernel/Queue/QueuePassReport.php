<?php

namespace YesWiki\Kernel\Queue;

/**
 * What one consumption pass did, and why it stopped: what the commands print, the API route answers and the pass's row of the Journal keeps.
 *
 * `handled` counts the messages the pass took and ran, whatever came of them (handled, retried,
 * failed): it measures work done, the history says how each one ended.
 */
final class QueuePassReport
{
    /** Nothing left that is available now (delayed retries may still be waiting). */
    public const EMPTY = 'empty';
    /** The next message would not fit in the time left. */
    public const BUDGET = 'budget';
    /** Memory use passed the ceiling. */
    public const MEMORY = 'memory';
    /** Another budgeted pass holds the lock: this one did nothing. */
    public const LOCKED = 'locked';
    /** Not even the first message fitted: the request had already used up what PHP gives it. Not a failure of any message. */
    public const NO_BUDGET = 'no-budget';
    /** The wiki is hibernated (or archiving, or updating): nothing is consumed. */
    public const PAUSED = 'paused';
    /** A blocking consumer reached its --time-limit. */
    public const TIME_LIMIT = 'time-limit';
    /** A blocking consumer reached its --limit of messages. */
    public const LIMIT = 'limit';
    /** A blocking consumer was asked to stop (SIGTERM, SIGINT). */
    public const STOPPED = 'stopped';
    /** A blocking consumer found the wiki's configuration changed since it started, and stopped for its supervisor to start one that knows it. */
    public const CONFIG_CHANGED = 'config-changed';
    /** The pass itself broke (the database went away, the table is missing); journaled. */
    public const ERROR = 'error';

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

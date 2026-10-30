<?php

namespace YesWiki\Kernel\Queue;

/**
 * The actions of the Journal's `queue` channel: one `queue.run.<status>` row per handling attempt, one `queue.pass` row per consumption pass.
 *
 * None of them is the name of a `queue.*` diagnostic (`queue.message_failed`, `queue.pass_failed`…),
 * which stay on the diagnostic channel: a failure is both what broke and a line of the history.
 */
final class QueueJournalEntry
{
    /** A consumption pass: `target` is why it stopped, empty while it runs. */
    public const PASS = 'queue.pass';

    /** Followed by a QueueRecorder status: a handling attempt that ended so. */
    public const RUN_PREFIX = 'queue.run.';

    /** The action of a run that ended with that status (one of QueueRecorder's constants). */
    public static function run(string $status): string
    {
        return self::RUN_PREFIX . $status;
    }

    /** The status of a run's action; null when the action is not a run. */
    public static function statusOf(string $action): ?string
    {
        return str_starts_with($action, self::RUN_PREFIX) ? substr($action, strlen(self::RUN_PREFIX)) : null;
    }
}

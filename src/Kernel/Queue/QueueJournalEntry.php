<?php

namespace YesWiki\Kernel\Queue;

/** The actions of the Journal's `queue` channel: one `queue.run.<status>` row per handling attempt, one `queue.pass` row per pass. */
final class QueueJournalEntry
{
    public const PASS = 'queue.pass';

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

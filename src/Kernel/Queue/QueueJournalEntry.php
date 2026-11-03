<?php

namespace YesWiki\Kernel\Queue;

/** The actions of the Journal's `queue` channel: one `queue.run.<status>` row per handling attempt, one `queue.pass` row per pass, and `queue.task.*` rows the purge never takes the last of. */
final class QueueJournalEntry
{
    public const PASS = 'queue.pass';

    public const RUN_PREFIX = 'queue.run.';

    public const TASK_DECLARED = 'queue.task.declared';

    public const TASK_DISPATCHED = 'queue.task.dispatched';

    public const TASK_FINISHED = 'queue.task.finished';

    /** The actions of a scheduled task's rows, of which the Journal keeps the last per task however old. */
    public const TASK_ACTIONS = [self::TASK_DECLARED, self::TASK_DISPATCHED, self::TASK_FINISHED];

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

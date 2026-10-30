<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Tell the extensions it is time for their own housekeeping": the `maintenance.before` and `maintenance.after` hooks, as a recurring task of their own (AdminSchedule).
 *
 * It is never retried: when the handler throws, some listeners have run, and a second run would
 * do their work again. The task shows as failed on the jobs page until the next run, which is
 * the extension's author's cue to read the Journal.
 */
final class RunMaintenanceHooks implements ScheduledTask, RetryPolicy
{
    public function taskId(): string
    {
        return 'extension-hooks';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_EXTENSION_HOOKS');
    }

    public function maxRetries(): int
    {
        return 0;
    }
}

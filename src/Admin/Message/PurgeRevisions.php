<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the page revisions older than `pages_purge_time` days": a housekeeping step, a recurring task of its own (AdminSchedule).
 * The handler decides what goes from the configuration as it is when it runs; idempotent, so the default retry applies.
 */
final class PurgeRevisions implements ScheduledTask
{
    public function taskId(): string
    {
        return 'revisions';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_REVISIONS');
    }
}

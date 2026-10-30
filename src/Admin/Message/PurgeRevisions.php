<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the page revisions older than `pages_purge_time` days", a step of the wiki's housekeeping that is a recurring task of its own (AdminSchedule).
 *
 * What goes is decided by the handler when it runs, from the configuration as it is then; a run
 * deletes what is past its time, so it is idempotent and the default retry applies.
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

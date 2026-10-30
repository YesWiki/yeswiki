<?php

namespace YesWiki\Kernel\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the Journal entries past their retention", a step of the wiki's housekeeping that is a recurring task of its own (KernelSchedule).
 *
 * What it deletes is the Journal's to say (Journal::prune()); it is idempotent, so the default
 * retry applies.
 */
final class PruneJournal implements ScheduledTask
{
    public function taskId(): string
    {
        return 'journal';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_JOURNAL');
    }
}

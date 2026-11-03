<?php

namespace YesWiki\Kernel\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the Journal entries past their retention" (Journal::prune()), a recurring housekeeping task (KernelSchedule).
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

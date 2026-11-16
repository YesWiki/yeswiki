<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/** "Erase what has been in the trash longer than `trash_keep_days`": a nightly task of AdminSchedule (end-users 13). */
final class PurgeTrash implements ScheduledTask
{
    public const TASK_ID = 'trash';

    public function taskId(): string
    {
        return self::TASK_ID;
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_TRASH');
    }
}

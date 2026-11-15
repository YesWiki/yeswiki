<?php

namespace YesWiki\Admin\Message;

use YesWiki\Admin\Service\ArchiveSchedule;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Take tonight's backup": the recurring task AdminSchedule declares while `archive[schedule]` is set.
 * Never retried: the next night's run is the retry.
 */
final class ScheduledArchive implements ScheduledTask, RetryPolicy
{
    public function taskId(): string
    {
        return ArchiveSchedule::TASK_ID;
    }

    public function label(): string
    {
        return _t('ADMIN_BACKUPS_SCHEDULED_TASK');
    }

    public function maxRetries(): int
    {
        return 0;
    }
}

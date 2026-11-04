<?php

namespace YesWiki\Identity\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the password recovery keys that have expired", a step of the wiki's housekeeping that is a recurring task of its own (IdentitySchedule).
 *
 * It only deletes what is past its time, so the default retry applies.
 */
final class PurgeRecoveryKeys implements ScheduledTask
{
    public function taskId(): string
    {
        return 'recovery-keys';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_RECOVERY_KEYS');
    }
}

<?php

namespace YesWiki\Identity\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the account activation keys that have expired", a step of the wiki's housekeeping that is a recurring task of its own (IdentitySchedule).
 *
 * It only deletes what is past its time, so the default retry applies.
 */
final class PurgeActivationKeys implements ScheduledTask
{
    public function taskId(): string
    {
        return 'activation-keys';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_ACTIVATION_KEYS');
    }
}

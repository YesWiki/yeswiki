<?php

namespace YesWiki\Identity\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Forget the anti-bot tokens that have expired and the refusal counters of past days", a step of the wiki's housekeeping that is a recurring task of its own (IdentitySchedule).
 *
 * It only deletes what is past its time, so the default retry applies.
 */
final class PurgeBotGuard implements ScheduledTask
{
    public function taskId(): string
    {
        return 'bot-guard';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_BOT_GUARD');
    }
}

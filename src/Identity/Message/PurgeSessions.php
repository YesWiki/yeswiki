<?php

namespace YesWiki\Identity\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/** "Forget the sessions that have expired", so that a quiet wiki, whose sessions PHP rarely collects, does not keep them (replicas 02). */
final class PurgeSessions implements ScheduledTask
{
    public function taskId(): string
    {
        return 'sessions';
    }

    public function label(): string
    {
        return _t('MAINTENANCE_TASK_SESSIONS');
    }
}

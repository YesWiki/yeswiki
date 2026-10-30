<?php

namespace YesWiki\Kernel\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * The queue's own housekeeping, run once a day: forget the failures nobody looked at in a month.
 *
 * Without it `failed` would only grow; the runs and the passes are the Journal's to prune
 * (PruneJournal). See PruneQueueHandler.
 */
final class PruneQueue implements ScheduledTask
{
    public function taskId(): string
    {
        return 'queue-prune';
    }

    public function label(): string
    {
        return _t('QUEUE_TASK_PRUNE');
    }
}

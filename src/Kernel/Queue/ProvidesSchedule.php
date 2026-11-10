<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Implemented by a service that has recurring work, found by the scheduler through the tag `yeswiki.schedule_provider`.
 * Each task is Symfony's RecurringMessage of a ScheduledTask: every() runs a period after the task's last dispatch, cron() at its
 * times in the wiki's timezone (QueueScheduleTicker). A provider that throws only leaves its own tasks out.
 */
interface ProvidesSchedule
{
    /** @return list<RecurringMessage> */
    public function recurringMessages(): array;
}

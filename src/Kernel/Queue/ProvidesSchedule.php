<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Implemented by a service that has recurring work, and nothing else: the scheduler finds it by this interface, tagged `yeswiki.schedule_provider`.
 *
 * A module enrols its tasks by implementing it; there is no central list to edit.
 */
interface ProvidesSchedule
{
    /** @return list<RecurringMessage> */
    public function recurringMessages(): array;
}

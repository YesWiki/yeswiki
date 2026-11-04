<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a service that has recurring work, found by the scheduler through the tag `yeswiki.schedule_provider`.
 * Each task is a RecurringMessageTimezoned, which says the timezone it runs in and checks it: the wiki's when it names none.
 * The Scheduler leaves out anything else it is given, and journals it at the tick.
 */
interface ProvidesSchedule
{
    /** @return list<RecurringMessageTimezoned> */
    public function recurringMessages(): array;
}

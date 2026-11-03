<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Scheduler\RecurringMessage;

/** Implemented by a service that has recurring work, found by the scheduler through the tag `yeswiki.schedule_provider`. */
interface ProvidesSchedule
{
    /** @return list<RecurringMessage> */
    public function recurringMessages(): array;
}

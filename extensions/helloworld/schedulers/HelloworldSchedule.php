<?php

namespace YesWiki\HelloWorld\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\HelloWorld\Message\Greet;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/**
 * How an extension declares a recurring task of its own: a service in schedulers/ that provides RecurringMessages of a ScheduledTask,
 * whose handler sits in messages/.
 */
class HelloworldSchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new Greet())];
    }
}

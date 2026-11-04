<?php

namespace YesWiki\Admin\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Admin\Message\PurgeRevisions;
use YesWiki\Admin\Message\RunMaintenanceHooks;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/** The housekeeping of the pages' history, and the hooks an extension hangs its own on. */
class AdminSchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRevisions()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new RunMaintenanceHooks()),
        ];
    }
}

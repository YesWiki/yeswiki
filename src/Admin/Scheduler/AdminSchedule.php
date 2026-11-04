<?php

namespace YesWiki\Admin\Scheduler;

use YesWiki\Admin\Message\PurgeRevisions;
use YesWiki\Admin\Message\RunMaintenanceHooks;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/** The housekeeping of the pages' history, and the hooks an extension hangs its own on. */
class AdminSchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [
            RecurringMessageTimezoned::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRevisions()),
            RecurringMessageTimezoned::every(KernelSchedule::HOUSEKEEPING_EVERY, new RunMaintenanceHooks()),
        ];
    }
}

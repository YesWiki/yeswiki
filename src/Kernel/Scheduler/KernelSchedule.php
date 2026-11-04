<?php

namespace YesWiki\Kernel\Scheduler;

use YesWiki\Kernel\Message\PruneJournal;
use YesWiki\Kernel\Message\PruneQueue;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;

/** The kernel's own recurring work: the queue's housekeeping, and the Journal's. */
class KernelSchedule implements ProvidesSchedule
{
    public const HOUSEKEEPING_EVERY = 1800;

    public function recurringMessages(): array
    {
        return [
            RecurringMessageTimezoned::every('24 hours', new PruneQueue()),
            RecurringMessageTimezoned::every(self::HOUSEKEEPING_EVERY, new PruneJournal()),
        ];
    }
}

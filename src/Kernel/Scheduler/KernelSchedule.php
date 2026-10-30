<?php

namespace YesWiki\Kernel\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Kernel\Message\PruneJournal;
use YesWiki\Kernel\Message\PruneQueue;
use YesWiki\Kernel\Queue\ProvidesSchedule;

/** The kernel's own recurring work: the queue's housekeeping, and the Journal's. */
class KernelSchedule implements ProvidesSchedule
{
    /**
     * Seconds between two runs of a housekeeping step: the interval of the sweep that these steps were run by, and that every module declaring one reuses.
     *
     * These tasks have no marker of their own to start from, so the Scheduler counts from its
     * checkpoint's birth; a step that came due while nobody consumed runs once at the next pass
     * (spec section 8).
     */
    public const HOUSEKEEPING_EVERY = 1800;

    public function recurringMessages(): array
    {
        return [
            RecurringMessage::every('24 hours', new PruneQueue()),
            RecurringMessage::every(self::HOUSEKEEPING_EVERY, new PruneJournal()),
        ];
    }
}

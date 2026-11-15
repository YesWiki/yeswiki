<?php

namespace YesWiki\Admin\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Admin\Message\PurgeRevisions;
use YesWiki\Admin\Message\ScheduledArchive;
use YesWiki\Admin\Service\ArchiveSchedule;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/** The housekeeping of the pages' history, and the scheduled backup while `archive[schedule]` is on. */
class AdminSchedule implements ProvidesSchedule
{
    public function __construct(
        private readonly ArchiveSchedule $archiveSchedule,
    ) {
    }

    public function recurringMessages(): array
    {
        $recurring = [
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRevisions()),
        ];
        $cron = $this->archiveSchedule->cron();
        if ($cron !== '') {
            $recurring[] = RecurringMessage::cron($cron, new ScheduledArchive());
        }

        return $recurring;
    }
}

<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Admin\Service\ArchiveSchedule;
use YesWiki\Admin\Service\ArchiveService;

/** Takes the scheduled backup; a backup that cannot be taken throws, so the jobs page and the backups screen say why. */
#[AsMessageHandler]
final class ScheduledArchiveHandler
{
    public function __construct(
        private readonly ArchiveService $archives,
        private readonly ArchiveSchedule $schedule,
    ) {
    }

    public function __invoke(ScheduledArchive $task): void
    {
        $output = '';
        $location = $this->archives->archive($output, $this->schedule->type() === 'full', true, [], [], null, '', null, true);
        if ($location === '') {
            throw new \RuntimeException(_t('ADMIN_BACKUPS_SCHEDULED_STOPPED', ['output' => is_string($output) ? trim(strip_tags($output)) : '']));
        }
    }
}

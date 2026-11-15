<?php

namespace YesWiki\Admin\Service;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;

/** Whether the scheduled backup ran: Degraded when its last run failed or it is more than a day late. */
class ArchiveHealthChecks implements ProvidesHealthChecks
{
    private const LATE_AFTER_SECONDS = 86400;

    public function __construct(
        private readonly ArchiveSchedule $schedule,
        private readonly QueueScheduleTicker $ticker,
        private readonly ClockInterface $clock,
    ) {
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('scheduled-backup')
                ->label(_t('ADMIN_BACKUPS_HEALTH'))
                ->degraded()
                ->says(_t('ADMIN_BACKUPS_HEALTH_SAYS'))
                ->linkedTo('admin/backups')
                ->runs(fn (): ?string => $this->problem()),
        ];
    }

    private function problem(): ?string
    {
        if ($this->schedule->cron() === '') {
            return null;
        }
        $task = $this->ticker->task(ArchiveSchedule::TASK_ID);
        if ($task === null) {
            return null;
        }
        if ($task['lastStatus'] !== null && $task['lastStatus'] !== 'handled') {
            return _t('ADMIN_BACKUPS_HEALTH_FAILED', ['error' => (string)($task['lastError'] ?? '')]);
        }
        $due = $task['nextDueAt'];
        if ($task['lastDispatchedAt'] !== null && $due !== null && $this->clock->now()->getTimestamp() - $due->getTimestamp() > self::LATE_AFTER_SECONDS) {
            return _t('ADMIN_BACKUPS_HEALTH_LATE');
        }

        return null;
    }
}

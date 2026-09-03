<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/**
 * Whether the housekeeping this wiki chose is actually happening (tickets 52 and 54).
 *
 * Only asked of a wiki set to `maintenance_trigger: cron`, because that is the setting that
 * introduces the failure: a crontab line that was never added, or a cron that stopped, leaves
 * recovery keys valid and the search index stale with nothing anywhere saying so. On the default
 * `request` there is nothing to check -- a wiki nobody visits has no housekeeping to miss.
 */
class MaintenanceHealthChecks implements ProvidesHealthChecks
{
    /** Past this, a daily crontab has missed two runs, which is no longer a slow night. */
    private const STALE_AFTER = 2 * 86400;

    private MaintenanceService $maintenance;

    public function __construct(MaintenanceService $maintenance)
    {
        $this->maintenance = $maintenance;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('maintenance-sweep')
                ->label(_t('HEALTH_MAINTENANCE'))
                ->says(_t('HEALTH_MAINTENANCE_SAYS'))
                ->actionableWhen(fn (): bool => $this->maintenance->trigger() === MaintenanceService::TRIGGER_CRON)
                ->runs(function (): ?string {
                    if ($this->maintenance->trigger() !== MaintenanceService::TRIGGER_CRON) {
                        return null;
                    }

                    $lastRun = $this->maintenance->lastRun();
                    if ($lastRun === 0) {
                        return _t('HEALTH_MAINTENANCE_NEVER');
                    }

                    $since = time() - $lastRun;

                    return $since < self::STALE_AFTER
                        ? null
                        : _t('HEALTH_MAINTENANCE_STALE', ['days' => (int)floor($since / 86400)]);
                }),
        ];
    }
}

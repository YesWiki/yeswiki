<?php

namespace YesWiki\Admin\Service;

use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/**
 * Whether anything this instance is allowed to update is out of date (ticket 52).
 *
 * Degraded, never broken, for two reasons that agree: a wiki running last month's release is not
 * broken, and an update check costs a round trip to the repository -- which the badge, running on
 * every page view, must not pay. `/admin/health` asks; the top bar does not.
 *
 * Gated on `mayUpgrade()` per package, so a farm instance is told about its own extensions and
 * not about a shared Program it is not the designated updater for (ADR-0007).
 */
class UpdateHealthChecks implements ProvidesHealthChecks
{
    private AutoUpdateService $updateService;

    public function __construct(AutoUpdateService $updateService)
    {
        $this->updateService = $updateService;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('core-update')
                ->label(_t('HEALTH_CORE_UPDATE'))
                ->degraded()
                ->says(_t('HEALTH_CORE_UPDATE_SAYS'))
                ->linkedTo('admin/updates')
                ->actionableWhen(fn (): bool => $this->updateService->mayUpgrade(PackageCore::CORE_NAME))
                ->runs(function (): ?string {
                    $core = $this->repository()?->getCorePackage();
                    if ($core === null || !$core->updateAvailable) {
                        return null;
                    }

                    return _t('HEALTH_UPDATE_AVAILABLE', ['name' => $core->name, 'version' => $core->release]);
                }),

            HealthCheck::named('package-updates')
                ->label(_t('HEALTH_PACKAGE_UPDATES'))
                ->degraded()
                ->says(_t('HEALTH_PACKAGE_UPDATES_SAYS'))
                ->linkedTo('admin/updates')
                ->runs(function (): ?string {
                    $repository = $this->repository();
                    if ($repository === null) {
                        return null;
                    }

                    $outdated = [];
                    foreach ([...$repository->getThemesPackages(), ...$repository->getToolsPackages()] as $package) {
                        if ($package->installed && $package->updateAvailable && $this->updateService->mayUpgrade($package->name)) {
                            $outdated[] = $package->name;
                        }
                    }

                    return $outdated === [] ? null : implode(', ', $outdated);
                }),
        ];
    }

    /** The repository, or null when it cannot be reached -- which is not this wiki being unhealthy. */
    private function repository(): ?Repository
    {
        try {
            if (!$this->updateService->initRepository()) {
                return null;
            }
        } catch (\Throwable $unreachable) {
            return null;
        }

        return $this->updateService->repository;
    }
}

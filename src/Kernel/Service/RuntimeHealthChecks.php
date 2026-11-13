<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/** What the wiki needs of the PHP it is running on and of its vendor/ folder (ticket 52). */
class RuntimeHealthChecks implements ProvidesHealthChecks
{
    private ComposerManifest $manifest;

    private DbService $dbService;

    public function __construct(ComposerManifest $manifest, DbService $dbService)
    {
        $this->manifest = $manifest;
        $this->dbService = $dbService;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('database-driver')
                ->label(_t('HEALTH_DATABASE_DRIVER'))
                ->says(_t('HEALTH_DATABASE_DRIVER_SAYS'))
                ->runs(function (): ?string {
                    $extension = 'pdo_' . $this->dbService->getDriver();

                    return extension_loaded($extension)
                        ? null
                        : _t('HEALTH_EXTENSION_MISSING', ['extension' => $extension]);
                }),

            HealthCheck::named('vendor-in-step')
                ->label(_t('HEALTH_VENDOR_IN_STEP'))
                ->says(_t('HEALTH_VENDOR_IN_STEP_SAYS'))
                ->runs(function (): ?string {
                    $packages = [];
                    foreach ($this->manifest->packagesOutOfStep() as $name => $installed) {
                        $packages[] = $installed === null
                            ? _t('HEALTH_VENDOR_PACKAGE_MISSING', ['package' => $name])
                            : _t('HEALTH_VENDOR_PACKAGE_STALE', ['package' => $name, 'version' => $installed]);
                    }

                    return $packages === [] ? null : implode(', ', $packages);
                }),

            HealthCheck::named('opcache')
                ->label(_t('HEALTH_OPCACHE'))
                ->degraded()
                ->says($this->reasonFor('zend-opcache'))
                ->runs(static function (): ?string {
                    return extension_loaded('Zend OPcache')
                        ? null
                        : _t('HEALTH_EXTENSION_MISSING', ['extension' => 'zend-opcache']);
                }),

            HealthCheck::named('settings-outside-requests')
                ->label(_t('HEALTH_SETTINGS_OUTSIDE_REQUESTS'))
                ->degraded()
                ->says(_t('HEALTH_SETTINGS_OUTSIDE_REQUESTS_SAYS'))
                ->runs(static function (): ?string {
                    $names = self::onlyInTheRequest();

                    return $names === [] ? null : _t('HEALTH_SETTINGS_ONLY_IN_THE_REQUEST', ['names' => implode(', ', $names)]);
                }),
        ];
    }

    /** @return list<string> the host's settings the web server passes this request (fastcgi_param, SetEnv) that neither private/.env nor the process environment holds, so no command or worker sees them (replicas 20) */
    public static function onlyInTheRequest(): array
    {
        $envFile = YesWikiLoader::envFileValues();
        $names = [];
        foreach (EnvironmentConfiguration::knownEnvNames() as $name => $key) {
            if (SettingKinds::isOperator($key) && getenv($name) !== false && getenv($name, true) === false && !array_key_exists($name, $envFile)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** composer.json's own sentence for an optional extension, which is the one the screen wants. */
    private function reasonFor(string $extension): string
    {
        return $this->manifest->suggestedExtensions()[$extension] ?? '';
    }
}

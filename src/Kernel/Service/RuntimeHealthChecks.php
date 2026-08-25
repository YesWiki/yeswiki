<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/**
 * What the wiki needs of the PHP it is running on (ticket 52).
 *
 * The three `ext-pdo_*` are `suggest` entries because only one of them matters per wiki -- which
 * one is what the configured driver says, and for that wiki it is not optional at all.
 */
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

            HealthCheck::named('opcache')
                ->label(_t('HEALTH_OPCACHE'))
                ->degraded()
                ->says($this->reasonFor('zend-opcache'))
                ->runs(static function (): ?string {
                    return extension_loaded('Zend OPcache')
                        ? null
                        : _t('HEALTH_EXTENSION_MISSING', ['extension' => 'zend-opcache']);
                }),
        ];
    }

    /** composer.json's own sentence for an optional extension, which is the one the screen wants. */
    private function reasonFor(string $extension): string
    {
        return $this->manifest->suggestedExtensions()[$extension] ?? '';
    }
}

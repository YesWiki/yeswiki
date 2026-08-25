<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\ComposerManifest;

/**
 * What the Program declares it cannot run without, checked against what this server has (ticket 52).
 *
 * Read from `composer.json` and nowhere else. The optional extensions are not here: each one is
 * declared by the module that loses something without it, which is the same rule and a different
 * subject (ADR-0026).
 */
class RequirementHealthChecks implements ProvidesHealthChecks
{
    private ComposerManifest $manifest;

    public function __construct(ComposerManifest $manifest)
    {
        $this->manifest = $manifest;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('php-version')
                ->label(_t('HEALTH_PHP_VERSION'))
                ->says(_t('HEALTH_PHP_VERSION_SAYS'))
                ->runs(function (): ?string {
                    $needed = $this->manifest->minimumPhpVersion();
                    if ($needed === '' || version_compare(PHP_VERSION, $needed, '>=')) {
                        return null;
                    }

                    return _t('HEALTH_PHP_VERSION_FAILED', ['needed' => $this->manifest->phpConstraint(), 'current' => PHP_VERSION]);
                }),

            HealthCheck::named('required-extensions')
                ->label(_t('HEALTH_REQUIRED_EXTENSIONS'))
                ->says(_t('HEALTH_REQUIRED_EXTENSIONS_SAYS'))
                ->runs(function (): ?string {
                    $missing = array_values(array_filter(
                        $this->manifest->requiredExtensions(),
                        static fn (string $extension): bool => !extension_loaded($extension)
                    ));

                    return $missing === [] ? null : implode(', ', $missing);
                }),
        ];
    }
}

<?php

namespace YesWiki\Search\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\ComposerManifest;

/** Search declares the extension it degrades without, and reads what it loses from composer.json (ADR-0026). */
class SearchHealthChecks implements ProvidesHealthChecks
{
    private ComposerManifest $manifest;

    public function __construct(ComposerManifest $manifest)
    {
        $this->manifest = $manifest;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('ext-intl')
                ->label(_t('HEALTH_EXT_INTL'))
                ->degraded()
                ->says($this->manifest->suggestedExtensions()['intl'] ?? '')
                ->runs(static function (): ?string {
                    return extension_loaded('intl')
                        ? null
                        : _t('HEALTH_EXTENSION_MISSING', ['extension' => 'intl']);
                }),
        ];
    }
}

<?php

namespace YesWiki\Import\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\ComposerManifest;

/** The IMAP importer's extension, declared by the module that owns the importer (ADR-0026). */
class ImportHealthChecks implements ProvidesHealthChecks
{
    private ComposerManifest $manifest;

    public function __construct(ComposerManifest $manifest)
    {
        $this->manifest = $manifest;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('ext-imap')
                ->label(_t('HEALTH_EXT_IMAP'))
                ->degraded()
                ->says($this->manifest->suggestedExtensions()['imap'] ?? '')
                ->linkedTo('admin/imports')
                ->runs(static function (): ?string {
                    return extension_loaded('imap')
                        ? null
                        : _t('HEALTH_EXTENSION_MISSING', ['extension' => 'imap']);
                }),
        ];
    }
}

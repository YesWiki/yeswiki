<?php

namespace YesWiki\Search\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\ComposerManifest;

/** Search declares the extension it degrades without, and reads what it loses from composer.json (ADR-0026). */
class SearchHealthChecks implements ProvidesHealthChecks
{
    private ComposerManifest $manifest;

    private SearchIndexSchema $schema;

    public function __construct(ComposerManifest $manifest, SearchIndexSchema $schema)
    {
        $this->manifest = $manifest;
        $this->schema = $schema;
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

            // Since ticket 62 the index is not only what search reads: it is where every keyword
            // question is answered from, so a wiki without one has no keywords at all rather than
            // no search. Worth saying out loud, because nothing else would say it.
            HealthCheck::named('search-index')
                ->label(_t('HEALTH_SEARCH_INDEX'))
                ->says(_t('HEALTH_SEARCH_INDEX_SAYS'))
                ->runs(function (): ?string {
                    return $this->schema->exists() ? null : _t('HEALTH_SEARCH_INDEX_MISSING');
                }),
        ];
    }
}

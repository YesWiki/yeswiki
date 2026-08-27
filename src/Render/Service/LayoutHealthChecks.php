<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\DbService;

/** Pages still naming a piece of chrome that is wiki-wide configuration now (ticket 30, re-derived under ticket 53). */
class LayoutHealthChecks implements ProvidesHealthChecks
{
    /** The Metadata keys that used to override a piece of chrome, and no longer name one. */
    private const RETIRED_OVERRIDES = ['PageTitre', 'PageMenuHaut', 'PageRapideHaut', 'PageMenu'];

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('pages-override-retired-chrome')
                ->label(_t('HEALTH_RETIRED_CHROME_OVERRIDES'))
                ->degraded()
                ->says(_t('HEALTH_RETIRED_CHROME_OVERRIDES_SAYS'))
                ->linkedTo('admin/layout')
                ->runs(fn (): ?string => $this->pagesOverridingRetiredChrome()),
        ];
    }

    private function pagesOverridingRetiredChrome(): ?string
    {
        $pages = $this->dbService->prefixTable('pages');
        $metadata = $this->dbService->quoteIdentifier('metadata');
        $asText = $this->dbService->jsonAsText('metadata');

        $clauses = implode(' OR ', array_map(
            static fn (string $role): string => "{$asText} LIKE '%{$role}%'",
            self::RETIRED_OVERRIDES
        ));

        $rows = $this->dbService->loadAll(
            "SELECT tag, {$metadata} FROM {$pages} WHERE latest = 'Y' AND ({$clauses})"
        );

        $affected = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['metadata'], true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach (self::RETIRED_OVERRIDES as $role) {
                if (!empty($decoded[$role]) && $decoded[$role] !== $role) {
                    $affected[] = "{$row['tag']} ({$role} = {$decoded[$role]})";
                }
            }
        }

        return $affected === [] ? null : implode(', ', $affected);
    }
}

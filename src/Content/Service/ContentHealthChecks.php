<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\DbService;

/** A tag names one Content (ADR-0001): no tag may carry two current rows, whatever wrote them (ticket 67). */
class ContentHealthChecks implements ProvidesHealthChecks
{
    public function __construct(private readonly DbService $dbService)
    {
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('tags-with-two-current-rows')
                ->label(_t('HEALTH_TAGS_WITH_TWO_CURRENT_ROWS'))
                ->says(_t('HEALTH_TAGS_WITH_TWO_CURRENT_ROWS_SAYS'))
                ->runs(fn (): ?string => $this->tagsWithTwoCurrentRows()),
        ];
    }

    /** The tags, twenty at most, that more than one current row claims, or null when there is none. */
    private function tagsWithTwoCurrentRows(): ?string
    {
        $rows = $this->dbService->loadAll(
            "SELECT tag FROM {$this->dbService->prefixTable('pages')} WHERE latest = 'Y' GROUP BY tag HAVING COUNT(*) > 1 ORDER BY tag LIMIT 20"
        );
        $tags = array_map(static fn (array $row): string => (string)$row['tag'], $rows);

        return $tags === [] ? null : implode(', ', $tags);
    }
}

<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

require_once __DIR__ . '/20260924130000_RetiredCoreActionsAreRemoved.php';

/** `{{rss}}`, `{{trail}}` and `{{listpagestag}}` left core, so their calls are taken out of every page revision. */
class RssTrailAndListpagestagAreRemoved extends YesWikiMigration
{
    public const RETIRED = ['rss', 'trail', 'listpagestag'];

    public function run()
    {
        [$removed, $pages] = (new RetiredCoreActionsAreRemoved())->remove($this->getService(DbService::class), self::RETIRED);

        $this->getService(SearchIndexer::class)->enqueue($pages);

        if ($removed !== []) {
            arsort($removed);
            $counts = implode(', ', array_map(fn (string $name, int $n): string => "{$name} ({$n})", array_keys($removed), $removed));
            $this->say(
                'rss, trail and listpagestag are no longer part of YesWiki; their calls were removed from ' . count($pages) . ' page(s), across all revisions: '
                . $counts . '. Pages: ' . implode(', ', array_slice($pages, 0, 40)) . (count($pages) > 40 ? '...' : '')
            );
        }
    }
}

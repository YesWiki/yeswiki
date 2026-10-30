<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * Ticket 18 / ADR-0015: create the search index.
 *
 * Filling it is ReindexingGoesThroughTheQueue's: this migration runs before the background
 * queue's table exists, so nothing could be sent to it here.
 */
class CreateSearchIndex extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SearchIndexSchema::class);
        $schema->create();

        $this->dropDeadFulltextIndexOnPages();

        $this->say('search index created; it is filled once the background queue exists');
    }

    /**
     * MySQL only: the other two drivers never had it, and `DROP INDEX` on a name that is not there is an error rather than a no-op, so it is guarded by an existence check rather than by try/catch.
     */
    private function dropDeadFulltextIndexOnPages(): void
    {
        if ($this->dbService->getDriver() !== 'mysql') {
            return;
        }

        $pages = trim($this->dbService->prefixTable('pages'));
        $existing = $this->dbService->loadAll("SHOW INDEX FROM `{$pages}` WHERE Key_name = 'tag'");
        if ($existing !== []) {
            $this->dbService->query("ALTER TABLE `{$pages}` DROP INDEX `tag`");
        }
    }
}

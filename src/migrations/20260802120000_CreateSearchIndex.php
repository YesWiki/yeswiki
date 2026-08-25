<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/** Ticket 18 / ADR-0015: create the search index, and queue every Content for it. */
class CreateSearchIndex extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SearchIndexSchema::class);
        $schema->create();

        $queued = $this->getService(SearchIndexer::class)->enqueueEverything();

        $this->dropDeadFulltextIndexOnPages();

        try {
            $this->getService(ConsoleService::class)->startConsoleAsync('search:reindex', ['--drain']);
        } catch (Throwable $unavailable) {
        }

        $this->say(
            "search index created; {$queued} Content(s) queued for indexing "
            . '(run `./yeswicli search:reindex --drain` to finish now)'
        );
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

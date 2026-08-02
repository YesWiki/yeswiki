<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * Ticket 18 / ADR-0015: create the search index, and queue every Content for it.
 *
 * ## What this deliberately does not do
 *
 * **It does not index anything.** Reading a million bodies, resolving a form and its
 * prepared fields per row and writing the result would be an hours-long job, and it would be
 * running inside the upgrade request. That request would time out, and a half-applied
 * migration on the upgrade path is exactly the failure ticket 25 documented.
 *
 * So the migration creates the schema and fills the *queue* -- one `INSERT ... SELECT`,
 * constant time whatever the wiki's size -- and the same drain that repairs a form cascade
 * does the work afterwards. One mechanism for first fill, cascade repair and disaster
 * recovery, and the only one that cannot time out.
 *
 * Until the drain finishes, search says so (`newtextsearch-building.twig`) rather than
 * returning nothing, and deliberately does **not** fall back to the old `body LIKE` query:
 * serving results known to be wrong is what this ticket exists to stop.
 *
 * ## The FULLTEXT index this drops
 *
 * `pages` carries `FULLTEXT KEY tag (tag, body)`, created for a search that no longer reads
 * `body` -- and which nothing in the codebase ever queried with MATCH ... AGAINST even
 * before this ticket. What it does cost is write amplification on a LONGTEXT column, on
 * every single page save. It goes.
 *
 * Idempotent: the DDL is `IF NOT EXISTS` throughout, and re-queueing a tag leaves exactly
 * one row.
 */
class CreateSearchIndex extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SearchIndexSchema::class);
        $schema->create();

        $queued = $this->getService(SearchIndexer::class)->enqueueEverything();

        $this->dropDeadFulltextIndexOnPages();

        // best effort: get the drain going now rather than at the next maintenance window.
        // The queue is already correct, so a host without proc_open loses nothing but time.
        try {
            $this->getService(ConsoleService::class)->startConsoleAsync('search:reindex', ['--drain']);
        } catch (Throwable $unavailable) {
            // no console available -- `./yeswicli search:reindex --drain` by hand, or wait
            // for the maintenance hook
        }

        // a migration has no per-row output channel (MigrationService returns one message
        // per migration), and how much is still queued is exactly what an operator needs to
        // know afterwards -- so it goes where the ticket-20 rename messages go
        $this->getService(AdministrativeLogService::class)->log(
            'migration',
            "search index created; {$queued} Content(s) queued for indexing "
            . '(run `./yeswicli search:reindex --drain` to finish now)'
        );
    }

    /**
     * MySQL only: the other two drivers never had it, and `DROP INDEX` on a name that is not
     * there is an error rather than a no-op, so it is guarded by an existence check rather
     * than by try/catch.
     */
    private function dropDeadFulltextIndexOnPages(): void
    {
        if ($this->dbService->getDriver() !== 'mysql') {
            return;
        }

        // trim(): prefixTable() returns the name wrapped in spaces so legacy callers can
        // concatenate it straight into SQL. That padding is invisible in an unquoted
        // `FROM x` and fatal inside backticks -- MySQL answers "Incorrect table name
        // ' yeswiki_pages '". Found only when this ran on MySQL for the first time: the
        // whole method is behind a driver check, so the SQLite suite never executes a line
        // of it. Same trap as SearchIndexSchema::table(), one release later.
        $pages = trim($this->dbService->prefixTable('pages'));
        $existing = $this->dbService->loadAll("SHOW INDEX FROM `{$pages}` WHERE Key_name = 'tag'");
        if ($existing !== []) {
            $this->dbService->query("ALTER TABLE `{$pages}` DROP INDEX `tag`");
        }
    }
}

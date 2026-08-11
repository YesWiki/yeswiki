<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * Ticket 35: the search index gains a keywords table, so `tags=` is an exact filter.
 *
 * Keywords were only ever appended into the index's free-text `text` column
 * (`SearchableTextExtractor`), which made `?q=Recette` match every page that merely *mentions*
 * the word. Tag navigation -- the link at the bottom of every page, previously the `listpages`
 * handler -- is an exact question and needs an exact answer, so keywords now also live in their own
 * table: one row per (Content, keyword), indexed on the keyword.
 *
 * ## What it does
 *
 * Creates the table (the DDL is `IF NOT EXISTS` in all three dialects, so this is safe on a wiki
 * that already has it) and queues every Content for reindexing, because the rows can only be
 * written by the indexer that knows how to read a body.
 *
 * **The table is empty until the queue drains.** That is deliberate rather than overlooked: filling
 * it here would mean decoding every body of every page inside a migration, and the wiki already has
 * a drain that does exactly that, incrementally, on its own housekeeping (`search:reindex` forces
 * it). Until then a tag link returns nothing rather than something wrong -- and
 * `SearchIndexSchema::exists()` is what the search surface already uses to say "the index is still
 * building" rather than "nothing matched".
 *
 * Idempotent: creating an existing table is a no-op, and re-queueing an already-queued Content is
 * an upsert on the queue's primary key.
 */
class SearchIndexGainsKeywords extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SearchIndexSchema::class);
        $dbService = $this->getService(DbService::class);
        $tables = $dbService->schema()->getTables();

        // Asked of the INDEX table by name, not through SearchIndexSchema::exists(). That method
        // now requires the keywords table too -- which is the very thing missing here -- so using
        // it would make this migration return early and create nothing, leaving the wiki's search
        // permanently reporting itself as "still building". The guard means "there is no index at
        // all", and on such a wiki 20260802120000_CreateSearchIndex builds the whole schema,
        // keywords table included.
        if (!in_array($schema->table(), $tables, true)) {
            return;
        }

        $keywords = $schema->keywordsTable();
        if (in_array($keywords, $tables, true)) {
            // already there: still requeue, because an index built before this migration has no
            // keyword rows even though the table exists (a partial upgrade, or a reordered run)
            $this->getService(SearchIndexer::class)->enqueueEverything();

            return;
        }

        // create() runs the whole search DDL; every statement in it is IF NOT EXISTS, so the
        // index and queue tables it also names are left exactly as they are
        $schema->create();

        $this->getService(SearchIndexer::class)->enqueueEverything();
    }
}

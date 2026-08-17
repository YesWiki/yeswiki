<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/** Ticket 35: the search index gains a keywords table, so `tags=` is an exact filter. */
class SearchIndexGainsKeywords extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SearchIndexSchema::class);
        $dbService = $this->getService(DbService::class);
        $tables = $dbService->schema()->getTables();

        if (!in_array($schema->table(), $tables, true)) {
            return;
        }

        $keywords = $schema->keywordsTable();
        if (in_array($keywords, $tables, true)) {
            $this->getService(SearchIndexer::class)->enqueueEverything();

            return;
        }

        $schema->create();

        $this->getService(SearchIndexer::class)->enqueueEverything();
    }
}

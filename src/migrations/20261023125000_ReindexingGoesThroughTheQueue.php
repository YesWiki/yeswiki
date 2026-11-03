<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Reindexing is one ReindexContent per Content on the background queue: `search_queue` goes, and every Content is queued once.
 */
class ReindexingGoesThroughTheQueue extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->query('DROP TABLE IF EXISTS' . $this->dbService->prefixTable('search_queue'));

        $queued = $this->getService(SearchIndexer::class)->enqueueEverything();
        $this->say("{$queued} Content(s) handed to the background queue for reindexing");
    }
}

<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 62: the second keyword index goes, and `search_keywords` is rebuilt from the bodies. */
class KeywordsHaveOneIndex extends YesWikiMigration
{
    /** The vocabulary the retired index used, as this migration's own literal (ticket 62). */
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    public function run(): void
    {
        $db = $this->getService(DbService::class);

        // Nothing is lost: `body.keywords` is the truth, and every keyword question is answered
        // from `search_keywords`, which the queue below rebuilds from those same bodies.
        $db->query(
            'DELETE FROM ' . $db->prefixTable('triples') . ' WHERE property = ?',
            [self::TAG_PROPERTY]
        );

        $queued = $this->getService(SearchIndexer::class)->enqueueEverything();
        $this->say("Keywords now have one index. {$queued} Content(s) queued so every keyword is rebuilt from its body.");
    }
}

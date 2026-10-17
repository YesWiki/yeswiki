<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/** Ticket 66: the search index keeps one row per language a Content is translated into, then reindexes everything once. */
class SearchIndexHoldsOneRowPerLanguage extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $schema = $this->getService(SearchIndexSchema::class);
        $table = $schema->table();

        if (!in_array($table, $db->schema()->getTables(), true)) {
            return;
        }

        if (!$db->schema()->columnExists(SearchIndexSchema::TABLE, 'lang')) {
            $type = $db->getDriver() === 'sqlite' ? 'TEXT' : 'VARCHAR(16)';
            $db->query("ALTER TABLE {$db->quoteIdentifier($table)} ADD COLUMN {$db->quoteIdentifier('lang')} {$type} NOT NULL DEFAULT ''");
        }

        try {
            $db->query("CREATE INDEX {$db->quoteIdentifier($table . '_idx_tag_lang')} ON {$db->quoteIdentifier($table)} (tag, lang)");
        } catch (Throwable $alreadyThere) {
        }

        $this->getService(SearchIndexer::class)->enqueueEverything();
    }
}

<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 27: a Content's type becomes a `pages` column, and `comment_on` becomes `parent`.
 *
 * `handler` -- present on every install, defaulted to 'page', written and read by nothing --
 * makes way for `type`, filled from the `TYPE_URI` triples that used to carry it. Those
 * triples are then deleted: they were costing two to four uncached queries per distinct tag
 * on every render, plus a `LEFT JOIN triples` in every query that needed a row's kind.
 *
 * **The date is deliberately impossible, and load-bearing** -- the same reason as
 * 00000000000001_DropBodyRColumn, which this must also follow. MigrationService runs whatever
 * is not yet recorded in plain `sort()` order of file names, and core reads `pages.type` and
 * `pages.parent` from the moment the ticket lands. Every later migration -- and several do
 * write page rows, if only through the administrative log -- would be running against a
 * schema the code no longer speaks. So the schema moves first, before any of them.
 *
 * Idempotent throughout: each column is checked before being added or dropped, so a wiki
 * that has already been here does nothing.
 */
class PageTypeAndParentColumns extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = trim($db->prefixTable('pages'));

        $this->addTypeColumn($db, $pages);
        $this->renameCommentOnToParent($db, $pages);
        $this->fillTypeFromTriples($db, $pages);
        $db->schema()->dropColumn('pages', 'handler');
        $this->deleteTypeTriples($db);
        $this->index($db, $pages, 'type');
        $this->index($db, $pages, 'parent');

        // `search_index.content_type` stored the raw triple value for anything this map did
        // not know, so upgraded wikis hold `liste` there, and every entry holds `fiche_bazar`
        // rather than `entry`. Both are now wrong, and a content-type filter reads them --
        // so the index is re-queued rather than left quietly stale. A no-op on a wiki whose
        // search index does not exist yet: 20260802120000_CreateSearchIndex queues
        // everything anyway when it builds it, later in this same run.
        $this->getService(SearchIndexer::class)->enqueueEverything();
    }

    private function addTypeColumn(DbService $db, string $pages): void
    {
        if ($db->schema()->columnExists('pages', 'type')) {
            return;
        }
        $default = PageType::DEFAULT;
        $definition = $db->getDriver() === 'sqlite'
            ? "TEXT NOT NULL DEFAULT '{$default}'"
            : "VARCHAR(30) NOT NULL DEFAULT '{$default}'";
        $db->query("ALTER TABLE {$pages} ADD COLUMN {$db->quoteIdentifier('type')} {$definition}");
    }

    /**
     * Add-copy-drop rather than `ALTER TABLE ... RENAME COLUMN`: that syntax needs MySQL
     * 8.0 / MariaDB 10.5.2 / SQLite 3.25, and a wiki old enough to still have `comment_on`
     * is exactly the wiki most likely to be on a server older than those.
     */
    private function renameCommentOnToParent(DbService $db, string $pages): void
    {
        if (!$db->schema()->columnExists('pages', 'comment_on')) {
            return;
        }
        $parent = $db->quoteIdentifier('parent');
        if (!$db->schema()->columnExists('pages', 'parent')) {
            $definition = $db->getDriver() === 'sqlite'
                ? "TEXT NOT NULL DEFAULT ''"
                : "VARCHAR(191) NOT NULL DEFAULT ''";
            $db->query("ALTER TABLE {$pages} ADD COLUMN {$parent} {$definition}");
        }
        $db->query("UPDATE {$pages} SET {$parent} = comment_on WHERE comment_on <> ''");
        $db->schema()->dropColumn('pages', 'comment_on');
    }

    private function fillTypeFromTriples(DbService $db, string $pages): void
    {
        $triples = trim($db->prefixTable('triples'));
        $property = $db->escape(TripleStore::TYPE_URI);
        $type = $db->quoteIdentifier('type');

        foreach (PageType::BY_LEGACY_TRIPLE as $tripleValue => $pageType) {
            // the empty key is the rows that never had a triple: they are pages, which is
            // already the column's default, so there is nothing to write
            if ($tripleValue === '') {
                continue;
            }
            $db->query(
                "UPDATE {$pages} SET {$type} = '{$db->escape($pageType)}'"
                . " WHERE tag IN (SELECT resource FROM {$triples}"
                . " WHERE property = '{$property}' AND value = '{$db->escape($tripleValue)}')"
            );
        }

        // a comment never carried a triple -- it was recognised by comment_on being set,
        // which is the one type this column states that the triples never did
        $db->query(
            "UPDATE {$pages} SET {$type} = '" . $db->escape(PageType::COMMENT) . "'"
            . " WHERE {$db->quoteIdentifier('parent')} <> ''"
        );
    }

    private function deleteTypeTriples(DbService $db): void
    {
        $triples = trim($db->prefixTable('triples'));
        $values = array_map(
            fn (string $v): string => "'{$db->escape($v)}'",
            array_values(array_filter(array_keys(PageType::BY_LEGACY_TRIPLE)))
        );

        // scoped to the six values the column now carries. A `TYPE_URI` triple whose value
        // is none of them is not a Content type at all -- `migration` is the one core still
        // writes -- and deleting the property wholesale would erase the already-run
        // migration list, re-running every migration in the repo against migrated data.
        $db->query(
            "DELETE FROM {$triples} WHERE property = '{$db->escape(TripleStore::TYPE_URI)}'"
            . ' AND value IN (' . implode(', ', $values) . ')'
        );
    }

    /** `CREATE INDEX IF NOT EXISTS` is not MySQL syntax, so a duplicate is caught instead. */
    private function index(DbService $db, string $pages, string $column): void
    {
        $name = str_replace(' ', '', $pages) . '_idx_' . $column;
        try {
            $db->query("CREATE INDEX {$db->quoteIdentifier($name)} ON {$pages} ({$db->quoteIdentifier($column)})");
        } catch (Throwable $alreadyThere) {
            // the index is what matters, not who created it
        }
    }
}

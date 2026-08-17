<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 27: a Content's type becomes a `pages` column, and `comment_on` becomes `parent`. */
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

    /** Add-copy-drop rather than `ALTER TABLE ... */
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
            if ($tripleValue === '') {
                continue;
            }
            $db->query(
                "UPDATE {$pages} SET {$type} = '{$db->escape($pageType)}'"
                . " WHERE tag IN (SELECT resource FROM {$triples}"
                . " WHERE property = '{$property}' AND value = '{$db->escape($tripleValue)}')"
            );
        }

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
        }
    }
}

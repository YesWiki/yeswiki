<?php

use YesWiki\Core\YesWikiMigration;

/** Composite indexes for the `latest = 'Y'` reads, which were full table scans. */
class LatestPagesAreIndexedTogether extends YesWikiMigration
{
    /** Index suffix => its columns. */
    private const INDEXES = [
        'idx_latest_parent_tag' => ['latest', 'parent', 'tag'],
        'idx_latest_time' => ['latest', 'time'],
    ];

    public function run()
    {
        $schema = $this->dbService->schema();
        $dialect = $this->dbService->dialect();
        $table = trim($this->dbService->prefixTable('pages'));

        foreach (self::INDEXES as $suffix => $columns) {
            $name = $this->dbService->getDriver() === 'mysql' ? $suffix : $table . '_' . $suffix;

            if ($schema->indexExists('pages', $name)) {
                continue;
            }

            $quoted = implode(', ', array_map(
                static fn (string $column) => $dialect->quoteIdentifier($column),
                $columns
            ));

            $this->dbService->query(
                'CREATE INDEX ' . $dialect->quoteIdentifier($name)
                . ' ON ' . $dialect->quoteIdentifier($table) . " ($quoted)"
            );
        }
    }
}

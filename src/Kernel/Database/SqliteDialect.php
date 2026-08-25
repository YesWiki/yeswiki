<?php

namespace YesWiki\Kernel\Database;

/** SQLite. */
class SqliteDialect implements SqlDialect
{
    public function driverName(): string
    {
        return 'sqlite';
    }

    public function now(): string
    {
        return "datetime('now')";
    }

    public function dateSubDays(int $days): string
    {
        return "datetime('now', '-" . intval($days) . " days')";
    }

    public function dateSubHours(int $hours): string
    {
        return "datetime('now', '-" . intval($hours) . " hours')";
    }

    /** TEXT, and it means it. */
    public function jsonColumnType(): string
    {
        return 'TEXT';
    }

    public function jsonAsText(string $column): string
    {
        return $column;
    }

    public function jsonExtract(string $column, string $path): string
    {
        $escaped = str_replace("'", "''", $path);

        return "(CASE WHEN json_valid($column) THEN json_extract($column, '$escaped') ELSE NULL END)";
    }

    /** The same SQL. */
    public function jsonExtractText(string $column, string $path): string
    {
        return $this->jsonExtract($column, $path);
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        return "GROUP_CONCAT(DISTINCT $column)";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function collateClause(): string
    {
        return ' COLLATE NOCASE';
    }

    public function regexpOperator(bool $not = false): string
    {
        return ($not ? 'NOT ' : '') . 'REGEXP';
    }

    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        if ($not) {
            return "(($haystack NOT LIKE $needle || ',%') AND " .
                   "($haystack NOT LIKE '%,' || $needle || ',%') AND " .
                   "($haystack NOT LIKE '%,' || $needle) AND " .
                   "($haystack != $needle))";
        }

        return "(($haystack LIKE $needle || ',%') OR " .
               "($haystack LIKE '%,' || $needle || ',%') OR " .
               "($haystack LIKE '%,' || $needle) OR " .
               "($haystack = $needle))";
    }

    public function castToInteger(string $expression): string
    {
        return "CAST({$expression} AS INTEGER)";
    }

    public function dumpPreamble(): array
    {
        return ['BEGIN TRANSACTION'];
    }

    public function dumpEpilogue(): array
    {
        return ['COMMIT'];
    }

    public function foreignKeyChecks(bool $enabled): ?string
    {
        return 'PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF');
    }

    public function supportsDump(): bool
    {
        return true;
    }

    public function journalDdl(string $table): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                \"id\" INTEGER PRIMARY KEY AUTOINCREMENT,
                \"at\" TEXT NOT NULL,
                \"last_at\" TEXT NOT NULL,
                \"repeat\" INTEGER NOT NULL DEFAULT 1,
                \"channel\" TEXT NOT NULL,
                \"level\" TEXT NOT NULL,
                \"actor\" TEXT NOT NULL DEFAULT '',
                \"action\" TEXT NOT NULL,
                \"target\" TEXT NOT NULL DEFAULT '',
                \"fingerprint\" TEXT DEFAULT NULL,
                \"day\" TEXT DEFAULT NULL,
                \"context\" TEXT DEFAULT NULL
            )",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_at\" ON \"{$table}\" (\"at\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_channel_at\" ON \"{$table}\" (\"channel\", \"at\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_actor_at\" ON \"{$table}\" (\"actor\", \"at\")",
            "CREATE UNIQUE INDEX IF NOT EXISTS \"{$table}_uniq_fingerprint_day\" ON \"{$table}\" (\"fingerprint\", \"day\")",
        ];
    }

    public function journalDropDdl(string $table): array
    {
        return ["DROP TABLE IF EXISTS \"{$table}\""];
    }

    public function upsert(string $table, array $values, array $conflictColumns, array $assignments): string
    {
        return SqlUpsert::onConflict($this, $table, $values, $conflictColumns, $assignments);
    }

    /** An ordinary table plus an FTS5 **external-content** table over it, kept in sync by triggers. */
    public function searchIndexDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                \"id\" INTEGER PRIMARY KEY AUTOINCREMENT,
                \"tag\" TEXT NOT NULL,
                \"acl\" TEXT NOT NULL,
                \"acl_hash\" TEXT NOT NULL,
                \"page_read_acl\" TEXT NOT NULL,
                \"owner\" TEXT NOT NULL DEFAULT '',
                \"content_type\" TEXT NOT NULL DEFAULT '',
                \"form_id\" TEXT NOT NULL DEFAULT '',
                \"title\" TEXT NOT NULL,
                \"text\" TEXT NOT NULL,
                \"updated_at\" TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_tag\" ON \"{$table}\" (\"tag\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_acl_hash\" ON \"{$table}\" (\"acl_hash\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_content_type\" ON \"{$table}\" (\"content_type\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_form_id\" ON \"{$table}\" (\"form_id\")",
            "CREATE VIRTUAL TABLE IF NOT EXISTS \"{$table}_fts\" USING fts5(
                \"title\", \"text\",
                content=\"{$table}\",
                content_rowid=\"id\",
                tokenize='unicode61 remove_diacritics 2'
            )",
            "CREATE TRIGGER IF NOT EXISTS \"{$table}_ai\" AFTER INSERT ON \"{$table}\" BEGIN
                INSERT INTO \"{$table}_fts\"(rowid, \"title\", \"text\") VALUES (new.\"id\", new.\"title\", new.\"text\");
            END",
            "CREATE TRIGGER IF NOT EXISTS \"{$table}_ad\" AFTER DELETE ON \"{$table}\" BEGIN
                INSERT INTO \"{$table}_fts\"(\"{$table}_fts\", rowid, \"title\", \"text\")
                VALUES ('delete', old.\"id\", old.\"title\", old.\"text\");
            END",
            "CREATE TRIGGER IF NOT EXISTS \"{$table}_au\" AFTER UPDATE ON \"{$table}\" BEGIN
                INSERT INTO \"{$table}_fts\"(\"{$table}_fts\", rowid, \"title\", \"text\")
                VALUES ('delete', old.\"id\", old.\"title\", old.\"text\");
                INSERT INTO \"{$table}_fts\"(rowid, \"title\", \"text\") VALUES (new.\"id\", new.\"title\", new.\"text\");
            END",
            "CREATE TABLE IF NOT EXISTS \"{$queueTable}\" (
                \"tag\" TEXT PRIMARY KEY,
                \"queued_at\" TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS \"{$keywordsTable}\" (
                \"tag\" TEXT NOT NULL,
                \"keyword\" TEXT NOT NULL,
                PRIMARY KEY (\"tag\", \"keyword\")
            )",
            "CREATE INDEX IF NOT EXISTS \"{$keywordsTable}_idx_keyword\" ON \"{$keywordsTable}\" (\"keyword\")",
        ];
    }

    public function searchIndexDropDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "DROP TABLE IF EXISTS \"{$keywordsTable}\"",
            "DROP TABLE IF EXISTS \"{$queueTable}\"",
            "DROP TRIGGER IF EXISTS \"{$table}_ai\"",
            "DROP TRIGGER IF EXISTS \"{$table}_ad\"",
            "DROP TRIGGER IF EXISTS \"{$table}_au\"",
            "DROP TABLE IF EXISTS \"{$table}_fts\"",
            "DROP TABLE IF EXISTS \"{$table}\"",
        ];
    }

    public function searchMatchExpression(string $table, array $termGroups): string
    {
        $groups = array_map(
            static fn (array $alternatives): string => '(' . implode(' OR ', array_map(
                static fn (string $term): string => '"' . $term . '"*',
                $alternatives
            )) . ')',
            $termGroups
        );

        $query = implode(' AND ', $groups);

        return "\"{$table}\".\"id\" IN (SELECT rowid FROM \"{$table}_fts\" WHERE \"{$table}_fts\" MATCH '{$query}')";
    }

    public function renameTables(array $renames): array
    {
        $statements = [];
        foreach ($renames as $from => $to) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($from) . ' RENAME TO ' . $this->quoteIdentifier($to);
        }

        return $statements;
    }
}

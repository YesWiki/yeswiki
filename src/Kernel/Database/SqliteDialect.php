<?php

namespace YesWiki\Kernel\Database;

/**
 * SQLite. The driver that exposed the case-sensitivity and trailing-space bugs MySQL's
 * ci-collations had been masking (see the ectoplasme branch notes).
 */
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

    public function jsonExtract(string $column, string $path): string
    {
        // json_extract() errors on non-JSON input, so guard with json_valid()
        return "(CASE WHEN json_valid($column) THEN json_extract($column, '$path') ELSE NULL END)";
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        // GROUP_CONCAT supports neither ORDER BY nor DISTINCT combined with an explicit
        // separator; its default separator is already ','
        return "GROUP_CONCAT(DISTINCT $column)";
    }

    public function quoteIdentifier(string $identifier): string
    {
        // Doubled, which is how SQL spells an escaped double quote. See MySqlDialect for why
        // this is worth doing even though every caller passes a literal column name.
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function collateClause(): string
    {
        return ' COLLATE NOCASE';
    }

    public function regexpOperator(bool $not = false): string
    {
        // REGEXP is registered as a user function by DbService::initDriverSpecific()
        return ($not ? 'NOT ' : '') . 'REGEXP';
    }

    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        // No FIND_IN_SET: match the value at the start, middle or end of the list, or alone
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
        // no session SET statements: SQLite rejects them outright, which is one of the
        // reasons the MySQL-shaped dump could never be replayed here
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

    /**
     * An ordinary table plus an FTS5 **external-content** table over it, kept in sync by
     * triggers.
     *
     * The obvious shape -- make the index table itself the FTS5 virtual table -- is wrong
     * here. An FTS5 table has no secondary indexes, so `DELETE ... WHERE tag = ?`, which
     * every single-Content reindex performs, would scan the whole index. With external
     * content the base table keeps a real `idx_tag`, and FTS5 stores only the terms.
     *
     * `remove_diacritics 2` needs SQLite 3.27+ (2019). No stemmer is configured: FTS5 only
     * ships a Porter stemmer for English, and ADR-0015 keeps the three dialects comparable
     * rather than stemming on some and not others.
     */
    public function searchIndexDdl(string $table, string $queueTable): array
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
        ];
    }

    public function searchIndexDropDdl(string $table, string $queueTable): array
    {
        return [
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
        // Each term is quoted before the prefix `*`. Sanitisation already rules out a quote
        // character, so this cannot escape the string -- what it buys is that FTS5 stops
        // reading a term as a query operator: a search for `OR` or `NEAR` would otherwise
        // be parsed as one, and FTS5 raises rather than returning nothing.
        $groups = array_map(
            static fn (array $alternatives): string => '(' . implode(' OR ', array_map(
                static fn (string $term): string => '"' . $term . '"*',
                $alternatives
            )) . ')',
            $termGroups
        );
        // AND is explicit: FTS5's implicit AND joins *phrases*, and juxtaposing two
        // parenthesised expressions is a syntax error rather than a conjunction
        $query = implode(' AND ', $groups);

        // a subquery rather than a join, so the expression stays a WHERE fragment like the
        // other two dialects' and callers need no dialect-specific FROM clause
        return "\"{$table}\".\"id\" IN (SELECT rowid FROM \"{$table}_fts\" WHERE \"{$table}_fts\" MATCH '{$query}')";
    }
}

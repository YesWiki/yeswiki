<?php

namespace YesWiki\Kernel\Database;

/**
 * PostgreSQL. Offered by the installer (InstallationController checks for pdo_pgsql) but
 * not exercised by the test suite -- treat these fragments as unverified.
 */
class PostgreSqlDialect implements SqlDialect
{
    public function driverName(): string
    {
        return 'pgsql';
    }

    public function now(): string
    {
        return 'NOW()';
    }

    public function dateSubDays(int $days): string
    {
        return "NOW() - INTERVAL '" . intval($days) . " days'";
    }

    public function dateSubHours(int $hours): string
    {
        return "NOW() - INTERVAL '" . intval($hours) . " hours'";
    }

    public function jsonExtract(string $column, string $path): string
    {
        // ->> extracts as text; '$.field' reduces to 'field'
        $field = preg_replace('/^\$\./', '', $path);

        return "(CASE WHEN $column ~ '^\\s*\\{' THEN ($column::jsonb ->> '$field') ELSE NULL END)";
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        $orderBy = $orderBy ?? $column;

        return "STRING_AGG(DISTINCT $column, ',' ORDER BY $orderBy)";
    }

    public function quoteIdentifier(string $identifier): string
    {
        // Doubled, which is how SQL spells an escaped double quote. See MySqlDialect for why
        // this is worth doing even though every caller passes a literal column name.
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function collateClause(): string
    {
        // no COLLATE for case-insensitivity here; ILIKE is the idiom
        return '';
    }

    public function regexpOperator(bool $not = false): string
    {
        return $not ? '!~' : '~';
    }

    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        return $not
            ? "($needle != ALL(string_to_array($haystack, ',')))"
            : "($needle = ANY(string_to_array($haystack, ',')))";
    }

    public function castToInteger(string $expression): string
    {
        // NULLIF guards the empty string, which `::integer` rejects rather than treating as
        // zero -- a comment tag that is not `comment<digits>` would otherwise error the query
        return "COALESCE(NULLIF(regexp_replace({$expression}, '\\D', '', 'g'), '')::bigint, 0)";
    }

    public function dumpPreamble(): array
    {
        return ['BEGIN'];
    }

    public function dumpEpilogue(): array
    {
        return ['COMMIT'];
    }

    public function foreignKeyChecks(bool $enabled): ?string
    {
        // no session-level switch: PostgreSQL needs per-constraint ALTER TABLE, and the
        // restore drops tables in one pass anyway
        return null;
    }

    /**
     * PostgreSQL has no `SHOW CREATE TABLE`, so DbService::getTableSchema() cannot produce the
     * structure half of a dump. Backing up here used to *silently* emit INSERTs with no
     * CREATE TABLE -- an archive that looks fine and restores into nothing. Refused instead
     * (ticket 17).
     */
    public function supportsDump(): bool
    {
        return false;
    }

    /**
     * A generated `tsvector` with a GIN index over it.
     *
     * The configuration is `'simple'`, not `'french'` or `'english'`: PostgreSQL is the only
     * one of the three dialects that can stem, so stemming here would make the same query
     * return different result sets per driver -- and the test suite runs SQLite. ADR-0015
     * defers stemming to a tuning ticket for that reason. `'simple'` is also immutable, which
     * a generated column requires; a per-Content language would need a trigger instead.
     */
    public function searchIndexDdl(string $table, string $queueTable): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                \"id\" SERIAL PRIMARY KEY,
                \"tag\" VARCHAR(191) NOT NULL,
                \"acl\" TEXT NOT NULL,
                \"acl_hash\" CHAR(32) NOT NULL,
                \"page_read_acl\" TEXT NOT NULL,
                \"owner\" VARCHAR(191) NOT NULL DEFAULT '',
                \"content_type\" VARCHAR(30) NOT NULL DEFAULT '',
                \"form_id\" VARCHAR(191) NOT NULL DEFAULT '',
                \"title\" TEXT NOT NULL,
                \"text\" TEXT NOT NULL,
                \"updated_at\" TIMESTAMP NOT NULL,
                \"search_vector\" tsvector GENERATED ALWAYS AS (
                    to_tsvector('simple', coalesce(\"title\", '') || ' ' || coalesce(\"text\", ''))
                ) STORED
            )",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_tag\" ON \"{$table}\" (\"tag\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_acl_hash\" ON \"{$table}\" (\"acl_hash\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_content_type\" ON \"{$table}\" (\"content_type\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_form_id\" ON \"{$table}\" (\"form_id\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_ft_text\" ON \"{$table}\" USING GIN (\"search_vector\")",
            "CREATE TABLE IF NOT EXISTS \"{$queueTable}\" (
                \"tag\" VARCHAR(191) PRIMARY KEY,
                \"queued_at\" TIMESTAMP NOT NULL
            )",
        ];
    }

    public function searchIndexDropDdl(string $table, string $queueTable): array
    {
        return ["DROP TABLE IF EXISTS \"{$table}\"", "DROP TABLE IF EXISTS \"{$queueTable}\""];
    }

    public function searchMatchExpression(string $table, array $termGroups): string
    {
        // `:*` is to_tsquery's prefix operator, `|` its OR, `&` its AND
        $groups = array_map(
            static fn (array $alternatives): string => '(' . implode(' | ', array_map(
                static fn (string $term): string => $term . ':*',
                $alternatives
            )) . ')',
            $termGroups
        );

        return "\"{$table}\".\"search_vector\" @@ to_tsquery('simple', '" . implode(' & ', $groups) . "')";
    }
}

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
        return '"' . $identifier . '"';
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
}

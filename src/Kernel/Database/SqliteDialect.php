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
        return '"' . $identifier . '"';
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
}

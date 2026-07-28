<?php

namespace YesWiki\Kernel\Database;

/**
 * MySQL / MariaDB. Also the behaviour DbService's switch statements used as their
 * `default:` branch, so this is what an unrecognised driver historically got.
 */
class MySqlDialect implements SqlDialect
{
    public function driverName(): string
    {
        return 'mysql';
    }

    public function now(): string
    {
        return 'NOW()';
    }

    public function dateSubDays(int $days): string
    {
        return 'DATE_SUB(NOW(), INTERVAL ' . intval($days) . ' DAY)';
    }

    public function dateSubHours(int $hours): string
    {
        return 'DATE_SUB(NOW(), INTERVAL ' . intval($hours) . ' HOUR)';
    }

    public function jsonExtract(string $column, string $path): string
    {
        // JSON_UNQUOTE so strings come back unquoted
        return "JSON_UNQUOTE(JSON_EXTRACT($column, '$path'))";
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        $orderBy = $orderBy ?? $column;

        return "GROUP_CONCAT(DISTINCT $column ORDER BY $orderBy SEPARATOR ',')";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . $identifier . '`';
    }

    public function collateClause(): string
    {
        return ' COLLATE utf8mb4_unicode_ci';
    }

    public function regexpOperator(bool $not = false): string
    {
        return ($not ? 'NOT ' : '') . 'REGEXP';
    }

    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        return ($not ? 'NOT ' : '') . "FIND_IN_SET($needle, $haystack)";
    }
}

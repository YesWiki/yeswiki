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

    public function dumpPreamble(): array
    {
        return [
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"',
            'SET AUTOCOMMIT = 0',
            'START TRANSACTION',
            '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */',
            '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */',
            '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */',
            '/*!40101 SET NAMES utf8mb4 */',
            '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */',
            "/*!40103 SET TIME_ZONE='+00:00' */",
        ];
    }

    public function dumpEpilogue(): array
    {
        return ['COMMIT'];
    }

    public function foreignKeyChecks(bool $enabled): ?string
    {
        return 'SET FOREIGN_KEY_CHECKS=' . ($enabled ? '1' : '0');
    }

    public function supportsDump(): bool
    {
        return true;
    }
}

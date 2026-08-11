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
        // JSON_UNQUOTE so strings come back unquoted. The path is escaped here rather than by
        // the caller: it can carry a form field name, and the dialect is what knows the path
        // ends up inside a single-quoted literal (see SqlDialect::jsonExtract()).
        $escaped = str_replace("'", "''", $path);

        return "JSON_UNQUOTE(JSON_EXTRACT($column, '$escaped'))";
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        $orderBy = $orderBy ?? $column;

        return "GROUP_CONCAT(DISTINCT $column ORDER BY $orderBy SEPARATOR ',')";
    }

    public function quoteIdentifier(string $identifier): string
    {
        // A backtick inside the name is doubled, which is how MySQL spells an escaped one. It
        // costs nothing for the reserved words this is actually called with (`time`, `type`,
        // `user`) and stops the method from being a way to break out of the quoting it looks
        // like it guarantees -- it used to wrap the name and inspect nothing.
        return '`' . str_replace('`', '``', $identifier) . '`';
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

    public function castToInteger(string $expression): string
    {
        // `AS INTEGER` is not MySQL syntax; SIGNED is the equivalent
        return "CAST({$expression} AS SIGNED)";
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

    /**
     * InnoDB FULLTEXT. Two limits are inherited and cannot be worked around from here, both
     * recorded in ADR-0015: `innodb_ft_min_token_size` (3 by default, a server variable
     * needing a restart) drops one- and two-character words, and the built-in stopword list
     * is English and cannot be disabled per session.
     */
    public function searchIndexDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tag` VARCHAR(191) NOT NULL,
                `acl` TEXT NOT NULL,
                `acl_hash` CHAR(32) NOT NULL,
                `page_read_acl` TEXT NOT NULL,
                `owner` VARCHAR(191) NOT NULL DEFAULT '',
                `content_type` VARCHAR(30) NOT NULL DEFAULT '',
                `form_id` VARCHAR(191) NOT NULL DEFAULT '',
                `title` TEXT NOT NULL,
                `text` LONGTEXT NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_tag` (`tag`),
                KEY `idx_acl_hash` (`acl_hash`),
                KEY `idx_content_type` (`content_type`),
                KEY `idx_form_id` (`form_id`),
                FULLTEXT KEY `ft_text` (`title`, `text`)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS `{$queueTable}` (
                `tag` VARCHAR(191) NOT NULL,
                `queued_at` DATETIME NOT NULL,
                PRIMARY KEY (`tag`)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB",
            // One row per (Content, keyword): an inverted index, so `tags=` is an indexed
            // equality lookup rather than a LIKE over a delimited column. ADR-0015 rejected
            // LIKE-based search for the scale this is built for, and a tag filter is no
            // different -- `%,cooking,%` cannot use an index at any size.
            "CREATE TABLE IF NOT EXISTS `{$keywordsTable}` (
                `tag` VARCHAR(191) NOT NULL,
                `keyword` VARCHAR(191) NOT NULL,
                PRIMARY KEY (`tag`, `keyword`),
                KEY `idx_keyword` (`keyword`)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB",
        ];
    }

    public function searchIndexDropDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "DROP TABLE IF EXISTS `{$table}`",
            "DROP TABLE IF EXISTS `{$queueTable}`",
            "DROP TABLE IF EXISTS `{$keywordsTable}`",
        ];
    }

    public function searchMatchExpression(string $table, array $termGroups): string
    {
        // boolean mode: `+` makes a group required, `*` makes a term a prefix, and terms
        // inside `( )` are alternatives. All three are safe to append because the caller has
        // stripped everything but letters, digits and underscores -- see
        // SqlDialect::searchMatchExpression().
        $groups = array_map(
            static fn (array $alternatives): string => '+(' . implode(' ', array_map(
                static fn (string $term): string => $term . '*',
                $alternatives
            )) . ')',
            $termGroups
        );

        return "MATCH(`{$table}`.`title`, `{$table}`.`text`) AGAINST ('" . implode(' ', $groups) . "' IN BOOLEAN MODE)";
    }
}

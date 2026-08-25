<?php

namespace YesWiki\Kernel\Database;

/** MySQL / MariaDB. */
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

    public function jsonColumnType(): string
    {
        return 'JSON';
    }

    public function jsonAsText(string $column): string
    {
        return $column;
    }

    public function jsonExtract(string $column, string $path): string
    {
        $escaped = str_replace("'", "''", $path);

        return "JSON_UNQUOTE(JSON_EXTRACT($column, '$escaped'))";
    }

    /** Identical SQL, and deliberately not delegated away. */
    public function jsonExtractText(string $column, string $path): string
    {
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

    public function journalDdl(string $table): array
    {
        $json = $this->jsonColumnType();

        return [
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `at` DATETIME NOT NULL,
                `last_at` DATETIME NOT NULL,
                `repeat` INT UNSIGNED NOT NULL DEFAULT 1,
                `channel` VARCHAR(16) NOT NULL,
                `level` VARCHAR(16) NOT NULL,
                `actor` VARCHAR(191) NOT NULL DEFAULT '',
                `action` VARCHAR(191) NOT NULL,
                `target` VARCHAR(191) NOT NULL DEFAULT '',
                `fingerprint` CHAR(32) DEFAULT NULL,
                `day` CHAR(10) DEFAULT NULL,
                `context` {$json} DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_at` (`at`),
                KEY `idx_channel_at` (`channel`, `at`),
                KEY `idx_actor_at` (`actor`, `at`),
                UNIQUE KEY `uniq_fingerprint_day` (`fingerprint`, `day`)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB",
        ];
    }

    public function journalDropDdl(string $table): array
    {
        return ["DROP TABLE IF EXISTS `{$table}`"];
    }

    /** `VALUES()` rather than the 8.0.20 row alias, which MariaDB does not have. */
    public function upsert(string $table, array $values, array $conflictColumns, array $assignments): string
    {
        $sets = [];
        foreach ($assignments as $column => $expression) {
            $sets[] = $this->quoteIdentifier($column) . ' = ' . preg_replace_callback(
                '/:new\.([a-z_]+)/i',
                fn (array $m): string => 'VALUES(' . $this->quoteIdentifier($m[1]) . ')',
                $expression
            );
        }

        return 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($values))) . ')'
            . ' VALUES (' . implode(', ', array_values($values)) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
    }

    /** InnoDB FULLTEXT. */
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
        $groups = array_map(
            static fn (array $alternatives): string => '+(' . implode(' ', array_map(
                static fn (string $term): string => $term . '*',
                $alternatives
            )) . ')',
            $termGroups
        );

        return "MATCH(`{$table}`.`title`, `{$table}`.`text`) AGAINST ('" . implode(' ', $groups) . "' IN BOOLEAN MODE)";
    }

    public function renameTables(array $renames): array
    {
        if ($renames === []) {
            return [];
        }

        $pairs = [];
        foreach ($renames as $from => $to) {
            $pairs[] = $this->quoteIdentifier($from) . ' TO ' . $this->quoteIdentifier($to);
        }

        return ['RENAME TABLE ' . implode(', ', $pairs)];
    }
}

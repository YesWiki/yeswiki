<?php

namespace YesWiki\Core\Service;

use YesWiki\Core\Entity\DumpRewrite;

/**
 * Makes the SQL dump of an archive fit the wiki restoring it: its tables and its links.
 */
class DumpRewriter
{
    public const INFO_FILENAME = 'restore.json';
    public const CORE_TABLES = ['acls', 'links', 'nature', 'pages', 'referrers', 'triples', 'users'];
    public const UNKNOWN_SOURCE_PREFIX = 1;
    public const INVALID_TARGET_PREFIX = 2;

    /**
     * @param array<string,mixed> $info contents of the restore info file, empty for an older archive
     *
     * @throws \Exception when the dump names no known table, or the target prefix is unusable
     */
    public static function prepare(
        string $sqlContent,
        array $info,
        string $targetPrefix,
        string $targetBaseUrl,
        bool $rewriteUrls = true
    ): DumpRewrite {
        $sourcePrefix = self::detectPrefix($sqlContent);
        if ($sourcePrefix === '' && is_string($info['table_prefix'] ?? null)) {
            $sourcePrefix = $info['table_prefix'];
        }
        if ($sourcePrefix === '') {
            throw new \Exception('Cannot tell which table prefix this archive was taken with: it names no YesWiki table.', self::UNKNOWN_SOURCE_PREFIX);
        }

        $targetPrefix = trim($targetPrefix);
        if (!self::isValidPrefix($targetPrefix)) {
            throw new \Exception("'$targetPrefix' is not a usable table prefix.", self::INVALID_TARGET_PREFIX);
        }

        $sourceBaseUrl = is_string($info['base_url'] ?? null) ? $info['base_url'] : '';
        $substitutions = $rewriteUrls ? self::substitutions($sourceBaseUrl, $targetBaseUrl) : [];

        return new DumpRewrite(
            self::rewriteUrls(self::rewriteTables($sqlContent, $sourcePrefix, $targetPrefix), $substitutions),
            $sourcePrefix,
            $targetPrefix,
            empty($substitutions) ? '' : self::root($sourceBaseUrl),
            empty($substitutions) ? '' : self::root($targetBaseUrl),
        );
    }

    /**
     * A prefix reaches the database as part of an identifier, so it stays alphanumeric.
     */
    public static function isValidPrefix(string $prefix): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $prefix) === 1;
    }

    /**
     * Every table the dump creates, in the order it creates them.
     *
     * @return string[]
     */
    public static function tables(string $sqlContent): array
    {
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $sqlContent, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The prefix the dump was taken with, empty when no core table names it.
     *
     * A backup sweeps in every table starting with its prefix, so a wiki sharing the database
     * under a longer prefix is in the dump too. The prefix looked for is the one they all share.
     */
    public static function detectPrefix(string $sqlContent): string
    {
        $tables = self::tables($sqlContent);
        $candidates = [];
        foreach ($tables as $table) {
            foreach (self::CORE_TABLES as $coreTable) {
                if (strlen($table) > strlen($coreTable) && str_ends_with($table, $coreTable)) {
                    $candidates[substr($table, 0, -strlen($coreTable))] = true;
                }
            }
        }
        $candidates = array_keys($candidates);
        usort($candidates, function ($a, $b) {
            return strlen($a) <=> strlen($b);
        });

        foreach ($candidates as $candidate) {
            $sharedByAll = true;
            foreach ($tables as $table) {
                if (!str_starts_with($table, $candidate)) {
                    $sharedByAll = false;
                    break;
                }
            }
            if ($sharedByAll) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array<string,string> the new name of each table the dump creates, keyed by the old one
     */
    public static function renames(string $sqlContent, string $from, string $to): array
    {
        if ($from === '' || $from === $to || !self::isValidPrefix($to)) {
            return [];
        }

        $renames = [];
        foreach (self::tables($sqlContent) as $table) {
            if (str_starts_with($table, $from)) {
                $renames[$table] = $to . substr($table, strlen($from));
            }
        }

        return $renames;
    }

    public static function rewriteTables(string $sqlContent, string $from, string $to): string
    {
        $substitutions = [];
        foreach (self::renames($sqlContent, $from, $to) as $table => $renamed) {
            $substitutions["`$table`"] = "`$renamed`";
        }

        return empty($substitutions) ? $sqlContent : strtr($sqlContent, $substitutions);
    }

    /**
     * Reduce a configured base_url to the site root every stored link starts with.
     *
     * A trailing '?' marks a query-style base, whose root is taken as written.
     */
    public static function root(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        $root = preg_replace('/\?+$/', '', $baseUrl);
        if ($root === '' || str_ends_with($root, '/')) {
            return $root;
        }

        return $root === $baseUrl ? "$root/" : $root;
    }

    /**
     * A target containing a quote, a backslash or a control character would corrupt the dump.
     */
    public static function isSafeTarget(string $baseUrl): bool
    {
        return $baseUrl !== '' && !preg_match('/[\'"\\\\\x00-\x1F]/', $baseUrl);
    }

    /**
     * Ordered replacement map covering both the plain and the JSON-escaped form of the root.
     *
     * @return array<string,string>
     */
    public static function substitutions(string $fromBaseUrl, string $toBaseUrl): array
    {
        $from = self::root($fromBaseUrl);
        $to = self::root($toBaseUrl);
        if ($from === '' || $to === '' || $from === $to || !self::isSafeTarget($to)) {
            return [];
        }

        $substitutions = [];
        foreach (self::schemeVariants($from) as $variant) {
            $substitutions[$variant] = $to;
            $substitutions[self::escapeForDump($variant)] = self::escapeForDump($to);
        }

        return $substitutions;
    }

    /**
     * @param array<string,string> $substitutions
     */
    public static function rewriteUrls(string $sqlContent, array $substitutions): string
    {
        return empty($substitutions) ? $sqlContent : strtr($sqlContent, $substitutions);
    }

    /**
     * Bazar entries are json_encode'd into the page body, so slashes reach the dump escaped twice.
     */
    private static function escapeForDump(string $url): string
    {
        return str_replace('/', '\\\\/', $url);
    }

    /**
     * @return string[]
     */
    private static function schemeVariants(string $root): array
    {
        if (!preg_match('#^https?://#i', $root)) {
            return [$root];
        }
        $withoutScheme = preg_replace('#^https?://#i', '', $root);

        return ["https://$withoutScheme", "http://$withoutScheme"];
    }
}

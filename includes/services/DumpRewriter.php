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
    public const TABLES_THAT_MAKE_A_WIKI = 2;
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
        $plan = self::plan(self::tables($sqlContent), $info, $targetPrefix, $targetBaseUrl, $rewriteUrls);

        return $plan->withSql(strtr($sqlContent, $plan->substitutions));
    }

    /**
     * What a dump has to be rewritten into, worked out from the tables it creates.
     *
     * The rewriting itself is a substitution map, so it can be applied statement by statement
     * and a dump never has to be held in memory whole.
     *
     * @param string[]            $tables
     * @param array<string,mixed> $info   contents of the restore info file, empty for an older archive
     *
     * @throws \Exception when the dump names no known table, or the target prefix is unusable
     */
    public static function plan(
        array $tables,
        array $info,
        string $targetPrefix,
        string $targetBaseUrl,
        bool $rewriteUrls = true
    ): DumpRewrite {
        $sourcePrefix = self::detectPrefixFromTables($tables);
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
        $urls = $rewriteUrls ? self::substitutions($sourceBaseUrl, $targetBaseUrl) : [];

        $own = self::ownTables($tables, $sourcePrefix);
        $substitutions = $urls;
        foreach (self::renamesFor($own, $sourcePrefix, $targetPrefix) as $table => $renamed) {
            $substitutions["`$table`"] = "`$renamed`";
        }

        $skip = [];
        foreach (array_diff($tables, $own) as $foreign) {
            $skip[] = "`$foreign`";
        }

        return new DumpRewrite(
            '',
            $sourcePrefix,
            $targetPrefix,
            empty($urls) ? '' : self::root($sourceBaseUrl),
            empty($urls) ? '' : self::root($targetBaseUrl),
            $substitutions,
            $skip,
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
        return self::detectPrefixFromTables(self::tables($sqlContent));
    }

    /**
     * The prefixes of other wikis living in the same database under a longer prefix.
     *
     * A wiki gives itself away by having several of the core tables under a name of its own:
     * `yeswiki_ecto__pages` and `yeswiki_ecto__users` next to `yeswiki_pages` are not this
     * wiki's tables, and neither backing them up nor replacing them is any of its business.
     *
     * @param string[] $tables
     *
     * @return string[]
     */
    public static function otherWikiPrefixes(array $tables, string $prefix): array
    {
        $counts = [];
        foreach ($tables as $table) {
            if ($prefix !== '' && strpos($table, $prefix) !== 0) {
                continue;
            }
            foreach (self::CORE_TABLES as $coreTable) {
                if (strlen($table) > strlen($prefix) + strlen($coreTable) && str_ends_with($table, $coreTable)) {
                    $candidate = substr($table, 0, -strlen($coreTable));
                    $counts[$candidate] = ($counts[$candidate] ?? 0) + 1;
                }
            }
        }

        $others = [];
        foreach ($counts as $candidate => $count) {
            if ($candidate !== $prefix && $count >= self::TABLES_THAT_MAKE_A_WIKI) {
                $others[] = $candidate;
            }
        }

        return $others;
    }

    /**
     * The tables of this wiki alone, among those of the database or of a dump.
     *
     * @param string[] $tables
     *
     * @return string[]
     */
    public static function ownTables(array $tables, string $prefix): array
    {
        $others = self::otherWikiPrefixes($tables, $prefix);
        $own = [];
        foreach ($tables as $table) {
            if ($prefix !== '' && strpos($table, $prefix) !== 0) {
                continue;
            }
            foreach ($others as $other) {
                if (strpos($table, $other) === 0) {
                    continue 2;
                }
            }
            $own[] = $table;
        }

        return $own;
    }

    /**
     * @param string[] $tables
     */
    public static function detectPrefixFromTables(array $tables): string
    {
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
        return self::renamesFor(self::tables($sqlContent), $from, $to);
    }

    /**
     * @param string[] $tables
     *
     * @return array<string,string>
     */
    public static function renamesFor(array $tables, string $from, string $to): array
    {
        if ($from === '' || $from === $to || !self::isValidPrefix($to)) {
            return [];
        }

        $renames = [];
        foreach ($tables as $table) {
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

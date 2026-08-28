<?php

namespace YesWiki\Kernel\Database;

class DumpRewriter
{
    /**
     * Tables a YesWiki has, used to work out which prefix a dump was taken with.
     *
     * @var list<string>
     */
    public const CORE_TABLES = ['pages', 'triples', 'search_index', 'search_keywords', 'search_queue'];

    public const TABLES_THAT_MAKE_A_WIKI = 2;

    /** What a backup records about the wiki it was taken from, so a restore knows what it is looking at. */
    public const INFO_FILENAME = 'restore.json';

    public static function isPrefix(string $prefix): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $prefix) === 1;
    }

    /**
     * Every table the dump creates, in the order it creates them.
     *
     * @return list<string>
     */
    public static function tables(string $sql): array
    {
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]([^`"]+)[`"]/i', $sql, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function detectPrefix(string $sql): string
    {
        return self::prefixOf(self::tables($sql));
    }

    /**
     * The prefix every table in the list shares, chosen from what the core tables suggest.
     *
     * @param list<string> $tables
     */
    public static function prefixOf(array $tables): string
    {
        $candidates = [];
        foreach ($tables as $table) {
            foreach (self::CORE_TABLES as $core) {
                if (\strlen($table) > \strlen($core) && str_ends_with($table, $core)) {
                    $candidates[substr($table, 0, -\strlen($core))] = true;
                }
            }
        }

        $candidates = array_keys($candidates);
        usort($candidates, static fn (string $a, string $b): int => \strlen($a) <=> \strlen($b));

        foreach ($candidates as $candidate) {
            foreach ($tables as $table) {
                if (!str_starts_with($table, $candidate)) {
                    continue 2;
                }
            }

            return $candidate;
        }

        return '';
    }

    /**
     * The new name of each table the dump creates, keyed by the old one.
     *
     * @param list<string> $tables
     *
     * @return array<string, string>
     */
    public static function renames(array $tables, string $from, string $to): array
    {
        if ($from === '' || $from === $to || !self::isPrefix($to)) {
            return [];
        }

        $renames = [];
        foreach ($tables as $table) {
            if (str_starts_with($table, $from)) {
                $renames[$table] = $to . substr($table, \strlen($from));
            }
        }

        return $renames;
    }

    /**
     * One statement with its table names replaced, matching only quoted identifiers so that a page whose text happens to mention a table name is left alone.
     *
     * @param array<string, string> $renames
     */
    public static function rewrite(string $statement, array $renames): string
    {
        if ($renames === []) {
            return $statement;
        }

        return (string)preg_replace_callback(
            '/([`"])([^`"]+)\1/',
            static fn (array $found): string => isset($renames[$found[2]])
                ? $found[1] . $renames[$found[2]] . $found[1]
                : $found[0],
            $statement
        );
    }

    /**
     * Reduce a configured base_url to the site root every stored link starts with.
     *
     * A trailing '?' marks a query-style base, whose root is taken as written.
     */
    public static function root(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        $root = (string)preg_replace('/\?+$/', '', $baseUrl);
        if ($root === '' || str_ends_with($root, '/')) {
            return $root;
        }

        return $root === $baseUrl ? "$root/" : $root;
    }

    /** A target containing a quote, a backslash or a control character would corrupt the dump. */
    public static function isSafeTarget(string $baseUrl): bool
    {
        return $baseUrl !== '' && !preg_match('/[\'"\\\\\x00-\x1F]/', $baseUrl);
    }

    /**
     * Ordered replacement map covering both the plain and the JSON-escaped form of the root.
     *
     * @return array<string, string>
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
     * Point every address the dump carries at this wiki instead of the one it came from.
     *
     * @param array<string, string> $substitutions
     */
    public static function rewriteUrls(string $sqlContent, array $substitutions): string
    {
        return empty($substitutions) ? $sqlContent : strtr($sqlContent, $substitutions);
    }

    /** An entry is json_encode'd into the page body, so slashes reach the dump escaped twice. */
    private static function escapeForDump(string $url): string
    {
        return str_replace('/', '\\\\/', $url);
    }

    /**
     * @return list<string>
     */
    private static function schemeVariants(string $root): array
    {
        if (!preg_match('#^https?://#i', $root)) {
            return [$root];
        }
        $withoutScheme = (string)preg_replace('#^https?://#i', '', $root);

        return ["https://$withoutScheme", "http://$withoutScheme"];
    }
}

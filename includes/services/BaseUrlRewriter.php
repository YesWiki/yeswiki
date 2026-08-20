<?php

namespace YesWiki\Core\Service;

/**
 * Rewrites the wiki root URL inside a SQL dump so an archive can be restored on another address.
 */
class BaseUrlRewriter
{
    public const INFO_FILENAME = 'restore.json';

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
    public static function rewrite(string $sqlContent, array $substitutions): string
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

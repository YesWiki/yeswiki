<?php

namespace YesWiki\Content\Entity;

/**
 * The one shape of `pages.body` (ticket 09).
 *
 * Every Content type -- wiki page, comment, bazar entry, form, list, user, file --
 * stores a JSON object in `body`. Before this ticket the column held raw wiki markup
 * for pages and comments and a JSON field-map for everything else, so every reader
 * had to know which kind of Content it was looking at before it could read the body.
 *
 * The container is uniform; the keys are not. A page carries its markup under
 * `content`, an entry carries its form fields at the top level. What callers can rely
 * on is that `decode()` always returns an array and `encode()` always produces a JSON
 * object, whatever the Content type.
 *
 * The column stays TEXT/LONGTEXT and holds JSON *text*: MySQL cannot build a FULLTEXT
 * index on a native JSON column, and `pages` has one on (tag, body).
 */
class PageBody
{
    /** Wiki markup, for the Content types that have prose: pages and comments. */
    public const CONTENT = 'content';

    /** Page keywords, a list of strings (ticket 09 moved these out of `triples`). */
    public const KEYWORDS = 'keywords';

    /** Display title. */
    public const TITLE = 'title';

    /**
     * JSON flags used everywhere a body is written, so a value survives a decode/encode
     * round trip unchanged. Unescaped unicode and slashes keep stored bodies readable
     * and, more importantly, keep them from being escaped twice by successive rewrites
     * -- the `é`-inside-a-string corruption real wikis already carry.
     */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Decode a stored body. Never throws and never returns a non-array: a body that is
     * not valid JSON is legacy markup, and is returned as `{content: <the markup>}` so
     * that a caller reading `$body[PageBody::CONTENT]` works on both shapes. That
     * fallback is a safety net for rows a failed migration left behind, not a supported
     * second shape -- nothing should rely on it.
     *
     * @return array<string, mixed>
     */
    public static function decode(?string $stored): array
    {
        $stored = (string)$stored;
        if (trim($stored) === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        return is_array($decoded) ? $decoded : [self::CONTENT => $stored];
    }

    /**
     * Encode a body for storage. Always an object, never a JSON array or scalar, so
     * that `json_decode(..., true)` on the way back always yields an associative array.
     * The cast matters for bodies whose keys are all numeric -- a form's `label` map is
     * `{"1": ..., "2": ...}` and PHP turns those keys into ints, which `json_encode`
     * would otherwise emit as a JSON array.
     *
     * @param array<array-key, mixed> $body
     */
    public static function encode(array $body): string
    {
        return (string)json_encode((object)$body, self::JSON_FLAGS);
    }

    /**
     * The wiki markup of a decoded body, or '' for Content types that have none.
     *
     * @param array<string, mixed> $body
     */
    public static function content(array $body): string
    {
        return (string)($body[self::CONTENT] ?? '');
    }

    /**
     * Whether two decoded bodies hold the same data, ignoring key order.
     *
     * `PageManager::save()` skips writing a new revision when the body is unchanged.
     * That test used to be a string comparison, which on JSON would both miss genuine
     * no-ops (the same data re-encoded with keys in another order) and invent
     * revisions out of them.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function equals(array $a, array $b): bool
    {
        self::ksortRecursive($a);
        self::ksortRecursive($b);

        return $a === $b;
    }

    /** @param array<string, mixed> $array */
    private static function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}

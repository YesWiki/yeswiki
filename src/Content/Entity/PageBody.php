<?php

namespace YesWiki\Content\Entity;

/** The one shape of `pages.body` (ticket 09). */
class PageBody
{
    /** Wiki markup, for the Content types that have prose: pages and comments. */
    public const CONTENT = 'content';

    /** Page keywords, a list of strings (ticket 09 moved these out of `triples`). */
    public const KEYWORDS = 'keywords';

    /** Display title. */
    public const TITLE = 'title';

    /**
     * JSON flags used everywhere a body is written, so a value survives a decode/encode round trip unchanged.
     */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Decode a stored body.
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
     * Encode a body for storage.
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
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function equals(array $a, array $b): bool
    {
        self::ksortRecursive($a);
        self::ksortRecursive($b);

        return $a === $b;
    }

    /**
     * @param array<string, mixed> $array
     */
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

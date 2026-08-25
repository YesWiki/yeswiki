<?php

namespace YesWiki\Kernel\Database;

/** Binds PHP values onto a prepared statement with the SQL type they actually are. */
final class SqlParameters
{
    /** The PDO type for one PHP value. */
    public static function typeOf(mixed $value): int
    {
        return match (true) {
            $value === null => \PDO::PARAM_NULL,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_int($value) => \PDO::PARAM_INT,
            default => \PDO::PARAM_STR,
        };
    }

    /**
     * Bind every value in $params onto $statement.
     *
     * @param array<array-key, mixed> $params
     */
    public static function bind(\PDOStatement $statement, array $params): void
    {
        $positional = array_is_list($params);

        foreach ($params as $key => $value) {
            $placeholder = $positional
                ? (int)$key + 1
                : self::named((string)$key);

            $statement->bindValue($placeholder, $value, self::typeOf($value));
        }
    }

    /** `tag` and `:tag` both mean the same placeholder; callers should not have to care. */
    private static function named(string $key): string
    {
        return str_starts_with($key, ':') ? $key : ':' . $key;
    }

    /**
     * Refuse a positional statement whose `?` count does not match its value count.
     *
     * @param array<array-key, mixed> $params
     *
     * @throws \InvalidArgumentException
     */
    public static function assertPlaceholderCount(string $sql, array $params): void
    {
        if (!array_is_list($params)) {
            return;
        }

        $stripped = (string)preg_replace("/'(?:[^']|'')*'/", "''", $sql);
        $placeholders = substr_count($stripped, '?');

        if ($placeholders !== count($params)) {
            throw new \InvalidArgumentException(sprintf('This statement has %d placeholder(s) but was given %d value(s): %s', $placeholders, count($params), trim((string)preg_replace('/\s+/', ' ', $sql))));
        }
    }

    /**
     * `?, ?, ?` for a `WHERE tag IN (...)` of $count values.
     *
     * @throws \InvalidArgumentException on a count of zero, which would produce `IN ()` -- a
     *                                   syntax error on every driver. Callers must not reach here with an empty list;
     *                                   the question they meant to ask is "match nothing", and it needs no query.
     */
    public static function placeholders(int $count): string
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('An IN clause needs at least one value; guard the empty case before building SQL.');
        }

        return implode(', ', array_fill(0, $count, '?'));
    }

    /** The character that turns off a LIKE wildcard, for use with `likeWildcardsEscaped()`. */
    public const LIKE_ESCAPE = '!';

    /** A term with its LIKE wildcards defused, so `LIKE` matches it literally. */
    public static function likeWildcardsEscaped(string $term): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $term
        );
    }

    /** The `%term%` body for a substring LIKE, wildcards defused. */
    public static function likeContains(string $term): string
    {
        return '%' . self::likeWildcardsEscaped($term) . '%';
    }

    /** The `term%` body for a prefix LIKE, wildcards defused. */
    public static function likeStartsWith(string $term): string
    {
        return self::likeWildcardsEscaped($term) . '%';
    }

    /** The clause that makes an escaped LIKE pattern mean what it says, on every driver. */
    public const LIKE_CLAUSE_SUFFIX = " ESCAPE '" . self::LIKE_ESCAPE . "'";

    /**
     * The statement with its values spliced in, **for human eyes only**.
     *
     * @param array<array-key, mixed> $params
     */
    public static function interpolateForDisplay(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }

        if (array_is_list($params)) {
            $remaining = $params;

            return (string)preg_replace_callback(
                '/\?/',
                function () use (&$remaining): string {
                    return $remaining === [] ? '?' : self::show(array_shift($remaining));
                },
                $sql
            );
        }

        $names = array_map(fn ($k): string => self::named((string)$k), array_keys($params));
        usort($names, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $key = array_key_exists($name, $params) ? $name : ltrim($name, ':');
            $sql = str_replace($name, self::show($params[$key]), $sql);
        }

        return $sql;
    }

    /** One value as the log should show it -- NULL visible as NULL, long text cut. */
    private static function show(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        $text = (string)$value;

        if (mb_strlen($text) > 120) {
            $text = mb_substr($text, 0, 120) . '...';
        }

        return "'" . $text . "'";
    }
}

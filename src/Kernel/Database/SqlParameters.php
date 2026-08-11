<?php

namespace YesWiki\Kernel\Database;

/**
 * Binds PHP values onto a prepared statement with the SQL type they actually are.
 *
 * This is the piece that makes bindings worth more than `DbService::escape()`. `escape()`
 * casts everything through `(string)`, so `null` reaches the database as `''` and `5` as
 * `'5'`. MySQL coerces its way out of both and PostgreSQL does not, which is the shape of
 * every "works on MySQL, fails on Postgres" bug this codebase has hit. Binding by type
 * decides the question once, here, instead of at each of the escape() call sites
 * EscapeRatchetTest is counting down.
 *
 * `PDO::ATTR_EMULATE_PREPARES` is false (see DbService::initSqlConnection), so these are
 * real server-side placeholders. That is also why the type matters at all: with emulation
 * on, PDO would quote everything as a string and MySQL would coerce it back. With emulation
 * off, `LIMIT ?` bound as a string is a syntax error -- an int has to be sent as an int.
 *
 * Deliberately stateless and free of the PDO link, like the dialects next to it: what a
 * value binds as is a function of the value, not of the connection.
 */
final class SqlParameters
{
    /**
     * The PDO type for one PHP value.
     *
     * Floats bind as strings on purpose: PDO has no PARAM_FLOAT, and PHP renders a float
     * with a `.` decimal separator regardless of locale, so the string form is exact and
     * portable. Everything else maps to the placeholder the database expects.
     */
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
     * Both placeholder styles are accepted, chosen by the array's own shape rather than by a
     * flag: a list binds positionally (`?`, 1-indexed as PDO requires), and a map binds by
     * name (`:tag`), with or without the leading colon. Named parameters are what keep a
     * ten-column INSERT readable, positional ones what keep a two-term WHERE short.
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
     * PDO already rejects this at execute() time, with "SQLSTATE[HY093]: Invalid parameter
     * number" and no indication of which statement or which way the mismatch goes. That is a
     * poor error for the mistake it describes, and the mistake is easy: converting a query
     * built by concatenating several fragments means keeping placeholders and values in step
     * across all of them, and a statement whose values went missing altogether is still valid
     * SQL right up to the moment it runs.
     *
     * Positional only. A named statement may legitimately reuse one placeholder twice, so its
     * counts need not agree and PDO's own check is the right one.
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

        // string literals go first: a `?` inside one is data, not a placeholder. Doubled
        // quotes inside a literal are consumed by the same pass.
        $stripped = (string)preg_replace("/'(?:[^']|'')*'/", "''", $sql);
        $placeholders = substr_count($stripped, '?');

        if ($placeholders !== count($params)) {
            throw new \InvalidArgumentException(sprintf('This statement has %d placeholder(s) but was given %d value(s): %s', $placeholders, count($params), trim((string)preg_replace('/\s+/', ' ', $sql))));
        }
    }

    /**
     * `?, ?, ?` for a `WHERE tag IN (...)` of $count values.
     *
     * SQL has no way to bind a list to one placeholder, so an IN clause needs as many
     * placeholders as it has values -- which is the one place a parameterised query still has
     * to build SQL from a count. Building it from the count (rather than from the values) is
     * what keeps the values out of the text.
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

    /**
     * The character that turns off a LIKE wildcard, for use with `likeWildcardsEscaped()`.
     *
     * `!` rather than the conventional `\`, because a backslash also means something to a MySQL
     * string literal and nothing to a SQLite one -- writing `ESCAPE '\'` portably means
     * reasoning about two levels of escaping at once. `!` has no meaning at either level.
     */
    public const LIKE_ESCAPE = '!';

    /**
     * A term with its LIKE wildcards defused, so `LIKE` matches it literally.
     *
     * `%` and `_` are wildcards inside a LIKE pattern, and nothing in a value-escaping or
     * value-binding path touches them -- they are legitimate pattern syntax, so neither
     * `escape()` nor a bound parameter has any business altering them. The result is that a
     * visitor searching for `100%` searches for "100 followed by anything", and one searching
     * `a_b` matches `aXb`. This is the piece that has to be applied on purpose.
     *
     * The escape character itself is doubled first, or a term containing `!` would defuse the
     * wrong character. **The pattern this returns only behaves if the query names the escape
     * character**: `LIKE '%...%' ESCAPE '!'`. SQLite has no default escape character at all, so
     * without that clause the escaping is silently inert there while working on MySQL -- the
     * exact failure shape this codebase keeps hitting. Use `likeContains()` to get both halves
     * right at once.
     */
    public static function likeWildcardsEscaped(string $term): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $term
        );
    }

    /**
     * The `%term%` body for a substring LIKE, wildcards defused.
     *
     * Pair it with `LIKE_CLAUSE_SUFFIX` (or bind it and append that suffix yourself) so the
     * ESCAPE clause is never forgotten.
     */
    public static function likeContains(string $term): string
    {
        return '%' . self::likeWildcardsEscaped($term) . '%';
    }

    /** The clause that makes an escaped LIKE pattern mean what it says, on every driver. */
    public const LIKE_CLAUSE_SUFFIX = " ESCAPE '" . self::LIKE_ESCAPE . "'";

    /**
     * The statement with its values spliced in, **for human eyes only**.
     *
     * Binding means the debug footer would otherwise show `WHERE tag = ?` and never say
     * which tag -- losing the one thing that made the query log useful. This rebuilds the
     * readable form for the log and for nothing else.
     *
     * This string is never executed and must never be passed to the database. It is not
     * escaped to any driver's rules and makes no attempt to be: it exists so a developer
     * reading the footer can see the values, and quoting it correctly would only make it
     * look safe enough for someone to reuse.
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

        // Longest name first: replacing `:id` before `:id_form` would corrupt the latter.
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
        // A single indexed `text` column can be tens of kilobytes; the footer prints every
        // query on one line, so an untruncated body makes the whole report unreadable.
        if (mb_strlen($text) > 120) {
            $text = mb_substr($text, 0, 120) . '...';
        }

        return "'" . $text . "'";
    }
}

<?php

namespace YesWiki\Kernel\Database;

/** A piece of SQL together with the values it binds (ticket 31). */
final class SqlFragment
{
    /**
     * @param list<mixed> $params
     */
    private function __construct(
        public readonly string $sql,
        public readonly array $params,
    ) {
    }

    /**
     * @param list<mixed> $params
     *
     * @throws \InvalidArgumentException if the placeholders and values already disagree -- the
     *                                   mistake is much cheaper to find here than three compositions later
     */
    public static function of(string $sql, array $params = []): self
    {
        SqlParameters::assertPlaceholderCount($sql, $params);

        return new self($sql, $params);
    }

    /** Nothing at all -- distinct from `of('')` only in intent, and the identity element of `all()`. */
    public static function empty(): self
    {
        return new self('', []);
    }

    public function isEmpty(): bool
    {
        return trim($this->sql) === '';
    }

    /**
     * Join fragments with $glue, dropping the empty ones.
     *
     * @param string $glue e.g. `' AND '`, `' OR '`
     */
    public static function all(string $glue, self ...$parts): self
    {
        $kept = array_values(array_filter($parts, static fn (self $p): bool => !$p->isEmpty()));
        if ($kept === []) {
            return self::empty();
        }

        $sql = implode($glue, array_map(static fn (self $p): string => $p->sql, $kept));
        $params = [];
        foreach ($kept as $part) {
            $params = [...$params, ...$part->params];
        }

        return new self($sql, $params);
    }

    /** The same values, with the SQL surrounded -- parentheses, `NOT (...)`, `HAVING ...`. */
    public function wrappedIn(string $before, string $after): self
    {
        if ($this->isEmpty()) {
            return self::empty();
        }

        return new self($before . $this->sql . $after, $this->params);
    }

    /** This fragment's SQL if it has any, else $fallback -- for a WHERE that must not be blank. */
    public function sqlOr(string $fallback): string
    {
        return $this->isEmpty() ? $fallback : $this->sql;
    }
}

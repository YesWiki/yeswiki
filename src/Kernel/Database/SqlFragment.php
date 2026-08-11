<?php

namespace YesWiki\Kernel\Database;

/**
 * A piece of SQL together with the values it binds (ticket 31).
 *
 * `DbService::query($sql, $params)` takes the two apart, which is right at the point a
 * statement is executed and useless anywhere else: several services build a *fragment* -- a
 * WHERE clause, an ACL predicate, an IN list -- that a caller two or three methods away pastes
 * into a query of its own. There is nowhere in that handover to put a value, which is why
 * those call sites kept using `escape()` long after every ordinary query had been converted.
 *
 * The alternative to this class is threading a parallel `$sql` / `$params` pair through every
 * builder and hoping the two stay in step. They do not: the burn-down that led here produced,
 * from a scripted rewrite, an `UPDATE ... SET body = ? WHERE id = ?` whose params array was
 * never attached -- valid SQL, silent until it ran. A fragment that carries its own values
 * cannot drift from them, and composing fragments composes the values in the same order as the
 * placeholders by construction rather than by care.
 *
 * Immutable, and deliberately ignorant of the connection: what a fragment *is* does not depend
 * on which driver will eventually run it.
 */
final class SqlFragment
{
    /** @param list<mixed> $params */
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

    /**
     * Nothing at all -- distinct from `of('')` only in intent, and the identity element of
     * `all()`. A builder with no conditions to add returns this rather than an empty string,
     * so callers stop writing `!empty($fragment)` checks around string concatenation.
     */
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
     * Dropping empties is the behaviour every caller hand-wrote and occasionally got wrong: a
     * conditional clause that contributes nothing must not leave a dangling `AND`. Values are
     * concatenated in the same order the fragments are, which is the order their placeholders
     * appear in the result -- that correspondence is the reason this class exists.
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

    /**
     * The same values, with the SQL surrounded -- parentheses, `NOT (...)`, `HAVING ...`.
     *
     * An empty fragment stays empty: wrapping nothing in parentheses yields `()`, which is a
     * syntax error rather than a no-op.
     */
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

<?php

namespace YesWiki\Kernel\Database;

/** The `ON CONFLICT ... DO UPDATE` half of SqlDialect::upsert(), which PostgreSQL and SQLite spell the same way. */
final class SqlUpsert
{
    /**
     * @param array<string, string> $values
     * @param list<string>          $conflictColumns
     * @param array<string, string> $assignments  `:new.x` is the row being inserted, `:old.x` the one already there
     */
    public static function onConflict(SqlDialect $dialect, string $table, array $values, array $conflictColumns, array $assignments): string
    {
        $sets = [];
        foreach ($assignments as $column => $expression) {
            $expression = (string)preg_replace_callback(
                '/:new\.([a-z_]+)/i',
                fn (array $m): string => 'EXCLUDED.' . $dialect->quoteIdentifier($m[1]),
                $expression
            );
            $sets[] = $dialect->quoteIdentifier($column) . ' = ' . self::oldRow($dialect, $expression, $table);
        }

        return 'INSERT INTO ' . $dialect->quoteIdentifier($table)
            . ' (' . implode(', ', array_map(fn (string $c): string => $dialect->quoteIdentifier($c), array_keys($values))) . ')'
            . ' VALUES (' . implode(', ', array_values($values)) . ')'
            . ' ON CONFLICT (' . implode(', ', array_map(fn (string $c): string => $dialect->quoteIdentifier($c), $conflictColumns)) . ')'
            . ' DO UPDATE SET ' . implode(', ', $sets);
    }

    /**
     * `:old.x` as the row already in the table, qualified by its name.
     *
     * Qualifying is what makes it work rather than what makes it tidy: PostgreSQL has both the
     * target table and `excluded` in scope inside `DO UPDATE SET`, so a bare `repeat` there is
     * an ambiguous column reference, not a default to the target.
     */
    public static function oldRow(SqlDialect $dialect, string $expression, string $table): string
    {
        return (string)preg_replace_callback(
            '/:old\.([a-z_]+)/i',
            fn (array $m): string => $dialect->quoteIdentifier($table) . '.' . $dialect->quoteIdentifier($m[1]),
            $expression
        );
    }
}

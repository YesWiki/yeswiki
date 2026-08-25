<?php

namespace YesWiki\Kernel\Database;

/** The `ON CONFLICT ... DO UPDATE` half of SqlDialect::upsert(), which PostgreSQL and SQLite spell the same way. */
final class SqlUpsert
{
    /**
     * @param array<string, string> $values
     * @param list<string>          $conflictColumns
     * @param array<string, string> $assignments
     */
    public static function onConflict(SqlDialect $dialect, string $table, array $values, array $conflictColumns, array $assignments): string
    {
        $sets = [];
        foreach ($assignments as $column => $expression) {
            $sets[] = $dialect->quoteIdentifier($column) . ' = ' . preg_replace_callback(
                '/:new\.([a-z_]+)/i',
                fn (array $m): string => 'EXCLUDED.' . $dialect->quoteIdentifier($m[1]),
                $expression
            );
        }

        return 'INSERT INTO ' . $dialect->quoteIdentifier($table)
            . ' (' . implode(', ', array_map(fn (string $c): string => $dialect->quoteIdentifier($c), array_keys($values))) . ')'
            . ' VALUES (' . implode(', ', array_values($values)) . ')'
            . ' ON CONFLICT (' . implode(', ', array_map(fn (string $c): string => $dialect->quoteIdentifier($c), $conflictColumns)) . ')'
            . ' DO UPDATE SET ' . implode(', ', $sets);
    }
}

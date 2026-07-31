<?php

namespace YesWiki\Kernel\Database;

/**
 * Splits an SQL dump into individual statements.
 *
 * `mysqli_multi_query()` did this in the driver, which is why archive restore only ever
 * worked on MySQL (ticket 17). PDO has no equivalent, so the split happens here -- and a
 * naive `explode(';')` is wrong in ways that corrupt data rather than fail loudly: a
 * semicolon inside a page body, inside a quoted identifier, or inside a comment would cut a
 * statement in half and the INSERT would be replayed truncated.
 *
 * What has to be tracked: single-quoted strings (with both `''` and backslash escaping),
 * double-quoted strings, backtick-quoted identifiers, `--` and `#` line comments, and
 * `/* *\/` block comments. Block comments are *kept* rather than stripped, because MySQL's
 * version-gated executable comments (`/*!40101 SET ... *\/`) are statements in a MySQL dump.
 */
final class SqlStatementSplitter
{
    /**
     * @return list<string> non-empty statements, in order, without their trailing semicolon
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // line comments run to the end of the line. `--` only starts one when followed by
            // whitespace, so `5--3` (a subtraction) is not mistaken for a comment.
            if (($char === '-' && $next === '-' && self::isLineCommentStart($sql, $i)) || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $length : $end;
                $current .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                $current .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $end = self::endOfQuoted($sql, $i, $char);
                $current .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        $statements[] = $current;

        return array_values(array_filter(
            array_map([self::class, 'stripLeadingComments'], $statements),
            fn (string $statement) => $statement !== ''
        ));
    }

    /**
     * Drop the commentary a dump puts above each statement, so a statement starts with SQL.
     *
     * Keeps counts predictable and makes "statement 7 failed" mean something. MySQL's
     * executable comments (`/*!...*\/`) are SQL, not commentary, and are never stripped.
     */
    private static function stripLeadingComments(string $statement): string
    {
        $statement = trim($statement);

        while ($statement !== '') {
            if (str_starts_with($statement, '--') || str_starts_with($statement, '#')) {
                $newline = strpos($statement, "\n");
                if ($newline === false) {
                    return '';
                }
                $statement = ltrim(substr($statement, $newline + 1));

                continue;
            }
            if (str_starts_with($statement, '/*') && !str_starts_with($statement, '/*!')) {
                $end = strpos($statement, '*/');
                if ($end === false) {
                    return '';
                }
                $statement = ltrim(substr($statement, $end + 2));

                continue;
            }

            break;
        }

        return $statement;
    }

    /** `--` introduces a comment only when followed by whitespace or end of input. */
    private static function isLineCommentStart(string $sql, int $position): bool
    {
        $after = $sql[$position + 2] ?? "\n";

        return $after === ' ' || $after === "\t" || $after === "\n" || $after === "\r";
    }

    /**
     * Index just past the closing quote of the literal starting at $start.
     *
     * Handles both escaping conventions: a doubled quote (`''`, the SQL standard) and a
     * backslash-escaped one (`\'`, which MySQL emits by default and which our own dump
     * produces). An unterminated literal consumes the rest of the input rather than throwing:
     * the statement will fail on execution, where the error message is about the actual SQL.
     */
    private static function endOfQuoted(string $sql, int $start, string $quote): int
    {
        $length = strlen($sql);
        $i = $start + 1;

        while ($i < $length) {
            $char = $sql[$i];

            if ($char === '\\' && $quote !== '`') {
                $i += 2;

                continue;
            }
            if ($char === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $i += 2;

                    continue;
                }

                return $i + 1;
            }
            $i++;
        }

        return $length;
    }
}

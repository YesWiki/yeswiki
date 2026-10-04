<?php

namespace YesWiki\Kernel\Database;

/** Splits an SQL dump into individual statements. */
final class SqlStatementSplitter
{
    /** Keywords that open a compound statement inside a trigger body, and the one that closes it. */
    private const BLOCK_KEYWORDS = ['BEGIN' => 1, 'CASE' => 1, 'END' => -1];

    public const CHUNK_SIZE = 1048576;

    /**
     * @return list<string> non-empty statements, in order, without their trailing semicolon
     */
    public static function split(string $sql): array
    {
        [$statements, $rest] = self::cut($sql);
        $statements[] = substr($sql, $rest);

        return self::cleaned($statements);
    }

    /**
     * The same, read from a stream as it goes, so a dump of any size costs the memory of one statement.
     *
     * @param resource $handle
     *
     * @return \Generator<int, string>
     */
    public static function fromStream($handle, int $chunkSize = self::CHUNK_SIZE): \Generator
    {
        $buffer = '';
        $want = $chunkSize;
        while (!feof($handle)) {
            $chunk = fread($handle, max(1, $want));
            if ($chunk === false) {
                throw new \RuntimeException('Cannot read the SQL dump');
            }
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;
            [$statements, $rest] = self::cut($buffer);
            $buffer = substr($buffer, $rest);
            $want = $statements === [] ? max($chunkSize, \strlen($buffer)) : $chunkSize;
            yield from self::cleaned($statements);
        }

        yield from self::cleaned([$buffer]);
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private static function cleaned(array $statements): array
    {
        return array_values(array_filter(
            array_map([self::class, 'stripLeadingComments'], $statements),
            fn (string $statement) => $statement !== ''
        ));
    }

    /**
     * The statements $sql completes, and where the one it leaves unfinished starts.
     *
     * @return array{0: list<string>, 1: int}
     */
    private static function cut(string $sql): array
    {
        $statements = [];
        $start = 0;
        $current = '';
        $length = strlen($sql);
        $i = 0;
        $blockDepth = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

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

            $keyword = self::blockKeywordAt($sql, $i);
            if ($keyword !== null && self::isTriggerStatement($current)) {
                $blockDepth = max(0, $blockDepth + self::BLOCK_KEYWORDS[$keyword]);
                $current .= substr($sql, $i, strlen($keyword));
                $i += strlen($keyword);

                continue;
            }

            if ($char === ';' && $blockDepth === 0) {
                $statements[] = $current;
                $current = '';
                $i++;
                $start = $i;

                continue;
            }

            $current .= $char;
            $i++;
        }

        return [$statements, $start];
    }

    /** Drop the commentary a dump puts above each statement, so a statement starts with SQL. */
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

    /** The block keyword starting at $position, as a whole word, or null. */
    private static function blockKeywordAt(string $sql, int $position): ?string
    {
        $before = $position > 0 ? $sql[$position - 1] : ' ';
        if (ctype_alnum($before) || $before === '_') {
            return null;
        }

        foreach (array_keys(self::BLOCK_KEYWORDS) as $keyword) {
            if (strcasecmp(substr($sql, $position, strlen($keyword)), $keyword) !== 0) {
                continue;
            }
            $after = $sql[$position + strlen($keyword)] ?? ' ';
            if (!ctype_alnum($after) && $after !== '_') {
                return $keyword;
            }
        }

        return null;
    }

    /** Whether what has been accumulated so far is a `CREATE TRIGGER`. */
    private static function isTriggerStatement(string $current): bool
    {
        return preg_match(
            '/(^|\n)\s*CREATE\s+(OR\s+REPLACE\s+)?(TEMP(ORARY)?\s+)?TRIGGER\b/i',
            $current
        ) === 1;
    }

    /** `--` introduces a comment only when followed by whitespace or end of input. */
    private static function isLineCommentStart(string $sql, int $position): bool
    {
        $after = $sql[$position + 2] ?? "\n";

        return $after === ' ' || $after === "\t" || $after === "\n" || $after === "\r";
    }

    /** Index just past the closing quote of the literal starting at $start. */
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

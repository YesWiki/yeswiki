<?php

namespace YesWiki\Core\Service;

/**
 * Runs a SQL dump one statement at a time.
 *
 * A whole dump handed to mysqli_multi_query() travels as a single packet, so a backup bigger
 * than max_allowed_packet is refused in one piece — after the tables it replaces are gone.
 */
class SqlScript
{
    public const CHUNK_SIZE = 1048576;
    private const IN_LINE_COMMENT = 'line';
    private const IN_BLOCK_COMMENT = 'block';

    /**
     * Cut a dump into statements, leaving what is inside quotes, backticks and comments alone.
     *
     * @return \Generator<string>
     */
    public static function statements(string $sqlContent): \Generator
    {
        yield from self::statementsFromChunks([$sqlContent]);
    }

    /**
     * The same, reading as it goes, so a dump of any size costs the memory of one statement.
     *
     * @param resource $handle
     *
     * @return \Generator<string>
     */
    public static function statementsFromStream($handle): \Generator
    {
        yield from self::statementsFromChunks(self::chunks($handle));
    }

    /**
     * @param resource $handle
     *
     * @return \Generator<string>
     */
    private static function chunks($handle): \Generator
    {
        while (!feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            if ($chunk === false) {
                throw new \Exception('Cannot read the SQL dump');
            }
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }

    /**
     * @param iterable<string> $chunks
     *
     * @return \Generator<string>
     */
    private static function statementsFromChunks(iterable $chunks): \Generator
    {
        $buffer = '';
        $offset = 0;
        $state = '';

        foreach ($chunks as $chunk) {
            $buffer .= $chunk;
            [$statements, $buffer, $offset, $state] = self::scan($buffer, $offset, $state);
            yield from $statements;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            yield $tail;
        }
    }

    /**
     * Read as far as the buffer allows, keeping where it stopped and what it was in the middle of.
     *
     * @return array{0:string[],1:string,2:int,3:string} statements, what is left of the buffer,
     *                                                   where to resume in it, and in what state
     */
    private static function scan(string $buffer, int $offset, string $state): array
    {
        $statements = [];
        $length = strlen($buffer);

        while ($offset < $length) {
            if ($state === self::IN_LINE_COMMENT) {
                $newLine = strpos($buffer, "\n", $offset);
                if ($newLine === false) {
                    return [$statements, $buffer, $length, $state];
                }
                $state = '';
                $offset = $newLine + 1;
                continue;
            }
            if ($state === self::IN_BLOCK_COMMENT) {
                $end = strpos($buffer, '*/', $offset);
                if ($end === false) {
                    return [$statements, $buffer, max($offset, $length - 1), $state];
                }
                $state = '';
                $offset = $end + 2;
                continue;
            }
            if ($state !== '') {
                $end = self::closingQuote($buffer, $offset, $state);
                if (is_null($end)) {
                    return [$statements, $buffer, self::resumeAt($buffer, $state), $state];
                }
                $state = '';
                $offset = $end;
                continue;
            }

            if (!preg_match('/[\'"`;#]|--[ \t]|\/\*/', $buffer, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                return [$statements, $buffer, $length, $state];
            }
            $token = $matches[0][0];
            $position = $matches[0][1];

            if ($token === ';') {
                $statement = trim(substr($buffer, 0, $position));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = substr($buffer, $position + 1);
                $length = strlen($buffer);
                $offset = 0;
                continue;
            }
            if ($token === "'" || $token === '"' || $token === '`') {
                $state = $token;
                $offset = $position + 1;
                continue;
            }
            if ($token === '#' || $token[0] === '-') {
                $state = self::IN_LINE_COMMENT;
                $offset = $position + 1;
                continue;
            }
            $state = self::IN_BLOCK_COMMENT;
            $offset = $position + 2;
        }

        return [$statements, $buffer, $offset, $state];
    }

    /**
     * Where scanning can pick up again when a quoted run is not closed in the buffer yet:
     * one character back when the last one could pair with what comes next.
     */
    private static function resumeAt(string $buffer, string $quote): int
    {
        $length = strlen($buffer);
        $last = $length > 0 ? $buffer[$length - 1] : '';

        return ($last === '\\' || $last === $quote) ? $length - 1 : $length;
    }

    /**
     * Where the quoted run ends, or null while the buffer does not close it.
     */
    private static function closingQuote(string $buffer, int $offset, string $quote): ?int
    {
        $length = strlen($buffer);
        while ($offset < $length) {
            $next = strpos($buffer, $quote, $offset);
            if ($next === false) {
                return null;
            }
            $backslashes = 0;
            for ($i = $next - 1; $i >= 0 && $buffer[$i] === '\\'; $i--) {
                $backslashes++;
            }
            if ($backslashes % 2 === 1) {
                $offset = $next + 1;
                continue;
            }
            if ($next + 1 >= $length) {
                return null;
            }
            if ($buffer[$next + 1] === $quote) {
                $offset = $next + 2;
                continue;
            }

            return $next + 1;
        }

        return null;
    }

    /**
     * The first statement the server would refuse to receive, if there is one.
     */
    public static function oversizedStatement(string $sqlContent, int $maxPacket): ?string
    {
        if ($maxPacket <= 0) {
            return null;
        }
        foreach (self::statements($sqlContent) as $statement) {
            if (strlen($statement) > $maxPacket) {
                return $statement;
            }
        }

        return null;
    }

    /**
     * @throws \Exception on the first statement the server refuses
     */
    public static function run(\mysqli $connection, string $sqlContent): void
    {
        self::runStatements($connection, self::statements($sqlContent));
    }

    /**
     * @param iterable<string> $statements
     *
     * @throws \Exception on the first statement the server refuses
     */
    public static function runStatements(\mysqli $connection, iterable $statements): void
    {
        $maxPacket = self::maxAllowedPacket($connection);
        foreach ($statements as $statement) {
            if (self::isSessionPlumbing($statement)) {
                continue;
            }
            if (!mysqli_query($connection, $statement)) {
                throw new \Exception(self::errorMessage(mysqli_error($connection), $statement, $maxPacket));
            }
        }
    }

    /**
     * Session plumbing a dump wraps itself in, which cannot hold when the import is cut into
     * slices: each one runs on its own connection, so a transaction opened in one is rolled
     * back at the end of it and a user variable set in one is gone by the next.
     */
    public static function isSessionPlumbing(string $statement): bool
    {
        $bare = trim(preg_replace('/^\/\*!\d*\s*|\s*\*\/$/', '', trim($statement)));

        return (bool)preg_match('/^(SET\s+@|SET\s+[^=]*=\s*@OLD_|SET\s+(SESSION\s+)?AUTOCOMMIT\s*=|START\s+TRANSACTION|COMMIT|ROLLBACK)/i', $bare);
    }

    /**
     * What the dump's own preamble would have set, applied so it holds for this connection only.
     *
     * @throws \Exception
     */
    public static function prepareSession(\mysqli $connection): void
    {
        foreach (["SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'", "SET TIME_ZONE='+00:00'", 'SET autocommit=1'] as $statement) {
            if (!mysqli_query($connection, $statement)) {
                throw new \Exception("Cannot prepare the connection: $statement: " . mysqli_error($connection));
            }
        }
    }

    public static function maxAllowedPacket(\mysqli $connection): int
    {
        $result = @mysqli_query($connection, "SHOW VARIABLES LIKE 'max_allowed_packet'");
        $row = $result ? mysqli_fetch_row($result) : null;

        return empty($row[1]) ? 0 : (int)$row[1];
    }

    public static function errorMessage(string $error, string $statement, int $maxPacket): string
    {
        $shown = preg_replace('/^\s*(?:--[ \t][^\n]*|#[^\n]*)\n/m', '', $statement);
        $message = 'SQL failed on "' . substr(preg_replace('/\s+/', ' ', trim($shown)), 0, 60) . '…": ' . $error;
        if ($maxPacket > 0 && strlen($statement) > $maxPacket) {
            $message .= ' — that statement is ' . round(strlen($statement) / 1024 / 1024, 1) . ' MB while this server accepts '
                . round($maxPacket / 1024 / 1024, 1) . ' MB (max_allowed_packet). Raise it, or take the backup again with a'
                . ' YesWiki recent enough to cut its dumps into smaller statements.';
        }

        return $message;
    }
}

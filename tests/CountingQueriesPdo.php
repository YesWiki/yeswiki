<?php

namespace YesWiki\Test;

/**
 * A PDO that counts the statements that go through it, for the tests that assert a piece of
 * work costs a bounded number of queries.
 *
 * One class, shared, rather than one per test file: each such test swaps its PDO into
 * DbService and leaves it there, so two different wrapper classes take turns replacing each
 * other's connection and the counts stop meaning anything. That is not hypothetical -- it is
 * why FormManagerNarrowReadsTest passed alone and failed beside PerRequestCachesTest.
 */
class CountingQueriesPdo extends \PDO
{
    public static int $count = 0;

    /**
     * Every statement seen, so a test can assert on the *shape* of what ran, not just how much.
     *
     * @var list<string>
     */
    public static array $statements = [];

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        self::$count++;
        self::$statements[] = (string)preg_replace('/\s+/', ' ', trim($query));

        return parent::query($query);
    }

    /** @return list<string> the statements issued since $fromCount */
    public static function statementsSince(int $fromCount): array
    {
        return array_slice(self::$statements, $fromCount);
    }

    /**
     * Install it into DbService if it is not already there, and return the current count.
     *
     * DbService memoises SELECTs of its own; its read cache is cleared here so a caller
     * measures the queries the code avoided, not the ones DbService happened to answer twice.
     */
    public static function countFor(\YesWiki\Kernel\Service\DbService $dbService): ?int
    {
        $link = (new \ReflectionProperty($dbService, 'link'))->getValue($dbService);

        if (!$link instanceof self) {
            if ($dbService->getDriver() !== 'sqlite') {
                return null; // the caller skips: this is wired for the suite's sqlite database
            }
            $link = new self('sqlite:private/yeswiki.db');
            $link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            (new \ReflectionProperty($dbService, 'link'))->setValue($dbService, $link);
        }
        (new \ReflectionProperty($dbService, 'readCache'))->setValue($dbService, []);

        return self::$count;
    }
}

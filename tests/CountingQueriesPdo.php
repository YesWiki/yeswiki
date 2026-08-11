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
/**
 * The statement half of the counter.
 *
 * A parameterised query does not go through PDO::query() at all -- DbService prepares it, binds
 * the values and executes -- so counting only in query() made every bound statement invisible.
 * That is not a small hole: the tests using this counter exist to pin that a piece of work costs
 * N queries and not 2N, and as call sites move to bindings (see EscapeRatchetTest) a count that
 * silently drops to zero reads as "even better than asserted" rather than as a broken measure.
 *
 * Counted on execute() rather than on prepare(), because one prepared statement executed in a
 * loop is as many queries as it has iterations -- which is exactly the shape these tests watch.
 */
class CountingQueriesStatement extends \PDOStatement
{
    /** PDO requires the statement class constructor to be non-public. */
    protected function __construct()
    {
    }

    /** @param array<array-key, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        CountingQueriesPdo::$count++;
        CountingQueriesPdo::$statements[] = (string)preg_replace('/\s+/', ' ', trim($this->queryString));

        return parent::execute($params);
    }
}

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
     * DbService used to have a `readCache` that was cleared here for the same reason -- except
     * it was only ever cleared, never populated, so it memoised nothing and this reset did
     * nothing either. Both are gone.
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
            // so prepared statements are counted too -- see CountingQueriesStatement
            $link->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [CountingQueriesStatement::class, []]);
            (new \ReflectionProperty($dbService, 'link'))->setValue($dbService, $link);
        }

        return self::$count;
    }
}

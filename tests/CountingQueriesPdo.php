<?php

namespace YesWiki\Test;

/** The statement half of the counter. */
class CountingQueriesStatement extends \PDOStatement
{
    /** PDO requires the statement class constructor to be non-public. */
    protected function __construct()
    {
    }

    /**
     * @param array<array-key, mixed>|null $params
     */
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

    /**
     * @return list<string> the statements issued since $fromCount
     */
    public static function statementsSince(int $fromCount): array
    {
        return array_slice(self::$statements, $fromCount);
    }

    /** Install it into DbService if it is not already there, and return the current count. */
    public static function countFor(\YesWiki\Kernel\Service\DbService $dbService): ?int
    {
        $link = (new \ReflectionProperty($dbService, 'link'))->getValue($dbService);

        if (!$link instanceof self) {
            if ($dbService->getDriver() !== 'sqlite') {
                return null;
            }
            $link = new self('sqlite:private/yeswiki.db');
            $link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $link->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [CountingQueriesStatement::class, []]);
            (new \ReflectionProperty($dbService, 'link'))->setValue($dbService, $link);
        }

        return self::$count;
    }
}

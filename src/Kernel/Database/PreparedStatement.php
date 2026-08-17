<?php

namespace YesWiki\Kernel\Database;

/** One statement prepared once and executed many times. */
final class PreparedStatement
{
    /**
     * @var callable(string, float, array<array-key, mixed>): void
     */
    private $log;

    /**
     * @param callable(string, float, array<array-key, mixed>): void $log receives the SQL,
     *                                                                    the elapsed seconds and the bound values, and decides
     *                                                                    whether the query log wants them
     */
    public function __construct(
        private readonly \PDOStatement $statement,
        private readonly string $sql,
        callable $log,
    ) {
        $this->log = $log;
    }

    /**
     * Bind $params and run.
     *
     * @param array<array-key, mixed> $params
     *
     * @throws \Exception with the statement named, matching DbService::query()
     */
    public function execute(array $params = []): \PDOStatement
    {
        $start = microtime(true);

        try {
            SqlParameters::bind($this->statement, $params);
            $this->statement->execute();
        } catch (\PDOException $failed) {
            throw new \Exception($failed->getMessage() . ' -- while running: ' . $this->sql, (int)$failed->getCode(), $failed);
        } finally {
            ($this->log)($this->sql, microtime(true) - $start, $params);
        }

        return $this->statement;
    }

    /**
     * Execute and take every row, for a statement re-run with different values in a loop.
     *
     * @param array<array-key, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(array $params = []): array
    {
        return array_values($this->execute($params)->fetchAll(\PDO::FETCH_ASSOC));
    }
}

<?php

namespace YesWiki\Kernel\Database;

/**
 * One statement prepared once and executed many times.
 *
 * `DbService::query($sql, $params)` prepares, executes and discards -- right for the single
 * query that answers a request, wasteful for a loop. The search reindexer writes one row per
 * ACL bucket per Content across a wiki of hundreds of thousands of them; preparing that
 * INSERT once and executing it per row is the difference between the database parsing one
 * statement and parsing every batch.
 *
 * It also removes the reason those loops built giant literal statements in the first place.
 * Concatenating 100 tuples into one INSERT was how they amortised the parse, and it is why
 * `SearchIndexer::INSERT_BATCH` had to be "kept modest: some hosts cap max_allowed_packet
 * hard". A bound statement does not grow with the data, so neither concern applies.
 *
 * Unlike the dialects beside it this does hold connection state -- a prepared statement is
 * bound to the connection that prepared it by definition. Get one from `DbService::prepare()`
 * rather than constructing it, and do not keep one past the request.
 */
final class PreparedStatement
{
    /** @var callable(string, float, array<array-key, mixed>): void */
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
     * Bind $params and run. Returns the underlying statement so a caller that wants
     * `rowCount()` or a cursor can have it.
     *
     * Every execution is logged separately: a loop of 500 inserts is 500 queries, and a debug
     * footer that reported one would hide exactly the cost this class exists to reduce.
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
            // The SQL goes in whole: a prepared statement's text carries placeholders, never
            // data, so there is nothing here to keep away from whoever is looking.
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
        // array_values() rather than a cast or an annotation: PDO's own signature promises
        // only `array`, and this method promises a list. Re-keying is what makes that true
        // instead of asserted, and costs nothing next to materialising the rows themselves.
        return array_values($this->execute($params)->fetchAll(\PDO::FETCH_ASSOC));
    }
}

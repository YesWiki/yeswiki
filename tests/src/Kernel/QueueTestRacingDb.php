<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\DbService;

/**
 * A connection whose next look for a waiting message by its key finds nothing, as if the sender
 * that wrote it had committed only after that look: the race of two senders, played on one connection.
 * It can also have its next transaction throw, as a database gone away would.
 */
final class QueueTestRacingDb extends DbService
{
    private bool $missNextLookup = false;
    private bool $missEveryLookup = false;
    private bool $failNextTransaction = false;

    public function missNextWaitingLookup(): void
    {
        $this->missNextLookup = true;
    }

    /** Every look for a waiting message by its key finds nothing from now on: the winner keeps vanishing between the look and the insert. */
    public function missEveryWaitingLookup(): void
    {
        $this->missEveryLookup = true;
    }

    /** The next transaction throws before it runs anything, as a connection lost or a deadlock retried in vain would. */
    public function failNextTransaction(): void
    {
        $this->failNextTransaction = true;
    }

    public function transactional(callable $work, int $attempts = 1): mixed
    {
        if ($this->failNextTransaction) {
            $this->failNextTransaction = false;

            throw new \RuntimeException('the database went away');
        }

        return parent::transactional($work, $attempts);
    }

    public function scalar(string $query, mixed $default = null, array $params = []): mixed
    {
        if ($this->missEveryLookup && str_contains($query, 'dedup_key = ?')) {
            return $default;
        }
        if ($this->missNextLookup && str_contains($query, 'dedup_key = ?')) {
            $this->missNextLookup = false;

            return $default;
        }

        return parent::scalar($query, $default, $params);
    }
}

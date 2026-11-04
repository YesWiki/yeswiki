<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\DbService;

/**
 * A connection whose next look for a waiting message by its key finds nothing, as if the sender
 * that wrote it had committed only after that look: the race of two senders, played on one connection.
 */
final class QueueTestRacingDb extends DbService
{
    private bool $missNextLookup = false;
    private bool $missEveryLookup = false;

    public function missNextWaitingLookup(): void
    {
        $this->missNextLookup = true;
    }

    /** Every look for a waiting message by its key finds nothing from now on: the winner keeps vanishing between the look and the insert. */
    public function missEveryWaitingLookup(): void
    {
        $this->missEveryLookup = true;
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

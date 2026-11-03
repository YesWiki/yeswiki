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

    public function missNextWaitingLookup(): void
    {
        $this->missNextLookup = true;
    }

    public function scalar(string $query, mixed $default = null, array $params = []): mixed
    {
        if ($this->missNextLookup && str_contains($query, 'dedup_key = ?')) {
            $this->missNextLookup = false;

            return $default;
        }

        return parent::scalar($query, $default, $params);
    }
}

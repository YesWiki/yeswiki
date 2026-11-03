<?php

namespace YesWiki\Kernel\Service;

use Psr\Clock\ClockInterface;

/**
 * The clock of the database: what every service that writes a time to it, or compares one it reads, goes by.
 *
 * The processes of a farm may run on several machines whose clocks need not agree; the database
 * they share is the one reference they all see. Its time is read in UTC (DbService::utcNow()),
 * whatever the time zone of the database or of its session, which on shared hosting is often
 * not PHP's. A time measured within one process only -- what a pass may still spend -- has no
 * need of it, and stays on PHP's clock (QueueBudgetFactory).
 *
 * Each call asks the database: one short query, beside the ones the caller is about to send.
 */
class DatabaseClock implements ClockInterface
{
    public function __construct(private readonly DbService $db)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->db->utcNow();
    }
}

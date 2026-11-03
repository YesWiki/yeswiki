<?php

namespace YesWiki\Kernel\Service;

use Psr\Clock\ClockInterface;

/**
 * The clock of the database, in UTC: what every service that writes a time to it, or compares one it reads, goes by.
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

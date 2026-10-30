<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Asks the transport not to queue this message if one with the same key is still waiting.
 *
 * For work that is idempotent and only needs doing once more, however many times it was asked
 * for: draining the search queue, a scheduled task. Once the waiting message has been taken, the
 * key is free again, so whatever arrived during its run gets a pass of its own.
 */
final class UniqueStamp implements StampInterface
{
    public function __construct(public readonly string $key)
    {
    }
}

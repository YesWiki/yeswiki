<?php

namespace YesWiki\Kernel\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * Applies the retention of `failed`: a month.
 *
 * Long enough for an admin who checks in now and then to see a failure and act on it; past that,
 * nobody will. The runs and the passes are rows of the Journal's `queue` channel, which the
 * Journal's own daily prune keeps for a week (Journal::QUEUE_PURGE_SETTING).
 */
#[AsMessageHandler]
class PruneQueueHandler
{
    /** Days a message waits in `failed` before it is dropped. */
    public const FAILED_DAYS = 30;

    public function __construct(
        private readonly QueueTransport $transport,
    ) {
    }

    public function __invoke(PruneQueue $task): void
    {
        $this->transport->pruneFailed(self::FAILED_DAYS);
    }
}

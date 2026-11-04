<?php

namespace YesWiki\Kernel\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * Applies the retention of `failed`: a month.
 */
#[AsMessageHandler]
class PruneQueueHandler
{
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

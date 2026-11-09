<?php

namespace YesWiki\Kernel\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Applies the retention of `failed` (a month) and of the queue's history (a week by default, `queue_history_purge_time`).
 */
#[AsMessageHandler]
class PruneQueueHandler
{
    public const FAILED_DAYS = 30;

    public const HISTORY_DAYS_SETTING = 'queue_history_purge_time';

    private const HISTORY_DAYS = 7;

    public function __construct(
        private readonly QueueTransport $transport,
        private readonly QueueRecorder $recorder,
        private readonly RuntimeConfig $config,
    ) {
    }

    public function __invoke(PruneQueue $task): void
    {
        $this->transport->pruneFailed(self::FAILED_DAYS);

        $days = (int)($this->config[self::HISTORY_DAYS_SETTING] ?? self::HISTORY_DAYS);
        if ($days > 0) {
            $this->recorder->prune($days);
        }
    }
}

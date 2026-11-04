<?php

namespace YesWiki\Import\Message;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\SyncScheduler;

/**
 * Imports one data source, and records what happened for the importers page whether it worked or not;
 * a failure is logged and thrown again for the queue to retry.
 */
#[AsMessageHandler]
final class SyncDataSourceHandler
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly ImporterManager $importers,
        private readonly SyncScheduler $scheduler,
    ) {
    }

    public function __invoke(SyncDataSource $task): void
    {
        $dataSources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];
        $options = is_array($dataSources) ? ($dataSources[$task->sourceId] ?? null) : null;
        if (!is_array($options)) {
            throw new UnrecoverableMessageHandlingException("The data source '{$task->sourceId}' is not in the configuration any more");
        }

        ob_start();
        try {
            $result = $this->importers->syncSource($task->sourceId, $options);
        } catch (\Throwable $failed) {
            $this->scheduler->recordRun($task->sourceId, trim(ob_get_clean() . "\n" . $failed->getMessage()));

            throw $failed;
        }
        $this->scheduler->recordRun($task->sourceId, trim(ob_get_clean() . "\n" . $result));
    }
}

<?php

namespace YesWiki\Import\Message;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\SyncLog;

/**
 * Imports one data source, and records what happened for the importers page whether it worked or not;
 * a failure is logged and thrown again for the queue to retry. A source removed from the configuration meanwhile is skipped.
 */
#[AsMessageHandler]
final class SyncDataSourceHandler
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly ImporterManager $importers,
        private readonly SyncLog $log,
    ) {
    }

    public function __invoke(SyncDataSource $task): void
    {
        $dataSources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];
        $options = is_array($dataSources) ? ($dataSources[$task->sourceId] ?? null) : null;
        if (!is_array($options)) {
            return; // removed from the configuration while its sync waited: nothing to sync, nothing failed
        }

        ob_start();
        try {
            $result = $this->importers->syncSource($task->sourceId, $options);
        } catch (\Throwable $failed) {
            $this->log->recordRun($task->sourceId, trim(ob_get_clean() . "\n" . $failed->getMessage()));

            throw $failed;
        }
        $this->log->recordRun($task->sourceId, trim(ob_get_clean() . "\n" . $result));
    }
}

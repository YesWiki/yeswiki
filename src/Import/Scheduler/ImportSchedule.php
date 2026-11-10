<?php

namespace YesWiki\Import\Scheduler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Import\Message\SyncDataSource;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/**
 * The automatic import of data sources: one recurring task per source flagged `syncOnMaintenance`,
 * every `syncIntervalInMin` minutes but never more often than the queue ticks for.
 */
class ImportSchedule implements ProvidesSchedule
{
    public function __construct(
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function recurringMessages(): array
    {
        $dataSources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];
        if (!is_array($dataSources)) {
            return [];
        }

        $recurring = [];
        foreach ($dataSources as $id => $options) {
            if (!is_array($options) || empty($options['syncOnMaintenance'])) {
                continue;
            }

            $recurring[] = RecurringMessage::every(max(KernelSchedule::HOUSEKEEPING_EVERY, (int)($options['syncIntervalInMin'] ?? 0) * 60), new SyncDataSource((string)$id));
        }

        return $recurring;
    }
}

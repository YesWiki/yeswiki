<?php

namespace YesWiki\Import\Scheduler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use YesWiki\Import\Message\SyncDataSource;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\Journal;

/**
 * The automatic import of data sources: one recurring task per source flagged `syncOnMaintenance`,
 * every `syncIntervalInMin` minutes but never more often than every half hour.
 */
class ImportSchedule implements ProvidesSchedule
{
    private const MIN_INTERVAL = 1800;

    private const FIRST_SYNC_DELAY_SECONDS = 60;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly Journal $journal,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /** A source whose task id would not fit the queue is left out and journaled, never thrown. */
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
            $message = new SyncDataSource((string)$id);
            if (!self::fits($message)) {
                $this->journal->error("The data source '{$id}' is not synced automatically: its name is too long for a scheduled task (" . (ScheduledTask::MAX_ID_LENGTH - strlen('sync-')) . ' characters at most)', [
                    'action' => 'import.schedule_failed',
                ]);

                continue;
            }

            $recurring[] = RecurringMessageTimezoned::every(max(self::MIN_INTERVAL, (int)($options['syncIntervalInMin'] ?? 0) * 60), $message);
        }

        return $recurring;
    }

    /**
     * Queues the first sync of a source just set (or created) to sync itself, rather than waiting an interval.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>      $after
     */
    public function sourceSaved(string $id, ?array $before, array $after): void
    {
        if (empty($after['syncOnMaintenance']) || !empty($before['syncOnMaintenance'])) {
            return;
        }
        $message = new SyncDataSource($id);
        if (!self::fits($message)) {
            return;
        }

        $this->bus->dispatch($message, [new DelayStamp(self::FIRST_SYNC_DELAY_SECONDS * 1000)]);
    }

    /** Whether the queue can key the task by its id; recurringMessages() journals one that cannot. */
    private static function fits(SyncDataSource $message): bool
    {
        return strlen($message->taskId()) <= ScheduledTask::MAX_ID_LENGTH;
    }
}

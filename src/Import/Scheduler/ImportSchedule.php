<?php

namespace YesWiki\Import\Scheduler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Import\Message\SyncDataSource;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\Journal;

/**
 * The automatic import of data sources: one recurring task per source flagged `syncOnMaintenance`.
 *
 * A source runs every `syncIntervalInMin` minutes, and never more often than every half hour,
 * which is how often the housekeeping pass that this replaces came round: a source with no
 * interval of its own went at each pass.
 *
 * The interval is counted from the start of the schedule, which the Scheduler's checkpoint keeps,
 * as for every periodic task; nothing else is read. A sync run by hand (the button,
 * `importer:sync`, `{{sync}}`, the API) moves nothing, and one that failed comes back an interval
 * later. A source just set to sync itself does not wait a whole interval for its first sync:
 * the importers page queues one when it saves it (sourceSaved()).
 *
 * The queue builds the schedule anew at every due tick, from what is configured then, and
 * forgets the tasks that are no longer declared: a source deleted or unflagged leaves the jobs
 * page.
 */
class ImportSchedule implements ProvidesSchedule
{
    /** Seconds under which a source is not run again, whatever its interval says. */
    private const MIN_INTERVAL = 1800;

    /**
     * How long the first sync of a source just set to sync itself waits.
     *
     * The pass at the end of the request that saved it, and a worker that has not yet noticed the
     * configuration changed (QueueConsumer asks every 5 seconds), still read the source as it
     * was; a request's pass lasts 20 seconds at most.
     */
    private const FIRST_SYNC_DELAY_SECONDS = 60;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly Journal $journal,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * A source whose task id would not fit the queue is left out and journaled, never thrown: one source must not take the other sources out of the schedule with it, as a provider that throws would (the queue then leaves all of this provider's tasks out of the tick, and keeps their rows as they were).
     */
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

            $recurring[] = RecurringMessage::every(max(self::MIN_INTERVAL, (int)($options['syncIntervalInMin'] ?? 0) * 60), $message);
        }

        return $recurring;
    }

    /**
     * The importers page saved a source: if it has just been set to sync itself (or was created so), its first sync is queued now instead of an interval from now.
     *
     * @param array<string, mixed>|null $before the source as it was configured, null for a new one
     * @param array<string, mixed>      $after  the source as it is saved
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

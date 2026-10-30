<?php

namespace YesWiki\Import\Scheduler;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Import\Message\SyncDataSource;
use YesWiki\Import\Service\SyncScheduler;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringAfterLastRun;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\Journal;

/**
 * The automatic import of data sources: one recurring task per source flagged `syncOnMaintenance`.
 *
 * A source runs every `syncIntervalInMin` minutes, and never more often than every half hour,
 * which is how often the housekeeping pass that this replaces came round: a source with no
 * interval of its own went at each pass.
 *
 * The queue builds the schedule anew at every due tick, from what is configured then, and
 * forgets the tasks that are no longer declared: a source deleted or unflagged leaves the jobs
 * page. A source starts an interval after its last run, as RecurringAfterLastRun says, which is
 * the modification time of its log (SyncScheduler::recordRun writes it when a sync ends, well or
 * badly): an upgrade does not import again what has just been imported, and a sync run by hand
 * or by cron postpones the scheduled one.
 */
class ImportSchedule implements ProvidesSchedule
{
    /** Seconds under which a source is not run again, whatever its interval says. */
    private const MIN_INTERVAL = 1800;

    private ClockInterface $clock;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly SyncScheduler $scheduler,
        private readonly Journal $journal,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * A source that cannot be scheduled is left out and journaled, never thrown: one source whose log cannot be read must not take the other sources out of the schedule with it, as a provider that throws would (the queue then leaves all of this provider's tasks out of the tick, and keeps their rows as they were).
     */
    public function recurringMessages(): array
    {
        $dataSources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];
        if (!is_array($dataSources)) {
            return [];
        }

        $now = $this->clock->now();
        $recurring = [];
        foreach ($dataSources as $id => $options) {
            if (!is_array($options) || empty($options['syncOnMaintenance'])) {
                continue;
            }
            $message = new SyncDataSource((string)$id);
            if (strlen($message->taskId()) > ScheduledTask::MAX_ID_LENGTH) {
                $this->journal->error("The data source '{$id}' is not synced automatically: its name is too long for a scheduled task (" . (ScheduledTask::MAX_ID_LENGTH - strlen('sync-')) . ' characters at most)', [
                    'action' => 'import.schedule_failed',
                ]);

                continue;
            }

            try {
                $interval = max(self::MIN_INTERVAL, (int)($options['syncIntervalInMin'] ?? 0) * 60);
                $recurring[] = RecurringAfterLastRun::every($interval, $message, $this->scheduler->lastSyncTime((string)$id), $now);
            } catch (\Throwable $unreadable) {
                $this->journal->error("The data source '{$id}' is not synced automatically: its last run cannot be read: {$unreadable->getMessage()}", [
                    'action' => 'import.schedule_failed',
                    'exception' => $unreadable,
                ]);
            }
        }

        return $recurring;
    }
}

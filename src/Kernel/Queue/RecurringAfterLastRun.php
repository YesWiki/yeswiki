<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Scheduler\RecurringMessage;

/**
 * A recurring task that starts one period after its last run, for a ProvidesSchedule whose tasks remember when they last ran (a digest's marker, a data source's log).
 *
 * So that an upgrade does not run again what has just run, a run by hand or by cron postpones the
 * scheduled one, and a task that fails comes back a period later and not at every tick. It is the
 * last run plus the period and not the last run itself: a recurring message that starts in the
 * future first runs at its start, so starting at the last run would run it again at the very next
 * tick.
 *
 * A task that is already overdue (never run, or last run more than a period ago) starts now, by
 * the provider's clock, which is the ticker's: the schedule is built just before the Scheduler
 * reads the time, so the run is due at that tick (spec section 8, a late task runs once at the
 * next pass).
 */
final class RecurringAfterLastRun
{
    /**
     * @param int|null $lastRunAt a Unix time; null when the task never ran
     */
    public static function every(int $seconds, object $message, ?int $lastRunAt, \DateTimeImmutable $now): RecurringMessage
    {
        $dueAt = $lastRunAt === null ? null : $now->setTimestamp($lastRunAt + $seconds);

        return RecurringMessage::every($seconds, $message, $dueAt !== null && $dueAt > $now ? $dueAt : $now);
    }
}

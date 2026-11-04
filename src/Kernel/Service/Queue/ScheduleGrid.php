<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * Where the runs of a recurring task fall. A task declared with every() runs on fixed slots in its timezone, never a period
 * after its last run, so a late tick does not push the next one back: every 30 minutes is at :00 and :30, every 24 hours at midnight.
 * A period of a day or less restarts at each midnight, on the wall clock (45 minutes: 0:00, 0:45 ... 23:15, then 0:00); a longer one
 * counts from a fixed midnight, in whole days when it is some. A time the clock skips in spring is the hour after it; the hour it
 * repeats in autumn gets no slots of its own. Any other trigger (cron()) keeps its own times. every() takes a fixed length: a calendar
 * period (a month) is a cron(). The timezone is the one the task is declared in (RecurringMessageTimezoned), which always exists.
 */
final class ScheduleGrid
{
    private const DAY = 86400;

    /** The midnight a period longer than a day counts from, a Monday, in the task's timezone. */
    private const REFERENCE = '2000-01-03 00:00:00';

    /**
     * The first run of that trigger strictly after $after, on the wall clock of $timezone; null when it has none, being past its end.
     * The trigger is left as it was.
     */
    public function nextRun(TriggerInterface $trigger, \DateTimeImmutable $after, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $every = $this->everySeconds($trigger);
        if ($every === null) {
            return $trigger->getNextRunDate($after);
        }

        $slot = match (true) {
            $every <= self::DAY => $this->withinTheDay($every, $after, $timezone),
            $every % self::DAY === 0 => $this->inWholeDays(intdiv($every, self::DAY), $after, $timezone),
            default => $this->fromTheReference($every, $after, $timezone),
        };

        return (clone $trigger)->getNextRunDate($slot->modify('-1 second')) === null ? null : $slot;
    }

    /** How long the period of an every() trigger is, in seconds; null for any other trigger, or one that has ended. */
    public function everySeconds(TriggerInterface $trigger): ?int
    {
        if (!$trigger instanceof PeriodicalTrigger) {
            return null;
        }
        $probe = clone $trigger;
        $first = $probe->getNextRunDate($this->reference(new \DateTimeZone('UTC')));
        $second = $first === null ? null : $probe->getNextRunDate($first);

        return $second === null ? null : $second->getTimestamp() - $first->getTimestamp();
    }

    /** The next slot of the day on the wall clock, k periods after its midnight; the next midnight once the day has no more. */
    private function withinTheDay(int $every, \DateTimeImmutable $after, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $local = $after->setTimezone($timezone);
        $midnight = $local->setTime(0, 0);
        $elapsed = (int)$local->format('G') * 3600 + (int)$local->format('i') * 60 + (int)$local->format('s');
        $base = $local->getTimestamp();

        for ($wall = (intdiv($elapsed, $every) + 1) * $every; $wall < self::DAY; $wall += $every) {
            $slot = $this->onTheWallClock($midnight, $wall, $base + $wall - $elapsed, $after);
            if ($slot !== null) {
                return $slot;
            }
        }

        return $midnight->modify('+1 day');
    }

    /**
     * That time of the day, the first instant after $after that shows it: in the offset of $after when the clock repeats an hour,
     * the hour after when the clock skips it; null when the day shows it only before $after.
     */
    private function onTheWallClock(\DateTimeImmutable $midnight, int $wall, int $sameOffset, \DateTimeImmutable $after): ?\DateTimeImmutable
    {
        $resolved = $midnight->setTime(intdiv($wall, 3600), intdiv($wall % 3600, 60), $wall % 60);
        $shown = $resolved->format('H:i:s');
        $candidates = [(new \DateTimeImmutable('@' . $sameOffset))->setTimezone($midnight->getTimezone()), $resolved];

        $earliest = null;
        foreach ($candidates as $candidate) {
            if ($candidate > $after && $candidate->format('H:i:s') === $shown && ($earliest === null || $candidate < $earliest)) {
                $earliest = $candidate;
            }
        }

        return $earliest ?? ($resolved > $after ? $resolved : null);
    }

    /** The next midnight a whole number of days after the reference. */
    private function inWholeDays(int $days, \DateTimeImmutable $after, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $reference = $this->reference($timezone);
        $passed = (int)$reference->diff($after->setTimezone($timezone)->setTime(0, 0))->days;

        return $reference->modify('+' . ((intdiv($passed, $days) + 1) * $days) . ' days');
    }

    /** The next slot a whole number of periods after the reference, in elapsed time. */
    private function fromTheReference(int $every, \DateTimeImmutable $after, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $reference = $this->reference($timezone);
        $periods = intdiv($after->getTimestamp() - $reference->getTimestamp(), $every) + 1;

        return (new \DateTimeImmutable('@' . ($reference->getTimestamp() + $periods * $every)))->setTimezone($timezone);
    }

    private function reference(\DateTimeZone $timezone): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::REFERENCE, $timezone);
    }
}

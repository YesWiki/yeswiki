<?php

namespace YesWiki\Kernel\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\CheckpointInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGeneratorInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\StatefulTriggerInterface;

/**
 * Symfony's MessageGenerator, for one tick, over a checkpoint of the wiki's own (messenger / scheduler).
 *
 * Symfony's generator takes a CheckpointInterface in its constructor but declares its private
 * checkpoint() as returning its own final Checkpoint class (symfony/scheduler 7.4 and 8.0), so
 * any other implementation fails with a TypeError on the first call; and that Checkpoint wants a
 * cache pool and symfony/lock, which the wiki does not ship. This is the same loop with the same
 * rules -- runs before the saved time are skipped, a run at the saved time is skipped up to the
 * saved index, `processOnlyLastMissedRun` dispatches only the last of the missed runs, a trigger
 * that does not move forward is an error -- written against the interface. It keeps nothing
 * between ticks (no heap, no "wait until"), since QueueScheduleTicker builds one per due tick
 * from the schedule as it is then; and for the same reason it always probes one microsecond
 * before the saved time, as Symfony's does on its first build, so that the messages due at that
 * very time and not yet dispatched still go.
 *
 * Should Symfony's generator accept other checkpoints one day, this class can give way to it.
 */
final class ScheduleGenerator implements MessageGeneratorInterface
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly string $name,
        private readonly ClockInterface $clock,
        private readonly CheckpointInterface $checkpoint,
    ) {
    }

    /**
     * The messages due now, each with the context of its run; nothing when another process holds the checkpoint.
     *
     * The checkpoint is saved after each run's messages and released once all are out, with the
     * time the earliest next run is due.
     *
     * @return \Generator<MessageContext, object>
     */
    public function getMessages(): \Generator
    {
        $now = $this->clock->now();
        if (!$this->checkpoint->acquire($now)) {
            return;
        }

        $lastTime = $this->checkpoint->time();
        $lastIndex = $this->checkpoint->index();
        $runs = $this->firstRuns($this->checkpoint->from(), $lastTime, $lastIndex);

        while (($index = self::earliest($runs)) !== null && $runs[$index][0] <= $now) {
            [$time, $recurring] = $runs[$index];
            unset($runs[$index]);
            $trigger = $recurring->getTrigger();
            $yield = true;

            if ($time < $lastTime) {
                $time = $lastTime;
                $yield = false;
            } elseif ($time == $lastTime && $index <= $lastIndex) {
                $yield = false;
            }

            $nextTime = $trigger->getNextRunDate($time);
            if ($nextTime !== null) {
                if ($nextTime <= $time) {
                    throw new LogicException(\sprintf('The "%s" trigger does not move the run date forward. Its "getNextRunDate()" method must return a date strictly after the given one.', $trigger));
                }
                $runs[$index] = [$nextTime, $recurring];

                if ($nextTime <= $now && $this->schedule->shouldProcessOnlyLastMissedRun()) {
                    $yield = false;
                }
            }

            if ($yield) {
                $context = new MessageContext($this->name, $recurring->getId(), $trigger, $time, $nextTime);
                try {
                    foreach ($recurring->getMessages($context) as $message) {
                        yield $context => $message;
                    }
                } finally {
                    $this->checkpoint->save($time, $index);
                }
            }
        }

        $next = self::earliest($runs);
        $this->checkpoint->release($now, $next === null ? null : $runs[$next][0]);
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    /**
     * The first run of each recurring message after the saved time, by its position in the schedule.
     *
     * @return array<int, array{\DateTimeImmutable, RecurringMessage}>
     */
    private function firstRuns(\DateTimeImmutable $startTime, \DateTimeImmutable $lastTime, int $lastIndex): array
    {
        $probeTime = $lastIndex >= 0 ? $lastTime->modify('-1 microsecond') : $lastTime;
        $runs = [];

        foreach ($this->schedule->getRecurringMessages() as $index => $recurring) {
            $trigger = $recurring->getTrigger();
            if ($trigger instanceof StatefulTriggerInterface) {
                $trigger->continue($startTime);
            }

            $nextTime = $trigger->getNextRunDate($probeTime);
            if ($nextTime !== null) {
                $runs[$index] = [$nextTime, $recurring];
            }
        }

        return $runs;
    }

    /**
     * The position of the run due first, the lower position first on a tie (Symfony's heap order); null when none is left.
     *
     * @param array<int, array{\DateTimeImmutable, RecurringMessage}> $runs
     */
    private static function earliest(array $runs): ?int
    {
        $earliest = null;
        foreach ($runs as $index => [$time]) {
            if ($earliest === null || $time < $runs[$earliest][0] || ($time == $runs[$earliest][0] && $index < $earliest)) {
                $earliest = $index;
            }
        }

        return $earliest;
    }
}

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
 * Symfony's MessageGenerator loop, for one tick, written against CheckpointInterface: Symfony's own only accepts
 * its final Checkpoint class (symfony/scheduler 7.4, 8.0). It can give way to Symfony's should that change.
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

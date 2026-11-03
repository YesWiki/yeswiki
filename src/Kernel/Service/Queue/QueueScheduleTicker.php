<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\ScheduleGenerator;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;

/**
 * Turns the recurring tasks that are due into messages in the queue, and does nothing else (messenger / scheduler).
 * What is due follows Symfony's rules with `processOnlyLastMissedRun`; progress lives in QueueCheckpoint, in the database.
 * The schedule stands still while the wiki is hibernated; tick() returns at once while the next due time is ahead.
 */
class QueueScheduleTicker
{
    private const NAME = 'yeswiki';

    private const RECHECK_SECONDS = 60;

    private ?\DateTimeImmutable $dueAt = null;

    private ?\DateTimeImmutable $learntAt = null;

    /**
     * @param iterable<ProvidesSchedule> $providers
     */
    public function __construct(
        #[AutowireIterator('yeswiki.schedule_provider')]
        private readonly iterable $providers,
        private readonly QueueBus $bus,
        private readonly QueueCheckpoint $checkpoint,
        private readonly QueueRecorder $recorder,
        private readonly ClockInterface $clock,
        private readonly HibernationService $hibernation,
        private readonly ?Journal $journal = null,
    ) {
    }

    /** Queues the runs that are due, and answers how many; throws when the checkpoint cannot be read or written. */
    public function tick(): int
    {
        $now = $this->clock->now();
        if ($this->dueAt !== null && $now < $this->dueAt && !$this->isStale($now)) {
            return 0;
        }

        try {
            $this->learn($this->checkpoint->dueAt(), $now);
            if ($this->dueAt !== null && $now < $this->dueAt) {
                return 0;
            }
            if ($this->hibernation->isWikiHibernatedNow()) {
                $this->learn($now->modify('+' . self::RECHECK_SECONDS . ' seconds'), $now);

                return 0;
            }

            $dispatched = $this->dispatchDue();
            $this->learn($this->checkpoint->dueAt(), $now);

            return $dispatched;
        } catch (\Throwable $failed) {
            $this->learn($now->modify('+' . self::RECHECK_SECONDS . ' seconds'), $now);

            throw $failed;
        } finally {
            $this->checkpoint->unlock();
        }
    }

    /** Every recurring task declared, built anew on each call; a provider that throws is journaled and left out. */
    public function schedule(): Schedule
    {
        return $this->build()[0];
    }

    /**
     * The ids of the tasks the providers declare now, and while a provider throws every task row recorded.
     *
     * @return list<string>
     */
    public function declaredTaskIds(): array
    {
        [$schedule, $complete] = $this->build();
        $ids = $this->idsOf($schedule);

        return $complete ? $ids : array_values(array_unique([...$ids, ...$this->recorder->taskIds()]));
    }

    /** Queues a run of that task now, leaving its schedule as it is; false when no such task is declared or it could not be queued. */
    public function runNow(string $taskId): bool
    {
        $now = $this->clock->now();
        foreach ($this->tasksOf($this->schedule(), $now) as [$message, $trigger]) {
            if ($message->taskId() === $taskId) {
                return $this->dispatch($message, self::periodOf($trigger, $now), null);
            }
        }

        return false;
    }

    /**
     * The schedule the providers declare now, and whether all of them could say.
     *
     * @return array{Schedule, bool}
     */
    private function build(): array
    {
        $schedule = (new Schedule())->processOnlyLastMissedRun(true);
        $complete = true;
        foreach ($this->providers as $provider) {
            try {
                $recurring = $provider->recurringMessages();
            } catch (\Throwable $failed) {
                $complete = false;
                $this->journal?->error($provider::class . ' could not say what it schedules; its tasks are kept as they were: ' . $failed->getMessage(), [
                    'action' => 'queue.schedule_provider_failed',
                    'target' => $provider::class,
                    'exception' => $failed,
                ]);

                continue;
            }
            foreach ($recurring as $message) {
                try {
                    $schedule->add($message);
                } catch (LogicException $twice) {
                    $this->journal?->error($provider::class . ' declares a scheduled task twice; it is scheduled once: ' . $twice->getMessage(), [
                        'action' => 'queue.schedule_provider_failed',
                        'target' => $provider::class,
                        'exception' => $twice,
                    ]);
                }
            }
        }

        return [$schedule, $complete];
    }

    /** Runs the generator over the schedule as it is now, and queues what it yields. */
    private function dispatchDue(): int
    {
        [$schedule, $complete] = $this->build();
        $generator = new ScheduleGenerator($schedule, self::NAME, $this->clock, $this->checkpoint);
        $dispatched = 0;

        foreach ($generator->getMessages() as $context => $message) {
            $every = $context->nextTriggerAt === null ? 0 : $context->nextTriggerAt->getTimestamp() - $context->triggeredAt->getTimestamp();
            if ($this->dispatch($message, $every, $context->nextTriggerAt)) {
                $dispatched++;
            }
        }
        if ($complete) {
            $this->recorder->forgetTasksExcept($this->idsOf($schedule));
        }

        return $dispatched;
    }

    /** Sends one run to the queue, a scheduled task recorded on its row; true once queued, or when that task already waits. */
    private function dispatch(object $message, int $everySeconds, ?\DateTimeImmutable $nextDue): bool
    {
        if (!$message instanceof ScheduledTask) {
            return $this->bus->dispatch($message)->last(TransportMessageIdStamp::class) !== null;
        }

        $queued = $this->bus->dispatch($message)->last(TransportMessageIdStamp::class) !== null;
        if ($queued) {
            $this->recorder->taskDispatched($message, $everySeconds, $nextDue);
        }

        return $queued;
    }

    /** @return list<string> */
    private function idsOf(Schedule $schedule): array
    {
        $ids = [];
        foreach ($this->tasksOf($schedule, $this->clock->now()) as [$message]) {
            $ids[] = $message->taskId();
        }

        return array_values(array_unique($ids));
    }

    /**
     * The messages of that schedule that are scheduled tasks, each with the trigger it recurs on.
     *
     * @return \Generator<int, array{ScheduledTask, TriggerInterface}>
     */
    private function tasksOf(Schedule $schedule, \DateTimeImmutable $now): \Generator
    {
        foreach ($schedule->getRecurringMessages() as $recurring) {
            $trigger = $recurring->getTrigger();
            foreach ($recurring->getMessages(new MessageContext(self::NAME, $recurring->getId(), $trigger, $now)) as $message) {
                if ($message instanceof ScheduledTask) {
                    yield [$message, $trigger];
                }
            }
        }
    }

    private function learn(?\DateTimeImmutable $dueAt, \DateTimeImmutable $now): void
    {
        $this->dueAt = $dueAt;
        $this->learntAt = $now;
    }

    private function isStale(\DateTimeImmutable $now): bool
    {
        return $this->learntAt === null || $now->getTimestamp() - $this->learntAt->getTimestamp() >= self::RECHECK_SECONDS;
    }

    /** How far apart a trigger's runs are, from two of them after $now; 0 when it will not fire twice more. */
    private static function periodOf(TriggerInterface $trigger, \DateTimeImmutable $now): int
    {
        $next = $trigger->getNextRunDate($now);
        $after = $next === null ? null : $trigger->getNextRunDate($next);

        return $next === null || $after === null ? 0 : $after->getTimestamp() - $next->getTimestamp();
    }
}

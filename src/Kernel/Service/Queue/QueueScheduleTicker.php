<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\ScheduleGenerator;
use YesWiki\Kernel\Queue\UniqueStamp;
use YesWiki\Kernel\Service\Journal;

/**
 * Turns the recurring tasks that are due into messages in the queue, and does nothing else (messenger / scheduler).
 *
 * The Scheduler here never runs a task: it produces, the queue consumes. QueueConsumer calls
 * tick() at the start of each pass and whenever a blocking consumer finds the queue empty, so
 * every mode gets its scheduled work the same way, as messages with retries, history and
 * failures like any other. A task's message is sent with a UniqueStamp on its id, so a run that
 * still waits (a wiki where passes are rare) absorbs the next one instead of piling up.
 *
 * What is due is decided by Symfony's rules (ScheduleGenerator, the loop of its MessageGenerator)
 * over the RecurringMessages of every ProvidesSchedule service, with `processOnlyLastMissedRun`:
 * a task that missed ten runs while nothing ticked runs once, not ten times (spec §8). Its
 * progress lives in QueueCheckpoint, in the database, so a request, the command and the next
 * request share one schedule.
 *
 * tick() has to be cheap, since a blocking consumer calls it about once a second. When the next
 * due time is known and still ahead, it returns without touching anything: that time is held in
 * memory, and read again from the checkpoint row at most once a minute, so that a long-lived
 * consumer still notices a due time another process wrote (one whose schedule has a task this
 * one had not seen, a restored database). A ticker that has just been built -- each request,
 * outside worker mode -- reads that one column. Only when something is due does it ask the
 * providers for the schedule, take the lock and build the generator; that is also when a
 * schedule that changed (a source set to sync) is picked up, since it is built anew each time.
 *
 * A provider that throws is journaled (`queue.schedule_provider_failed`) and left out of that
 * schedule; the others are scheduled as usual. Which rows were its tasks is not known here, so
 * while one fails no task row is forgotten, and every row recorded counts as declared: the tasks
 * of the provider that broke stay in sight, and `queue-task-overdue` says so once they are late,
 * rather than their rows being pruned as if nobody declared them any more (digests that stop
 * with nothing to say so).
 */
class QueueScheduleTicker
{
    /** The schedule's name, as the MessageContext of each run carries it. */
    private const NAME = 'yeswiki';

    /** How long a due time held in memory is trusted before the checkpoint row is read again. */
    private const RECHECK_SECONDS = 60;

    private ClockInterface $clock;

    /** When the schedule is next due, as last read or written; null when it has to be read. */
    private ?\DateTimeImmutable $dueAt = null;

    /** When $dueAt was learnt. */
    private ?\DateTimeImmutable $learntAt = null;

    /**
     * @param iterable<ProvidesSchedule> $providers every service that declares recurring work
     */
    public function __construct(
        #[AutowireIterator('yeswiki.schedule_provider')]
        private readonly iterable $providers,
        private readonly QueueBus $bus,
        private readonly QueueCheckpoint $checkpoint,
        private readonly QueueRecorder $recorder,
        ?ClockInterface $clock = null,
        private readonly ?Journal $journal = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * Queues the runs that are due, and answers how many.
     *
     * Throws when its checkpoint cannot be read or written: the caller journals it and goes on
     * consuming (a provider that throws is only left out, see build()). A broken schedule is then
     * tried again a minute later, not at every idle second of a blocking consumer.
     */
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

            $dispatched = $this->dispatchDue();
            $this->learn($this->checkpoint->dueAt(), $now);

            return $dispatched;
        } catch (\Throwable $failed) {
            $this->learn($now->modify('+' . self::RECHECK_SECONDS . ' seconds'), $now);

            throw $failed;
        } finally {
            // The Scheduler releases the checkpoint only when it got to the end; not when a provider or a write threw.
            $this->checkpoint->unlock();
        }
    }

    /**
     * Every recurring task declared, as Symfony's Schedule; built anew on each call, so that it reflects what the providers declare now.
     *
     * A provider that throws is journaled and left out.
     */
    public function schedule(): Schedule
    {
        return $this->build()[0];
    }

    /**
     * The ids of the tasks the providers declare now: the only tasks whose rows (QueueTaskRows) mean anything.
     *
     * A row outlives its task (a deleted data source, a disabled extension) until the next due tick forgets it; whoever judges the rows, such as the health checks, ignores those.
     * While a provider throws, every row recorded counts too: its own tasks are among them.
     *
     * @return list<string>
     */
    public function declaredTaskIds(): array
    {
        [$schedule, $complete] = $this->build();
        $ids = $this->idsOf($schedule);

        return $complete ? $ids : array_values(array_unique([...$ids, ...$this->recorder->taskIds()]));
    }

    /**
     * Queues a run of that task now, outside its schedule (the jobs page's "run now"); false when no such task is declared or it could not be queued.
     *
     * The schedule is left as it is: the next run stays when it was due.
     */
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
     * The schedule the providers declare now, and whether all of them could say: a provider that throws is journaled and left out, and the others are scheduled all the same.
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
                    // The same message on the same interval, declared again: it is the same task, scheduled once already.
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
        // The schedule was just built from what the providers declare now: the moment to forget the tasks that left it,
        // unless a provider could not say, whose tasks would go with them.
        if ($complete) {
            $this->recorder->forgetTasksExcept($this->idsOf($schedule));
        }

        return $dispatched;
    }

    /**
     * Sends one run to the queue; a scheduled task is sent under its unique key and recorded on its row.
     *
     * @return bool whether it is in the queue (or a run of the same task already waiting there)
     */
    private function dispatch(object $message, int $everySeconds, ?\DateTimeImmutable $nextDue): bool
    {
        if (!$message instanceof ScheduledTask) {
            return $this->bus->dispatch($message)->last(TransportMessageIdStamp::class) !== null;
        }

        // QueueBus journals a queue it cannot write to, and hands the envelope back without an id.
        $queued = $this->bus->dispatch($message, [new UniqueStamp('task:' . $message->taskId())])->last(TransportMessageIdStamp::class) !== null;
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

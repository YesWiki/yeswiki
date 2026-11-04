<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\TaskHistory;
use YesWiki\Kernel\Queue\TaskPeriodStamp;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;

/**
 * Turns the recurring tasks that are due into messages in the queue (messenger / scheduler): a task is due at its trigger's first run after
 * its anchor, its last dispatch or its first sighting, as the Journal tells (ScheduleHistory), on the fixed slots of ScheduleGrid rather
 * than a period after the anchor; one process ticks at a time, under LOCK.
 * The schedule stands still while the wiki is hibernated; tick() returns at once while the next due time, held in memory, is ahead.
 */
class QueueScheduleTicker
{
    public const LOCK = 'cache/queue-schedule.lock';

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
        private readonly ScheduleHistory $history,
        private readonly QueueRecorder $recorder,
        private readonly RuntimeLock $locks,
        private readonly ClockInterface $clock,
        private readonly HibernationService $hibernation,
        private readonly ?Journal $journal = null,
        private readonly ScheduleGrid $grid = new ScheduleGrid(),
    ) {
    }

    /**
     * Queues the runs that are due and answers how many (one that could not be sent is tried in a minute); throws when the Journal or the lock fails.
     *
     * @phpstan-impure
     */
    public function tick(): int
    {
        $now = $this->clock->now();

        try {
            $due = $this->dueAt();
            if ($due !== null && $now < $due) {
                return 0;
            }
            if ($this->hibernation->isWikiHibernatedNow()) {
                $this->learn($now->modify('+' . self::RECHECK_SECONDS . ' seconds'), $now);

                return 0;
            }

            $dispatched = $this->dispatchDue($now);
            if ($dispatched === null) {
                return 0;
            }
            $due = $this->nextDueAt($now);
            $this->learn($due === null || $due <= $now ? $now->modify('+' . self::RECHECK_SECONDS . ' seconds') : $due, $now);

            return $dispatched;
        } catch (\Throwable $failed) {
            $this->learn($now->modify('+' . self::RECHECK_SECONDS . ' seconds'), $now);

            throw $failed;
        }
    }

    /**
     * When a task is next due, read again once a minute; null when a declared task was never seen, or none will ever run again: due now.
     *
     * @phpstan-impure
     */
    public function dueAt(): ?\DateTimeImmutable
    {
        $now = $this->clock->now();
        if ($this->isStale($now)) {
            $this->learn($this->nextDueAt($now), $now);
        }

        return $this->dueAt;
    }

    /** Every recurring task declared, built anew on each call; a provider that throws is left out (only a tick journals it). */
    public function schedule(): Schedule
    {
        return $this->build()[0];
    }

    /**
     * The ids of the tasks the providers declare now, and while a provider throws every task the Journal has a row of.
     *
     * @return list<string>
     */
    public function declaredTaskIds(): array
    {
        [$schedule, $complete] = $this->build();
        $ids = [];
        foreach ($this->tasksOf($schedule, $this->clock->now()) as [$task]) {
            $ids[] = $task->taskId();
        }

        return $complete ? $ids : array_values(array_unique([...$ids, ...array_map('strval', array_keys($this->history->all()))]));
    }

    /**
     * The tasks declared, by id, with their history and next due time (now when never seen); while a provider throws, the Journal's others too, due on the slot after their anchor.
     *
     * @return list<array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}>
     */
    public function tasks(): array
    {
        $now = $this->clock->now();
        [$schedule, $complete] = $this->build();
        $histories = $this->history->all();
        $tasks = [];

        foreach ($this->tasksOf($schedule, $now) as [$task, $trigger]) {
            $history = $histories[$task->taskId()] ?? null;
            $anchor = $history?->anchor();
            $next = $anchor === null ? $now : $this->grid->nextRun($trigger, $anchor);
            $every = $this->periodOf($trigger, $next ?? $now, $anchor !== null && $next !== null);
            $tasks[$task->taskId()] = self::row($task->taskId(), $task->label(), $every, $next, $history);
        }
        if (!$complete) {
            foreach ($histories as $taskId => $history) {
                $every = $history->everySeconds ?? 0;
                $anchor = $history->anchor();
                $next = $every > 0 && $anchor !== null ? $this->grid->nextRun(new PeriodicalTrigger($every), $anchor) : null;
                $tasks[(string)$taskId] ??= self::row((string)$taskId, $history->label ?? (string)$taskId, $every, $next, $history);
            }
        }
        usort($tasks, static fn (array $a, array $b): int => strcmp($a['taskId'], $b['taskId']));

        return $tasks;
    }

    /**
     * That task, as tasks() lists it; null when it is not listed.
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}|null
     */
    public function task(string $taskId): ?array
    {
        foreach ($this->tasks() as $task) {
            if ($task['taskId'] === $taskId) {
                return $task;
            }
        }

        return null;
    }

    /** Queues a run of that task now, from which its next run counts; false when no such task is declared or it could not be queued. */
    public function runNow(string $taskId): bool
    {
        $now = $this->clock->now();
        foreach ($this->tasksOf($this->schedule(), $now) as [$task, $trigger]) {
            if ($task->taskId() === $taskId) {
                $this->learntAt = null;

                return $this->queue($task, $this->periodOf($trigger, $now));
            }
        }

        return false;
    }

    /**
     * The schedule the providers declare now, and whether all of them could say; a provider that cannot is journaled when $say.
     *
     * @return array{Schedule, bool}
     */
    private function build(bool $say = false): array
    {
        $schedule = new Schedule();
        $complete = true;
        foreach ($this->providers as $provider) {
            try {
                $recurring = $provider->recurringMessages();
            } catch (\Throwable $failed) {
                $complete = false;
                if ($say) {
                    $this->journal?->error($provider::class . ' could not say what it schedules; its tasks are kept as they were: ' . $failed->getMessage(), [
                        'action' => 'queue.schedule_provider_failed',
                        'target' => $provider::class,
                        'exception' => $failed,
                    ]);
                }

                continue;
            }
            foreach ($recurring as $message) {
                try {
                    $schedule->add($message);
                } catch (LogicException $twice) {
                    if ($say) {
                        $this->journal?->error($provider::class . ' declares a scheduled task twice; it is scheduled once: ' . $twice->getMessage(), [
                            'action' => 'queue.schedule_provider_failed',
                            'target' => $provider::class,
                            'exception' => $twice,
                        ]);
                    }
                }
            }
        }

        return [$schedule, $complete];
    }

    /** Under the lock, notes the tasks never seen and queues the ones due, on a schedule of its own; null when another process ticks. */
    private function dispatchDue(\DateTimeImmutable $now): ?int
    {
        $lock = $this->locks->acquire(self::LOCK);
        if ($lock === null) {
            throw new \RuntimeException('The schedule lock ' . self::LOCK . ' cannot be created');
        }
        if (!$this->locks->tryLock($lock)) {
            $this->locks->release($lock);

            return null;
        }

        try {
            [$schedule] = $this->build(true);
            $histories = $this->history->all();
            $dispatched = 0;
            foreach ($this->tasksOf($schedule, $now, true) as [$task, $trigger]) {
                $anchor = ($histories[$task->taskId()] ?? null)?->anchor();
                if ($anchor === null) {
                    $this->recorder->taskDeclared($task, $this->periodOf($trigger, $now));

                    continue;
                }
                $next = $this->grid->nextRun($trigger, $anchor);
                if ($next !== null && $next <= $now && $this->queue($task, $this->periodOf($trigger, $next, true))) {
                    $dispatched++;
                }
            }

            return $dispatched;
        } finally {
            $this->locks->release($lock);
        }
    }

    /** The earliest next run of the declared tasks, on a schedule of its own; null when one was never seen, or none will run again. */
    private function nextDueAt(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $schedule = $this->schedule();
        $histories = $this->history->all();
        $earliest = null;
        foreach ($this->tasksOf($schedule, $now) as [$task, $trigger]) {
            $anchor = ($histories[$task->taskId()] ?? null)?->anchor();
            if ($anchor === null) {
                return null;
            }
            $next = $this->grid->nextRun($trigger, $anchor);
            if ($next !== null && ($earliest === null || $next < $earliest)) {
                $earliest = $next;
            }
        }

        return $earliest?->setTimezone(new \DateTimeZone('UTC'));
    }

    /** Sends one run of that task to the queue, which journals it with its period (QueueBus); true once queued, or when that task already waits. */
    private function queue(ScheduledTask $task, int $everySeconds): bool
    {
        return $this->bus->dispatch($task, $everySeconds > 0 ? [new TaskPeriodStamp($everySeconds)] : [])->last(TransportMessageIdStamp::class) !== null;
    }

    /**
     * The scheduled tasks of that schedule, each once, with the trigger it recurs on; a message that is no task is journaled when $say, and left out.
     *
     * @return \Generator<int, array{ScheduledTask, TriggerInterface}>
     */
    private function tasksOf(Schedule $schedule, \DateTimeImmutable $now, bool $say = false): \Generator
    {
        $seen = [];
        foreach ($schedule->getRecurringMessages() as $recurring) {
            $trigger = $recurring->getTrigger();
            foreach ($recurring->getMessages(new MessageContext(self::NAME, $recurring->getId(), $trigger, $now)) as $message) {
                if (!$message instanceof ScheduledTask) {
                    if ($say) {
                        $this->journal?->error('A recurring ' . $message::class . ' is no ScheduledTask: with no task id to find its last run by, it is not scheduled', [
                            'action' => 'queue.schedule_provider_failed',
                            'target' => $message::class,
                        ]);
                    }

                    continue;
                }
                if (!isset($seen[$message->taskId()])) {
                    $seen[$message->taskId()] = true;

                    yield [$message, $trigger];
                }
            }
        }
    }

    /**
     * One task as tasks() lists it.
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}
     */
    private static function row(string $taskId, string $label, int $every, ?\DateTimeImmutable $next, ?TaskHistory $history): array
    {
        return [
            'taskId' => $taskId,
            'label' => $label === '' ? $taskId : $label,
            'everySeconds' => $every,
            'lastDispatchedAt' => $history?->lastDispatchedAt,
            'nextDueAt' => $next?->setTimezone(new \DateTimeZone('UTC')),
            'lastFinishedAt' => $history?->lastFinishedAt,
            'lastStatus' => $history?->lastStatus,
            'lastError' => $history?->lastError,
            'consecutiveFailures' => $history === null ? 0 : $history->consecutiveFailures,
        ];
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

    /**
     * How far apart a trigger's runs are: an every()'s period, else from the next two runs after $from, or $from and the one after
     * when it is a run; 0 when they are not there.
     */
    private function periodOf(TriggerInterface $trigger, \DateTimeImmutable $from, bool $isARun = false): int
    {
        $every = $this->grid->everySeconds($trigger);
        if ($every !== null) {
            return $every;
        }
        $next = $isARun ? $from : $this->grid->nextRun($trigger, $from);
        $after = $next === null ? null : $this->grid->nextRun($trigger, $next);

        return $next === null || $after === null ? 0 : $after->getTimestamp() - $next->getTimestamp();
    }
}

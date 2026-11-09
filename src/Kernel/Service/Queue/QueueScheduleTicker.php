<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\TaskHistory;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;

/**
 * Turns the recurring tasks that are due into messages in the queue (messenger / scheduler): a task is due at its trigger's next run
 * after its anchor, its last dispatch or its first sighting, as the history tells (ScheduleHistory). An every() runs a period after
 * its anchor; a cron() at its times in the wiki's timezone, PHP's. One process ticks at a time, under LOCK.
 * A provider that throws, or a trigger that does, leaves its tasks out and stops no other; only the tick journals it.
 * The schedule stands still while the wiki is hibernated; tick() returns at once while the next due time, held in memory, is ahead.
 */
class QueueScheduleTicker
{
    public const LOCK = 'cache/queue-schedule.lock';

    private const NAME = 'yeswiki';

    private const RECHECK_SECONDS = 60;

    /** Any fixed date: the period of an every() is read from two of its runs after it. */
    private const REFERENCE = '2000-01-03 00:00:00';

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
    ) {
    }

    /**
     * Queues the runs that are due and answers how many (one that could not be sent is tried in a minute); throws when the history or the lock fails.
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

    /**
     * The tasks the providers declare now, by id, with their history and next due time (now when never seen). A task whose trigger throws is left out.
     *
     * @return list<array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string}>
     */
    public function tasks(): array
    {
        $now = $this->clock->now();
        $histories = $this->history->all();
        $tasks = [];

        foreach ($this->declared($now) as [$task, $recurring]) {
            $history = $histories[$task->taskId()] ?? null;
            $anchor = $history?->anchor();
            try {
                $next = $anchor === null ? $now : $this->nextRun($recurring, $anchor);
                $every = $this->everySeconds($recurring->getTrigger());
            } catch (\Throwable) {
                continue;
            }
            $tasks[] = self::row($task, $every, $next, $history);
        }
        usort($tasks, static fn (array $a, array $b): int => strcmp($a['taskId'], $b['taskId']));

        return $tasks;
    }

    /**
     * That task, as tasks() lists it; null when it is not listed.
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string}|null
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
        foreach ($this->declared($this->clock->now()) as [$task]) {
            if ($task->taskId() === $taskId) {
                $this->learntAt = null;

                return $this->queue($task);
            }
        }

        return false;
    }

    /**
     * The scheduled tasks the providers declare now, each once (the first declared wins), with the recurring message it comes in
     * and the provider that declared it. A provider that throws is left out, and so is a message that is no task: journaled when $say.
     *
     * @return list<array{ScheduledTask, RecurringMessage, class-string<ProvidesSchedule>}>
     */
    private function declared(\DateTimeImmutable $now, bool $say = false): array
    {
        $declared = [];
        $seen = [];
        foreach ($this->providers as $provider) {
            try {
                $messages = [];
                foreach ($provider->recurringMessages() as $recurring) {
                    foreach ($recurring->getMessages(new MessageContext(self::NAME, $recurring->getId(), $recurring->getTrigger(), $now)) as $message) {
                        $messages[] = [$message, $recurring];
                    }
                }
            } catch (\Throwable $failed) {
                if ($say) {
                    $this->journal?->error($provider::class . ' could not say what it schedules; its tasks are left out: ' . $failed->getMessage(), [
                        'action' => 'queue.schedule_provider_failed',
                        'target' => $provider::class,
                        'exception' => $failed,
                    ]);
                }

                continue;
            }
            foreach ($messages as [$message, $recurring]) {
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
                    $declared[] = [$message, $recurring, $provider::class];
                }
            }
        }

        return $declared;
    }

    /**
     * Under the lock, notes the tasks never seen and queues the ones due; null when another process ticks.
     * A task whose trigger throws is journaled and skipped.
     */
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
            $declared = $this->declared($now, true);
            $histories = $this->history->all();
            $dispatched = 0;
            foreach ($declared as [$task, $recurring, $provider]) {
                $anchor = ($histories[$task->taskId()] ?? null)?->anchor();
                if ($anchor === null) {
                    $this->recorder->taskDeclared($task);

                    continue;
                }
                try {
                    $next = $this->nextRun($recurring, $anchor);
                } catch (\Throwable $failed) {
                    $this->journal?->error("{$provider} declares the task '{$task->taskId()}' on a trigger that throws; it is not scheduled: " . $failed->getMessage(), [
                        'action' => 'queue.schedule_provider_failed',
                        'target' => $provider,
                        'task' => $task->taskId(),
                        'exception' => $failed,
                    ]);

                    continue;
                }
                if ($next !== null && $next <= $now && $this->queue($task)) {
                    $dispatched++;
                }
            }

            return $dispatched;
        } finally {
            $this->locks->release($lock);
        }
    }

    /** The earliest next run of the declared tasks; null when one was never seen, or none will run again. A trigger that throws is passed over. */
    private function nextDueAt(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $declared = $this->declared($now);
        $histories = $this->history->all();
        $earliest = null;
        foreach ($declared as [$task, $recurring]) {
            $anchor = ($histories[$task->taskId()] ?? null)?->anchor();
            if ($anchor === null) {
                return null;
            }
            try {
                $next = $this->nextRun($recurring, $anchor);
            } catch (\Throwable) {
                continue;
            }
            if ($next !== null && ($earliest === null || $next < $earliest)) {
                $earliest = $next;
            }
        }

        return $earliest;
    }

    /**
     * The first run of that task after $anchor; null when its trigger has ended. An every() runs a period after the anchor: Symfony's
     * PeriodicalTrigger would put it on the grid of its own start instead. Any other trigger is asked after the anchor in the wiki's
     * timezone, which a cron() that names none runs in.
     */
    private function nextRun(RecurringMessage $recurring, \DateTimeImmutable $anchor): ?\DateTimeImmutable
    {
        $trigger = $recurring->getTrigger();
        if ($trigger instanceof PeriodicalTrigger) {
            $every = $this->everySeconds($trigger);
            $next = $anchor->modify("+{$every} seconds");

            return $every > 0 && (clone $trigger)->getNextRunDate($next->modify('-1 second')) !== null ? $next : null;
        }

        return (clone $trigger)->getNextRunDate($anchor->setTimezone(new \DateTimeZone(date_default_timezone_get())));
    }

    /**
     * How far apart a task's runs are, in seconds: an every()'s period, else the gap between its next two runs from now; 0 when they are not there.
     */
    private function everySeconds(TriggerInterface $trigger): int
    {
        $probe = clone $trigger;
        $from = $trigger instanceof PeriodicalTrigger
            ? new \DateTimeImmutable(self::REFERENCE, new \DateTimeZone('UTC'))
            : $this->clock->now()->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $first = $probe->getNextRunDate($from);
        $second = $first === null ? null : $probe->getNextRunDate($first);

        return $first === null || $second === null ? 0 : $second->getTimestamp() - $first->getTimestamp();
    }

    /** Sends one run of that task to the queue, which journals its dispatch (QueueBus); true once queued, or when that task already waits. */
    private function queue(ScheduledTask $task): bool
    {
        return $this->bus->dispatch($task)->last(TransportMessageIdStamp::class) !== null;
    }

    /**
     * One task as tasks() lists it.
     *
     * @return array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string}
     */
    private static function row(ScheduledTask $task, int $every, ?\DateTimeImmutable $next, ?TaskHistory $history): array
    {
        return [
            'taskId' => $task->taskId(),
            'label' => $task->label() === '' ? $task->taskId() : $task->label(),
            'everySeconds' => $every,
            'lastDispatchedAt' => $history?->lastDispatchedAt,
            'nextDueAt' => $next,
            'lastFinishedAt' => $history?->lastFinishedAt,
            'lastStatus' => $history?->lastStatus,
            'lastError' => $history?->lastError,
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
}

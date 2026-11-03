<?php

namespace YesWiki\Kernel\Queue;

/** What the Journal says of one scheduled task: when it appeared, was last queued, and how its last run ended (ScheduleHistory). */
final class TaskHistory
{
    public function __construct(
        public readonly string $taskId,
        public readonly ?string $label,
        public readonly ?\DateTimeImmutable $declaredAt,
        public readonly ?\DateTimeImmutable $lastDispatchedAt,
        public readonly ?\DateTimeImmutable $lastFinishedAt,
        public readonly ?string $lastStatus,
        public readonly ?string $lastError,
        public readonly int $consecutiveFailures,
    ) {
    }

    /** The moment the Scheduler counts from: the last dispatch, else the task's appearance. */
    public function anchor(): ?\DateTimeImmutable
    {
        return $this->lastDispatchedAt ?? $this->declaredAt;
    }
}

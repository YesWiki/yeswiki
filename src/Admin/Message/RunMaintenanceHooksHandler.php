<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;

/**
 * Dispatches `maintenance.before` then `maintenance.after`, one listener at a time: each failure is journaled (`maintenance.hook_failed`)
 * and the handler then throws a summary. Context: `startedAt` (Unix time), `interval` (seconds), `previousRun` (Unix time or null),
 * and for `maintenance.after` only `duration` (seconds the `before` listeners took).
 */
#[AsMessageHandler]
final class RunMaintenanceHooksHandler
{
    public const BEFORE = 'maintenance.before';
    public const AFTER = 'maintenance.after';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly Journal $journal,
        private readonly ScheduleHistory $history,
    ) {
    }

    public function __invoke(RunMaintenanceHooks $task): void
    {
        $began = microtime(true);
        $context = [
            'startedAt' => time(),
            'interval' => KernelSchedule::HOUSEKEEPING_EVERY,
            'previousRun' => $this->previousRun($task),
        ];

        $failures = $this->dispatch(self::BEFORE, $context);
        $failures = [...$failures, ...$this->dispatch(self::AFTER, $context + ['duration' => microtime(true) - $began])];

        if ($failures !== []) {
            throw new \RuntimeException(count($failures) . ' maintenance hook(s) failed: ' . implode('; ', $failures));
        }
    }

    /**
     * Calls each listener of the event with the same Event, and answers what the ones that threw said.
     *
     * @param self::BEFORE|self::AFTER $eventName
     * @param array<string, mixed>     $context
     *
     * @return list<string>
     */
    private function dispatch(string $eventName, array $context): array
    {
        $failures = [];
        $this->dispatcher->dispatchToEach($eventName, new Event($context), function (string $name, \Throwable $failed) use ($eventName, &$failures): void {
            $this->journal->error("The {$eventName} hook {$name} failed: {$failed->getMessage()}", [
                'action' => 'maintenance.hook_failed',
                'target' => $name,
                'exception' => $failed,
            ]);
            $failures[] = "{$name} ({$eventName}): {$failed->getMessage()}";
        });

        return $failures;
    }

    /** When the task last finished, as a Unix time. */
    private function previousRun(RunMaintenanceHooks $task): ?int
    {
        return $this->history->of($task->taskId())?->lastFinishedAt?->getTimestamp();
    }
}

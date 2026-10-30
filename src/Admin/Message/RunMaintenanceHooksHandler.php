<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueStatistics;

/**
 * Dispatches `maintenance.before` and then `maintenance.after` to the extensions, one listener at a time.
 *
 * Every listener is called on its own, so that one that throws neither stops the ones after it
 * nor goes unseen: each failure is written to the Journal under `maintenance.hook_failed`, with
 * the listener's name as its target, and once all have run the handler throws a summary of them
 * for the task to show as failed on the jobs page. (EventDispatcher::yesWikiDispatch(), which
 * these events went through, caught the first throw around the whole dispatch and told nobody.)
 *
 * The listeners get the context they always had: `startedAt` (a Unix time), `interval` (seconds
 * between two runs), `previousRun` (a Unix time, or null if the task never finished a run), and,
 * for `maintenance.after` only, `duration` (the seconds the `before` listeners took -- the
 * housekeeping steps run as tasks of their own and are no longer between the two).
 */
#[AsMessageHandler]
final class RunMaintenanceHooksHandler
{
    public const BEFORE = 'maintenance.before';
    public const AFTER = 'maintenance.after';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly Journal $journal,
        private readonly QueueStatistics $statistics,
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
        foreach ($this->statistics->tasks() as $known) {
            if ($known['taskId'] === $task->taskId()) {
                return $known['lastFinishedAt']?->getTimestamp();
            }
        }

        return null;
    }
}

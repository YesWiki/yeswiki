<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The budget of the pass running right now, so a handler that can cut its own work in slices asks how much time is left instead of guessing.
 *
 * Outside a budgeted pass (a blocking consumer, an admin's synchronous call) there is no remaining time to give: null, not infinity, so a handler cannot mistake "not measured" for "plenty".
 */
class QueueBudgetContext implements RequestScopedState
{
    private ?QueueBudget $budget = null;

    public function start(QueueBudget $budget): void
    {
        $this->budget = $budget;
    }

    /** The pass is over: code that runs later in the same request is not under its budget, and must not read "0 s left". */
    public function end(): void
    {
        $this->budget = null;
    }

    /** Seconds the running pass has left; null when no budgeted pass is running. */
    public function remainingSeconds(): ?float
    {
        return $this->budget?->remainingSeconds();
    }

    /** Whole seconds one slice of a handler's work may take: $atMost, or what the running pass has left when that is less, and never under one. */
    public function sliceSeconds(int $atMost): int
    {
        return max(1, (int)min($atMost, $this->remainingSeconds() ?? $atMost));
    }

    public function startNewRequest(): void
    {
        $this->budget = null;
    }
}

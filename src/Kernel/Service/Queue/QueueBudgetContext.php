<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The budget of the pass running right now, so a handler that can cut its own work in slices asks how much time is left.
 * Outside a budgeted pass there is no remaining time to give: null, not infinity.
 */
class QueueBudgetContext implements RequestScopedState
{
    private ?QueueBudget $budget = null;

    public function start(QueueBudget $budget): void
    {
        $this->budget = $budget;
    }

    /** The pass is over: later code in the same request is not under its budget. */
    public function end(): void
    {
        $this->budget = null;
    }

    /** Seconds the running pass has left; null when no budgeted pass is running. */
    public function remainingSeconds(): ?float
    {
        return $this->budget?->remainingSeconds();
    }

    /** Whole seconds one slice of a handler's work may take: $atMost or what the pass has left if less, never under one. */
    public function sliceSeconds(int $atMost): int
    {
        return max(1, (int)min($atMost, $this->remainingSeconds() ?? $atMost));
    }

    public function startNewRequest(): void
    {
        $this->budget = null;
    }
}

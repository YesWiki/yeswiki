<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Whether the entries being written right now come from an import. */
class ImportContext implements RequestScopedState
{
    private bool $importing = false;

    public function isImporting(): bool
    {
        return $this->importing;
    }

    /**
     * Run $work with the import flag set, and put it back afterwards.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function during(callable $work)
    {
        $was = $this->importing;
        $this->importing = true;

        try {
            return $work();
        } finally {
            $this->importing = $was;
        }
    }

    public function startNewRequest(): void
    {
        $this->importing = false;
    }
}

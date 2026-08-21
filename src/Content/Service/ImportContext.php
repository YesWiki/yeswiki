<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Whether the entries being written right now come from an import.
 *
 * An import writes entries the way a visitor's form submission does, and two things have to
 * behave differently when it does: a subscription field must not send its welcome mail, and a
 * form's own defaults must not overwrite what the imported row said.
 *
 * It was `$GLOBALS['_BAZAR_']['provenance'] === 'import'`, set by the CSV importer and never
 * unset. Under worker mode (ADR-0024) every later request in that process believes it is an
 * import too, so a real visitor signing up gets no mail and nobody finds out. `during()` scopes
 * the flag to the work it describes and restores it even when that work throws, which the flag
 * it replaces never did.
 */
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

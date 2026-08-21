<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Numbers the entry lists drawn while serving one page.
 *
 * Two `{{entrylist}}` calls on a page need two sets of DOM ids, so each list takes the next
 * number and builds `entry-list-3`, `osmmap3`, `table_3` from it.
 *
 * It was `$GLOBALS['_BAZAR_']['listindex']`, initialised only when unset. Under worker mode
 * (ADR-0024) it never gets unset, so the ids drift with the age of the process: the first
 * visitor sees `entry-list-1` and the hundredth sees `entry-list-231`. Nothing breaks visibly,
 * which is what makes it worth removing rather than resetting.
 */
class ListIndex implements RequestScopedState
{
    private int $index = 0;

    /** The number of the list about to be drawn, counting from one. */
    public function next(): int
    {
        return ++$this->index;
    }

    public function startNewRequest(): void
    {
        $this->index = 0;
    }
}

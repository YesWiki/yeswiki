<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Numbers the entry lists drawn while serving one page. */
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

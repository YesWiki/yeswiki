<?php

namespace YesWiki\Contact\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Numbers the mail forms rendered while serving one page. */
class MailFormCounter implements RequestScopedState
{
    private int $count = 0;

    /** The number of the form about to be drawn, counting from one. */
    public function next(): int
    {
        return ++$this->count;
    }

    public function startNewRequest(): void
    {
        $this->count = 0;
    }
}

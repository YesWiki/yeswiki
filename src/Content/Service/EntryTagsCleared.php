<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Which entries have had their keyword triples cleared while saving. */
class EntryTagsCleared implements RequestScopedState
{
    /** @var array<string, true> entry tag => its keywords have been cleared this request */
    private array $cleared = [];

    /** Whether this entry's keywords still need clearing, marking them cleared if so. */
    public function needsClearing(string $entryTag): bool
    {
        if (isset($this->cleared[$entryTag])) {
            return false;
        }
        $this->cleared[$entryTag] = true;

        return true;
    }

    public function startNewRequest(): void
    {
        $this->cleared = [];
    }
}

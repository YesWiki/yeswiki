<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Which entries have had their keyword triples cleared while saving.
 *
 * A tags field rewrites an entry's keywords by deleting every triple it has and creating them
 * again from what was submitted. Deleting has to happen once, before the first field writes, or
 * the second field would delete what the first just created: a form with two tag fields would
 * keep only the last one's keywords.
 *
 * It was `$GLOBALS['delete_tags']`, a bare boolean **keyed by nothing**, so saving two entries in
 * one request cleared only the first one's keywords and the second kept its old ones alongside
 * the new. Keyed by entry tag here, which fixes that as well as the worker-mode leak ADR-0024 is
 * about.
 */
class EntryTagsCleared implements RequestScopedState
{
    /** @var array<string, true> entry tag => its keywords have been cleared this request */
    private array $cleared = [];

    /**
     * Whether this entry's keywords still need clearing, marking them cleared if so.
     *
     * Answers true exactly once per entry per request, which is what the caller needs to decide.
     */
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

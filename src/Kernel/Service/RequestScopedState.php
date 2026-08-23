<?php

namespace YesWiki\Kernel\Service;

/** A service holding a fact about **this request** and nothing longer. */
interface RequestScopedState
{
    /** Forget this request, so the next one starts where the first one did. */
    public function startNewRequest(): void;
}

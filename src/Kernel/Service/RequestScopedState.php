<?php

namespace YesWiki\Kernel\Service;

/**
 * A service holding a fact about **this request** and nothing longer.
 *
 * ADR-0024 says request state must live in a service that is built per request and cannot be
 * reached from a long-lived singleton. Moving a counter out of `$GLOBALS` and into a container
 * service satisfies the first half and not the second: the container outlives the request under
 * worker mode, so the counter goes on counting. `RepeatedRequestTest` is what showed that, by
 * rendering the same page ten times in one process and watching the list ids climb from 21 to 39.
 *
 * The interface **is** the registration. `YesWikiRequestScopeCompilerPass` finds every
 * implementer, so a new piece of request state announces itself by declaring what it is, and the
 * ADR's objection to a reset routine -- "a list somebody has to remember to extend" -- does not
 * apply: there is no list. What there is instead is a rule a class states about itself, and
 * `GlobalsRatchetTest` keeping the alternative closed.
 */
interface RequestScopedState
{
    /**
     * Forget this request, so the next one starts where the first one did.
     *
     * Called by the runtime before a request is served, never from inside one.
     */
    public function startNewRequest(): void;
}

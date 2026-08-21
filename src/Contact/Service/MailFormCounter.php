<?php

namespace YesWiki\Contact\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Numbers the mail forms rendered while serving one page.
 *
 * `{{contact}}`, `{{subscribe}}` and `{{unsubscribe}}` all draw a form whose address lives in the
 * page body rather than in the form, so `FindMailFromWikiPage()` picks the Nth `{{…mail="…"}}`
 * call out of that body. N is this counter.
 *
 * It was `$GLOBALS['nbactionmail']`, incremented behind an `isset()`. Under php-fpm the process
 * dies with the request and the count starts again; under worker mode (ADR-0024) it climbs for
 * the life of the process, so the second visitor's form reads the address meant for a form
 * further down the page, or no address at all. Request state lives in a service, never in a
 * global, and this service is rebuilt per request.
 */
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

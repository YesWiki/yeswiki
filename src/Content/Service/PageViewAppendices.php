<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\AppendsToPageView;

/**
 * Everything that adds a block to the bottom of a page view.
 *
 * A one-method holder for a tagged iterable, and it exists rather than the iterable being
 * injected into `ShowHandler` for a mundane reason: a handler is resolved by a registry and
 * built without constructor arguments, so it has no constructor to inject into. That is the
 * same reason `getService()` is the normal idiom there (ADR-0013).
 */
class PageViewAppendices
{
    /** @param iterable<AppendsToPageView> $appendices tagged `yeswiki.page_view_appendix` */
    public function __construct(private readonly iterable $appendices = [])
    {
    }

    /** @return iterable<AppendsToPageView> */
    public function all(): iterable
    {
        return $this->appendices;
    }
}

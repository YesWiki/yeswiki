<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\AppendsToPageView;

/** Everything that adds a block to the bottom of a page view. */
class PageViewAppendices
{
    /**
     * @param iterable<AppendsToPageView> $appendices tagged `yeswiki.page_view_appendix`
     */
    public function __construct(private readonly iterable $appendices = [])
    {
    }

    /**
     * @return iterable<AppendsToPageView>
     */
    public function all(): iterable
    {
        return $this->appendices;
    }
}

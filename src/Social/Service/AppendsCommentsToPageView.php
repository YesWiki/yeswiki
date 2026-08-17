<?php

namespace YesWiki\Social\Service;

use YesWiki\Content\Entity\AppendsToPageView;

/** The comment box, at the bottom of a page that has comments turned on. */
class AppendsCommentsToPageView implements AppendsToPageView
{
    public function __construct(private readonly CommentService $comments)
    {
    }

    public function appendToPageView(string $tag): string
    {
        return $this->comments->renderCommentsForPage($tag);
    }
}

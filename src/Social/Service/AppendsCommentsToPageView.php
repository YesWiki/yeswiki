<?php

namespace YesWiki\Social\Service;

use YesWiki\Content\Entity\AppendsToPageView;

/**
 * The comment box, at the bottom of a page that has comments turned on.
 *
 * `ShowHandler` used to call `CommentService::renderCommentsForPage()` directly, which was the
 * last thing making the page view depend on the social features (ADR-0019). Whether a page has
 * comments at all is `CommentService`'s question and stays there.
 */
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

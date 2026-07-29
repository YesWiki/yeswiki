<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

class CommentsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{comments}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'comments';
    }

    public function run()
    {
        // render the comments if needed
        return $this->getService(CommentService::class)->renderCommentsForPage($this->getService(PageContext::class)->getTag());
    }
}

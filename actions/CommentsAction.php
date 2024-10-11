<?php

use YesWiki\Core\Service\CommentService;
use YesWiki\Core\YesWikiAction;

class CommentsAction extends YesWikiAction
{
    public function run()
    {
        // render the comments if needed
        return $this->getService(CommentService::class)->renderCommentsForPage($this->wiki->getPageTag());
    }
}

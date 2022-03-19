<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\CommentService;

class CommentsAction extends YesWikiAction
{
    public function run()
    {
        // render the comments if needed
        echo $this->getService(CommentService::class)->renderCommentsForPage($this->wiki->getPageTag());
    }
}

<?php

use YesWiki\Core\Service\CommentService;
use YesWiki\Core\YesWikiAction;

class CommentsTableAction extends YesWikiAction
{
    protected $commentsService;

    public function run()
    {
        // get Services
        $this->commentsService = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments(''); // get all comments

        return $this->render('@core/comment-table.twig', [
            'comments' => $coms,
        ]);
    }
}

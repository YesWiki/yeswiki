<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\CommentService;

class CommentsTableAction extends YesWikiAction
{
    protected $commentsService;
    
    public function run()
    {
        // get Services
        $this->commentsService  = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments(''); # get all comments
        return $this->render('@core/comments-table.twig', [
            'comments' => $coms,
            'isAdmin' => $this->wiki->userIsAdmin(),
        ]) ;
    }
}

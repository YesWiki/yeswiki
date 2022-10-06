<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\CommentService;

class CommentsTableAction extends YesWikiAction
{
    protected $aclService;
    protected $commentsService;
    
    public function run()
    {
        // get Services
        $this->aclService  = $this->getService(AclService::class);
        $this->commentsService  = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments(''); # get all comments
        // filter on read acl on parent page
        $coms = array_filter($coms, function ($com) {
            return !empty($com['comment_on']) && $this->aclService->hasAccess('read', $com['comment_on']);
        });
        return $this->render('@core/comments-table.twig', [
            'comments' => $coms,
        ]) ;
    }
}

<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\UserManager;

class UserCommentsAction extends YesWikiAction
{
    protected $aclService;
    protected $commentsService;
    protected $userManager;

    public function run()
    {
        // get Services
        $this->userManager  = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return $this->render('@templates/alert-message.twig', [
                'message' => _t('COMMENT_RESERVED_TO_CONNECTED'),
                'type' => 'info'
            ]) ;
        }

        $this->aclService  = $this->getService(AclService::class);
        $this->commentsService  = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments('', $user['name']); # get all comments
        // filter on read acl on parent page
        $coms = array_filter($coms, function ($com) {
            return !empty($com['comment_on']) && $this->aclService->hasAccess('read', $com['comment_on']);
        });
        return $this->render('@core/comment-table.twig', [
            'comments' => $coms,
        ]) ;
    }
}

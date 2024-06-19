<?php

use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class UserCommentsAction extends YesWikiAction
{
    protected $commentsService;
    protected $userManager;

    public function run()
    {
        // get Services
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return $this->render('@templates/alert-message.twig', [
                'message' => _t('COMMENT_RESERVED_TO_CONNECTED'),
                'type' => 'info',
            ]);
        }

        $this->commentsService = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments('', $user['name']); // get all comments

        return $this->render('@core/comment-table.twig', [
            'comments' => $coms,
        ]);
    }
}

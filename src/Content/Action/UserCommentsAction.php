<?php

namespace YesWiki\Content\Action;
use YesWiki\Content\Service\CommentService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class UserCommentsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{usercomments}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'usercomments';
    }

    protected $commentsService;
    protected $userManager;

    public function run()
    {
        // get Services
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return $this->render('@core/alert-message.twig', [
                'message' => _t('COMMENT_RESERVED_TO_CONNECTED'),
                'type' => 'info',
            ]);
        }

        $this->commentsService = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments('', false, $user['name']); // get all comments

        return $this->render('@core/comment-table.twig', [
            'comments' => $coms,
        ]);
    }
}

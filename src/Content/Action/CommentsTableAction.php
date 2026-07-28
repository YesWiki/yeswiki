<?php

namespace YesWiki\Content\Action;
use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class CommentsTableAction extends YesWikiAction implements RegisteredAction
{
    /** `{{commentstable}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'commentstable';
    }

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

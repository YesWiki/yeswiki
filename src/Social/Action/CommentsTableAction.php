<?php

namespace YesWiki\Social\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Social\Service\CommentService;

class CommentsTableAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{commentstable}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'commentstable';
    }

    public function components(): array
    {
        return [
            Component::for('commentstable')
                ->category(Category::Admin)
                ->label(_t('AB_management_commentstable_label'))
                ->icon('messages')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    protected $commentsService;

    public function run()
    {
        $this->commentsService = $this->getService(CommentService::class);
        $coms = $this->commentsService->loadComments('');

        return $this->render('@core/comment-table.twig', [
            'comments' => $coms,
        ]);
    }
}

<?php

namespace YesWiki\Social\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Social\Service\CommentService;

class CommentsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{comments}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'comments';
    }

    /** No settings at all: where the comment box goes is the whole decision. */
    public function components(): array
    {
        return [
            Component::for('comments')
                ->category(Category::Forms)
                ->label(_t('AB_comments_label'))
                ->icon('message-circle')
                ->description(_t('AB_comments_description'))
                ->previewHeight('250px'),
        ];
    }

    public function run()
    {
        return $this->getService(CommentService::class)->renderCommentsForPage($this->getService(PageContext::class)->getTag());
    }
}

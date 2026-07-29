<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\HashCashService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `/PageName/addcomment` -- converted from the procedural handlers/page/addcomment.php by ticket 06.
 */
class AddcommentHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'addcomment';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emitBefore();
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    /**
     * Ran as a before-callback until ticket 06 merged it in.
     */
    private function emitBefore(): void
    {
        // merged from handlers/page/__addcomment.php (ticket 06: core does not hook itself)
        if (isset($_POST['action']) && $_POST['action'] == 'addcomment') {
            if (!$this->wiki->services->get(HashCashService::class)->checkHashcash()) {
                $this->getService(FlashMessageService::class)->setMessage(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'));
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
            }
        }
    }

    private function emit(): void
    {
        $commentService = $this->wiki->services->get(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($_POST);

        if (!empty($result['error'])) {
            $this->getService(FlashMessageService::class)->setMessage($result['error']);
        } elseif (!empty($result['success'])) {
            $this->getService(FlashMessageService::class)->setMessage($result['success']);
        }
        // redirect to page
        $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', '#post-comment'));
    }
}

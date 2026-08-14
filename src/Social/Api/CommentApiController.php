<?php

namespace YesWiki\Social\Api;

use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Social\Service\CommentService;

class CommentApiController extends YesWikiController
{
    #[Route('/api/comments/{tag}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllComments($tag = '')
    {
        return new ApiResponse([$this->getService(CommentService::class)->loadComments($tag)]);
    }

    #[Route('/api/comments', methods: ['POST'], options: ['acl' => ['+']])]
    public function postComment()
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all());

        return $this->answer($result);
    }

    /**
     * JSON for the page's own script, a redirect back to the page for anyone else.
     *
     * `templates/comment-form.twig` is a plain `<form method="POST">` pointed at this route. With
     * JavaScript a click handler intercepts it and posts by fetch; **without JavaScript the
     * browser submits it normally and used to render this route's JSON as the whole page** -- the
     * comment was saved, and the reader was left looking at `{"success":"..."}`. So commenting was
     * already broken without JS before ticket 35 deleted the `addcomment` handler; the handler was
     * not what made it work.
     *
     * Distinguished by `X-Requested-With`, which `postForm()` in yeswiki-base.js now sends: fetch
     * sets no header of its own that a form submission does not also send, and `Accept` is `*​/*`
     * in both cases, so there is nothing to sniff.
     *
     * @param array<string, mixed> $result as returned by CommentService::addCommentIfAuthorized()
     */
    private function answer(array $result): ApiResponse
    {
        $code = is_int($result['code'] ?? null) ? $result['code'] : 200;

        if ($this->getRequest()->isXmlHttpRequest()) {
            return new ApiResponse($result, $code);
        }

        $message = (string)($result['error'] ?? $result['success'] ?? '');
        if ($message !== '') {
            $this->getService(FlashMessageService::class)->setMessage($message);
        }

        // The tag comes from the request, NOT from $result: addCommentIfAuthorized() returns
        // code/error/success/html and no page at all, so `$result['pageTag']` would be an empty
        // string and href('', '') resolves to the home page -- a redirect that looks like it
        // worked and quietly loses the reader's place. `pagetag` is the hidden field the form
        // posts, and PageContext is the fallback for a caller that omits it.
        $tag = (string)$this->getRequest()->request->get('pagetag', '');
        if ($tag === '') {
            $tag = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }
        // redirect() throws an ExitException, so nothing after it runs -- phpstan knows, and a
        // `return` here is flagged as unreachable rather than being harmless belt-and-braces
        $this->getService(Redirector::class)->redirect(
            $this->getService(UrlFormatter::class)->href('', $tag)
        );
    }

    #[Route('/api/comments/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function editComment($tag)
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all(), $tag);

        return $this->answer($result);
    }

    // no route: reachable through the canonical POST /api/comments/{tag}/delete below
    public function deleteComment($tag)
    {
        if ($this->getService(AclService::class)->isOwner($tag) || $this->getService(AclService::class)->isAdmin()) {
            $commentService = $this->getService(CommentService::class);
            $errors = $commentService->delete($tag);

            return new ApiResponse(['success' => _t('COMMENT_REMOVED')] + $errors, 200);
        }

        return new ApiResponse(['error' => _t('NOT_AUTORIZED_TO_REMOVE_COMMENT')], 403);
    }

    #[Route('/api/comments/{tag}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deleteCommentViaPostMethod($tag)
    {
        // todo use Anti-Csrf token or Bearer HTTP header
        return $this->deleteComment($tag);
    }
}

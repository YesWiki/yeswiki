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
    public function getAllComments(string $tag = ''): ApiResponse
    {
        return new ApiResponse([$this->getService(CommentService::class)->loadComments($tag)]);
    }

    #[Route('/api/comments', methods: ['POST'], options: ['acl' => ['+']])]
    public function postComment(): ApiResponse
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all());

        return $this->answer($result);
    }

    /**
     * JSON for the page's own script, a redirect back to the page for anyone else.
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

        $tag = (string)$this->getRequest()->request->get('pagetag', '');
        if ($tag === '') {
            $tag = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }

        $this->getService(Redirector::class)->redirect(
            $this->getService(UrlFormatter::class)->href('', $tag)
        );
    }

    #[Route('/api/comments/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function editComment(string $tag): ApiResponse
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all(), $tag);

        return $this->answer($result);
    }

    public function deleteComment(string $tag): ApiResponse
    {
        if ($this->getService(AclService::class)->isOwner($tag) || $this->getService(AclService::class)->isAdmin()) {
            $commentService = $this->getService(CommentService::class);
            $errors = $commentService->delete($tag);

            return new ApiResponse(['success' => _t('COMMENT_REMOVED')] + $errors, 200);
        }

        return new ApiResponse(['error' => _t('NOT_AUTORIZED_TO_REMOVE_COMMENT')], 403);
    }

    #[Route('/api/comments/{tag}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deleteCommentViaPostMethod(string $tag): ApiResponse
    {
        return $this->deleteComment($tag);
    }
}

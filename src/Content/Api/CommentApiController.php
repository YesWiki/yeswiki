<?php

namespace YesWiki\Content\Api;

use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\CommentService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;

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

        return new ApiResponse($result, $result['code']);
    }

    #[Route('/api/comments/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function editComment($tag)
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all(), $tag);

        return new ApiResponse($result, $result['code']);
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

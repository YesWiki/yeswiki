<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;

/** Fetching a backup from another wiki, from the admin backups screen: the browser starts it, then posts 'advance' until it is done. */
class RemoteBackupApiController extends YesWikiController
{
    #[Route('/api/remotebackup', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getRemoteBackupStatus(): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

        try {
            return new ApiResponse($this->getService(RemoteBackupService::class)->status(), Response::HTTP_OK);
        } catch (\Throwable $th) {
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/remotebackup', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function remoteBackupAction(): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
        } catch (TokenNotFoundException $th) {
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_FORBIDDEN);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $post = $this->getRequest()->request;
        $service = $this->getService(RemoteBackupService::class);
        $action = (string)$post->get('action', '');

        try {
            return match ($action) {
                'start' => new ApiResponse($service->start(
                    trim((string)$post->get('url', '')),
                    trim((string)$post->get('username', '')),
                    (string)$post->get('password', '')
                ), Response::HTTP_OK),
                'advance' => new ApiResponse($service->advance(), Response::HTTP_OK),
                'cancel' => new ApiResponse($service->cancel(), Response::HTTP_OK),
                default => new ApiResponse(['error' => "Not supported action : $action"], Response::HTTP_BAD_REQUEST),
            };
        } catch (\Throwable $th) {
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

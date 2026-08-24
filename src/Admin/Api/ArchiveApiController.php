<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Controller\ArchiveController;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ArchiveApiController extends YesWikiController
{
    /** An archive is a zip, so this route does not swallow the named ones beside it. */
    #[Route('/api/archives/{id}', methods: ['GET'], requirements: ['id' => '.+\.zip'], options: ['acl' => ['@admins']])]
    public function getArchive(string $id): Response
    {
        return $this->getService(ArchiveController::class)->getArchive($id);
    }

    #[Route('/api/archives/uidstatus/{uid}', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchiveStatus(string $uid): ApiResponse
    {
        $forceStarted = $this->getRequest()->query->get('forceStarted');

        return $this->getService(ArchiveController::class)->getArchiveStatus(
            $uid,
            !empty($forceStarted) && in_array($forceStarted, [1, true, '1', 'true'], true)
        );
    }

    #[Route('/api/archives/archivingStatus', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchivingStatus(): ApiResponse
    {
        return new ApiResponse(
            $this->getService(ArchiveService::class)->getArchivingStatus(),
            Response::HTTP_OK
        );
    }

    #[Route('/api/archives/forcedUpdateToken', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getForcedUpdateToken(): ApiResponse
    {
        $token = $this->getService(ArchiveService::class)->getForcedUpdateToken();

        return new ApiResponse(
            ['token' => $token],
            empty($token) ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK
        );
    }

    #[Route('/api/archives', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchives(): ApiResponse
    {
        $archiveService = $this->getService(ArchiveService::class);

        return new ApiResponse(
            $archiveService->getArchives(),
            Response::HTTP_OK
        );
    }

    #[Route('/api/archives/{id}', methods: ['POST'], requirements: ['id' => '.+\.zip'], options: ['acl' => ['@admins']])]
    public function archiveAction(string $id): ApiResponse
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction($id);
    }

    #[Route('/api/archives', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function archivesAction(): ApiResponse
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction();
    }
}

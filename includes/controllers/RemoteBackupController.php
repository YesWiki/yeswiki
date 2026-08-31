<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\RemoteBackupService;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class RemoteBackupController extends YesWikiController
{
    protected $remoteBackupService;

    public function __construct(RemoteBackupService $remoteBackupService)
    {
        $this->remoteBackupService = $remoteBackupService;
    }

    public function manageRemoteBackup()
    {
        $security = $this->getService(SecurityController::class);
        $action = $security->filterInput(INPUT_POST, 'action', FILTER_DEFAULT, true);
        try {
            switch ($action) {
                case 'start':
                    return new ApiResponse(
                        $this->remoteBackupService->start(
                            $security->filterInput(INPUT_POST, 'url', FILTER_DEFAULT, true),
                            $security->filterInput(INPUT_POST, 'username', FILTER_DEFAULT, true),
                            (string)($this->getRequest()->request->get('password') ?? '')
                        ),
                        Response::HTTP_OK
                    );
                case 'cancel':
                    return new ApiResponse($this->remoteBackupService->cancel(), Response::HTTP_OK);
                default:
                    return new ApiResponse(
                        ['error' => "Not supported action : $action"],
                        Response::HTTP_BAD_REQUEST
                    );
            }
        } catch (\Throwable $throwable) {
            return new ApiResponse(
                ['error' => $throwable->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Advancing the job can hold the request for a while, so the session lock is released first.
     */
    public function getRemoteBackupStatus()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            return new ApiResponse($this->remoteBackupService->status(), Response::HTTP_OK);
        } catch (\Throwable $throwable) {
            return new ApiResponse(
                ['error' => $throwable->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

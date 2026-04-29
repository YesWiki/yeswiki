<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class ArchiveController extends YesWikiController
{
    protected $archiveService;
    protected $params;

    public function __construct(
        ArchiveService $archiveService,
        ParameterBagInterface $params
    ) {
        $this->archiveService = $archiveService;
        $this->params = $params;
    }

    public function getArchive(string $id)
    {
        try {
            $filePath = $this->archiveService->getFilePath($id);
            if (empty($filePath)) {
                return new ApiResponse(
                    ['error' => 'Not existing file ' . htmlspecialchars($id)],
                    Response::HTTP_BAD_REQUEST
                );
            } else {
                // to prevent existing headers because of handlers /show or others
                $nbObLevels = ob_get_level();
                for ($i = 1; $i < $nbObLevels; $i++) {
                    ob_end_clean();
                }
                for ($i = 1; $i < $nbObLevels; $i++) {
                    ob_start();
                }

                $response = new BinaryFileResponse($filePath);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $id);
                $response->headers->set('Content-Type', 'application/zip');
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Headers', 'X-Requested-With, Location, Slug, Accept, Content-Type');
                $response->headers->set('Access-Control-Expose-Headers', 'Location, Slug, Accept, Content-Type');
                $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, DELETE, PUT, PATCH');
                $response->headers->set('Access-Control-Max-Age', '86400');

                return $response;
            }
        } catch (\Throwable $pThrowable) {
            return new ApiResponse(
                ['error' => 'an exception occures : ' . $this->wiki->dumpThrowable ($pThrowable) ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function manageArchiveAction(?string $id = null)
    {
        try {
            $action = $this->getService(SecurityController::class)->filterInput(INPUT_POST, 'action', FILTER_DEFAULT, true);
            switch ($action) {
                case 'delete':
                    if (!empty($id)) {
                        $filenames = [$id];
                    } elseif (isset($_POST['filesnames']) && is_array($_POST['filesnames'])) {
                        $filenames = $_POST['filesnames'];
                    } else {
                        return new ApiResponse(
                            ['error' => "\$_POST['filesnames'] should be set and be an array for action 'delete'"],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                    $results = $this->archiveService->deleteArchives($filenames);

                    return new ApiResponse(
                        $results,
                        $results['main'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
                    );
                    break;
                case 'startArchive':
                    try {
                        if (isset($_POST['params']) && !is_array($_POST['params'])) {
                            return new ApiResponse(
                                ['error' => "\$_POST['params'] should be set and be an array for action 'startArchive'"],
                                Response::HTTP_BAD_REQUEST
                            );
                        }
                        $params = (isset($_POST['params']) && is_array($_POST['params'])) ? $_POST['params'] : [];
                        $callAsync = !isset($_POST['callAsync']) || in_array($_POST['callAsync'], [1, true, 'true', '1'], true);
                        $uid = $this->startArchive($params, $callAsync);
                        if (empty($uid)) {
                            return new ApiResponse(
                                ['error' => 'no process created when starting archive action'],
                                Response::HTTP_INTERNAL_SERVER_ERROR
                            );
                        }

                        return new ApiResponse(
                            ['uid' => $uid],
                            Response::HTTP_OK
                        );
                    } catch (\Throwable $pThrowable) {
                        return new ApiResponse(
                            ['error' => 'A problem occures while starting the backup process. An exception occures : ' . $this->wiki->dumpThrowable ($pThrowable) ],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    }
                    break;
                case 'stopArchive':
                    if (empty($_POST['uid']) || !is_string($_POST['uid'])) {
                        return new ApiResponse(
                            ['error' => "\$_POST['uid'] should be set and be an string for action 'stopArchive'"],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                    $uid = htmlspecialchars($_POST['uid']);
                    $result = $this->archiveService->stopArchive($uid);

                    return new ApiResponse(
                        [],
                        $result ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
                    );
                    break;
                case 'restore':
                    if (empty($id)) {
                        return new ApiResponse(
                            ['error' => '"api/archives/{id}" should have not empty {id} when using action "restore"'],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                    // TODO update code here when restore will work
                    return new ApiResponse(
                        ['error' => 'action not defined'],
                        Response::HTTP_BAD_REQUEST
                    );
                    break;

                case 'futureDeletedArchives':
                    $files = $this->archiveService->archivesToDelete(true);

                    return new ApiResponse(
                        ['files' => $files],
                        Response::HTTP_OK
                    );
                    break;

                default:
                    return new ApiResponse(
                        ['error' => "Not supported action : $action"],
                        Response::HTTP_BAD_REQUEST
                    );
                    break;
            }
        } catch (\Throwable $pThrowable) {
            return new ApiResponse(
                ['error' => 'an exception occures : ' . $this->wiki->dumpThrowable ($pThrowable) ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getArchiveStatus(string $uid, bool $forceStarted)
    {
        try {
            if (empty($uid)) {
                return new ApiResponse(
                    ['error' => '$uid should not be empty'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return new ApiResponse(
                $this->archiveService->getUIDStatus($uid, $forceStarted),
                Response::HTTP_OK
            );
        } catch (\Throwable $pThrowable) {
            return new ApiResponse(
                ['error' => 'an exception occures : ' . $this->wiki->dumpThrowable ($pThrowable) ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * start archive async or async via CLI.
     *
     * @return string uid
     */
    protected function startArchive(
        array $params = [],
        bool $startAsync = true
    ): string {
        $savefiles = (isset($params['savefiles']) && in_array($params['savefiles'], [1, '1', true, 'true'], true));
        $savedatabase = (isset($params['savedatabase']) && in_array($params['savedatabase'], [1, '1', true, 'true'], true));

        return $this->archiveService->startArchive(
            $savefiles,
            $savedatabase,
            [],
            [],
            $startAsync
        );
    }
}

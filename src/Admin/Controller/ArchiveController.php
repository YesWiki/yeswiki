<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Service\ThrowableFormatter;

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
            }
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
        } catch (\Throwable $pThrowable) {
            return new ApiResponse(
                ['error' => 'an exception occures : ' . $this->getService(ThrowableFormatter::class)->dump($pThrowable)],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function manageArchiveAction(?string $id = null)
    {
        try {
            $action = $this->getService(InputFilter::class)->filterInput(INPUT_POST, 'action', FILTER_DEFAULT, true);
            switch ($action) {
                case 'delete':
                    $post = $this->getRequest()->request;
                    // `$post->all('filesnames')` THROWS a BadRequestException when the value is
                    // not an array, so this guard never fired and the clean 400 below was
                    // unreachable -- a bad request got a Symfony exception instead (ticket 40)
                    $rawFilenames = $post->all()['filesnames'] ?? null;
                    if (!empty($id)) {
                        $filenames = [$id];
                    } elseif (is_array($rawFilenames)) {
                        $filenames = $rawFilenames;
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
                case 'startArchive':
                    try {
                        $post = $this->getRequest()->request;
                        $postParams = $post->all()['params'] ?? null;
                        if ($post->has('params') && !is_array($postParams)) {
                            return new ApiResponse(
                                ['error' => "\$_POST['params'] should be set and be an array for action 'startArchive'"],
                                Response::HTTP_BAD_REQUEST
                            );
                        }
                        $params = is_array($postParams) ? $postParams : [];
                        $callAsync = !$post->has('callAsync') || in_array($post->get('callAsync'), [1, true, 'true', '1'], true);
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
                            ['error' => 'A problem occures while starting the backup process. An exception occures : ' . $this->getService(ThrowableFormatter::class)->dump($pThrowable)],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    }
                case 'stopArchive':
                    $uidRaw = $this->getRequest()->request->get('uid');
                    if (empty($uidRaw) || !is_string($uidRaw)) {
                        return new ApiResponse(
                            ['error' => "\$_POST['uid'] should be set and be an string for action 'stopArchive'"],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                    $uid = htmlspecialchars($uidRaw);
                    $result = $this->archiveService->stopArchive($uid);

                    return new ApiResponse(
                        [],
                        $result ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
                    );
                case 'restore':
                    if (empty($id)) {
                        return new ApiResponse(
                            ['error' => '"api/archives/{id}" should have not empty {id} when using action "restore"'],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                    try {
                        $post = $this->getRequest()->request;
                        $restoreFiles = !$post->has('restoreFiles') || in_array($post->get('restoreFiles'), [1, true, 'true', '1'], true);
                        $restoreDatabase = !$post->has('restoreDatabase') || in_array($post->get('restoreDatabase'), [1, true, 'true', '1'], true);
                        $this->archiveService->restoreArchive($id, $restoreFiles, $restoreDatabase);

                        return new ApiResponse(['success' => true], Response::HTTP_OK);
                    } catch (\Throwable $th) {
                        return new ApiResponse(
                            ['error' => 'Restore failed: ' . $this->getService(ThrowableFormatter::class)->dump($th)],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    }

                case 'futureDeletedArchives':
                    $files = $this->archiveService->archivesToDelete(true);

                    return new ApiResponse(
                        ['files' => $files],
                        Response::HTTP_OK
                    );

                default:
                    return new ApiResponse(
                        ['error' => "Not supported action : $action"],
                        Response::HTTP_BAD_REQUEST
                    );
            }
        } catch (\Throwable $pThrowable) {
            return new ApiResponse(
                ['error' => 'an exception occures : ' . $this->getService(ThrowableFormatter::class)->dump($pThrowable)],
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
                ['error' => 'an exception occures : ' . $this->getService(ThrowableFormatter::class)->dump($pThrowable)],
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

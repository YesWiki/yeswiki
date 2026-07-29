<?php

namespace YesWiki\Content\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Attach;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HtmlPurifierService;

class FileApiController extends YesWikiController
{
    /**
     * Consolidated upload route (ticket 17, replaces tools/attach's legacy upload.php
     * page-handler AND the AJAX qqFileUploader path -- both funneled into the same
     * underlying Attach class already, this is the one real validated path they become).
     * Creates a new, independent "file" Content entry (FileManager), not tied 1:1 to
     * $pageTag afterward -- only used here to seed the new entry's initial read ACL.
     */
    #[Route('/api/files', methods: ['POST'], options: ['acl' => ['public']])]
    public function uploadFile(Request $request)
    {
        $pageTag = (string)$request->request->get('pageTag', '');
        if (empty($pageTag)) {
            return new ApiResponse(['error' => "'pageTag' should not be empty"], Response::HTTP_BAD_REQUEST);
        }
        $this->denyAccessUnlessGranted('write', $pageTag);

        $uploadedFile = $request->files->get('upFile');
        if (empty($uploadedFile) || !$uploadedFile->isValid()) {
            return new ApiResponse(['error' => _t('ERROR_NO_FILE_UPLOADED')], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = $uploadedFile->getClientOriginalName();
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $authorizedExtensions = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['authorized-extensions'] ?? [];
        if (!empty($authorizedExtensions) && !array_key_exists($ext, $authorizedExtensions)) {
            return new ApiResponse(['error' => _t('ERROR_NOT_AUTHORIZED_EXTENSION')], Response::HTTP_BAD_REQUEST);
        }

        $maxFileSize = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['attach_config']['max_file_size']
            ?? $this->getService(ParameterBagInterface::class)->get('max-upload-size');
        if ($uploadedFile->getSize() > $maxFileSize) {
            return new ApiResponse(['error' => _t('ERROR_MAX_FILE_SIZE')], Response::HTTP_BAD_REQUEST);
        }

        // captured before move(): the SplFileInfo/UploadedFile object stops reflecting the
        // original tmp path (and getSize()/getMimeType() start failing) once moved away
        $size = $uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType() ?? '';

        $fileManager = $this->getService(FileManager::class);
        $sanitized = $fileManager->sanitizeFilename($originalFilename);
        $storedFilename = $fileManager->suggestFreeFilename($sanitized);

        $uploadedFile->move(FileManager::STORAGE_DIR, $storedFilename);
        if (in_array($ext, ['svg', 'xml'], true)) {
            $this->getService(HtmlPurifierService::class)->cleanFile(FileManager::STORAGE_DIR . '/' . $storedFilename, $ext);
        }

        $entry = $fileManager->create($originalFilename, $storedFilename, $pageTag, $size, $mimeType);

        return new ApiResponse($entry, Response::HTTP_CREATED);
    }

    /**
     * Consolidated download route (ticket 17, replaces tools/attach's DownloadHandler/
     * doDownload(), which performed NO ownership ACL check at all -- the only external
     * gate was AclService::hasAccess('read') with no tag argument, checking whatever
     * page the current URL happened to resolve to instead of the file's own ACL).
     */
    #[Route('/api/files/{tag}/download', methods: ['GET'], options: ['acl' => ['public']])]
    public function downloadFile(Request $request, string $tag)
    {
        $this->denyAccessUnlessGranted('read', $tag);

        $fileManager = $this->getService(FileManager::class);
        $entry = $fileManager->getOne($tag);
        $path = $fileManager->getPhysicalPath($tag);
        if (empty($entry) || empty($path)) {
            return new ApiResponse(['error' => _t('ATTACH_PARAM_FILE_NOT_FOUND')], Response::HTTP_NOT_FOUND);
        }

        $filename = $entry['original_filename'] ?? basename($path);
        // default inline (so {{attach}}'s <img>/<audio>/<iframe> rendering can point straight
        // at this route now that the bytes no longer live under the web-servable files/ dir);
        // ?download=1 forces a real "Save As" download
        $disposition = !empty($request->query->get('download')) ? 'attachment' : 'inline';

        return new StreamedResponse(
            function () use ($path) {
                readfile($path);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => $entry['mime_type'] ?: 'application/octet-stream',
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => (string)filesize($path),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * List file entries the requester can read, for the file-picker UI (ticket 17).
     */
    #[Route('/api/files', methods: ['GET'], options: ['acl' => ['public']])]
    public function getFiles(Request $request)
    {
        $search = strtolower((string)$request->query->get('search', ''));
        $fileManager = $this->getService(FileManager::class);
        $aclService = $this->getService(AclService::class);

        $entries = [];
        foreach ($fileManager->getAllFileTags() as $tag) {
            if (!$aclService->hasAccess('read', $tag)) {
                continue;
            }
            $entry = $fileManager->getOne($tag);
            if (empty($entry)) {
                continue;
            }
            if (!empty($search) && strpos(strtolower($entry['original_filename'] ?? ''), $search) === false) {
                continue;
            }
            $entries[] = $entry;
        }

        return new ApiResponse($entries, Response::HTTP_OK);
    }
}

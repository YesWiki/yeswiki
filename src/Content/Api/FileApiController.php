<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;

class FileApiController extends YesWikiController
{
    /** Ceiling on a `?width=`/`?height=`: they name a file on disk and arrive in a URL. */
    private const MAX_RESIZE = 4000;

    /** The format every resized copy is written in, whatever the original was. */
    private const DERIVED_FORMAT = 'webp';

    /**
     * Consolidated upload route (ticket 17, replaces tools/attach's legacy upload.php page-handler AND the AJAX qqFileUploader path -- both funneled into the same underlying attach code already, this is the one real validated path they become).
     */
    #[Route('/api/files', methods: ['POST'], options: ['acl' => ['public']])]
    public function uploadFile(Request $request): ApiResponse
    {
        $pageTag = (string)$request->request->get('pageTag', '');
        if (empty($pageTag)) {
            return new ApiResponse(['error' => "'pageTag' should not be empty"], Response::HTTP_BAD_REQUEST);
        }
        $this->denyAccessUnlessGranted('write', $pageTag);

        $uploadedFile = $request->files->get('upFile');
        if (empty($uploadedFile)) {
            return new ApiResponse(['error' => _t('ERROR_NO_FILE_UPLOADED')], Response::HTTP_BAD_REQUEST);
        }

        $fileManager = $this->getService(FileManager::class);
        try {
            $stored = $fileManager->storeUpload($uploadedFile);
        } catch (\InvalidArgumentException $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $entry = $fileManager->create(
            $stored['original_filename'],
            $stored['stored_filename'],
            $pageTag,
            $stored['size'],
            $stored['mime_type'],
        );

        return new ApiResponse($this->withDerivedAttributes($entry), Response::HTTP_CREATED);
    }

    /**
     * A file entry as this API hands it out: its stored body, plus the extension and the family that follow from it.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function withDerivedAttributes(array $entry): array
    {
        $filename = (string)($entry['original_filename'] ?? '');
        $entry['extension'] = FileManager::extensionOf($filename);
        $entry['family'] = FileManager::familyOf((string)($entry['mime_type'] ?? ''), $filename);

        return $entry;
    }

    /**
     * Consolidated download route (ticket 17, replaces tools/attach's DownloadHandler/ doDownload(), which performed NO ownership ACL check at all -- the only external gate was AclService::hasAccess('read') with no tag argument, checking whatever page the current URL happened to resolve to instead of the file's own ACL).
     */
    #[Route('/api/files/{tag}/download', methods: ['GET'], options: ['acl' => ['public']])]
    public function downloadFile(Request $request, string $tag): Response
    {
        $this->denyAccessUnlessGranted('read', $tag);

        $fileManager = $this->getService(FileManager::class);
        $entry = $fileManager->getOne($tag);
        $path = $fileManager->getPhysicalPath($tag);
        if (empty($entry) || empty($path)) {
            return new ApiResponse(['error' => _t('ATTACH_PARAM_FILE_NOT_FOUND')], Response::HTTP_NOT_FOUND);
        }

        $filename = $entry['original_filename'] ?? basename($path);

        $mimeType = $entry['mime_type'] ?: 'application/octet-stream';
        $resized = $this->resizedCopy($request, $path);
        if ($resized !== null) {
            $path = $resized;
            $mimeType = 'image/' . self::DERIVED_FORMAT;
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . self::DERIVED_FORMAT;
        }

        $disposition = !empty($request->query->get('download')) ? 'attachment' : 'inline';

        $storage = $this->getService(Storage::class);

        return new StreamedResponse(
            function () use ($path, $storage) {
                $bytes = $storage->readStream($path);
                fpassthru($bytes);
                fclose($bytes);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => (string)$storage->fileSize($path),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * The WebP copy this request asked for, generated on first use, or null when it asked for none or the file is not a raster image.
     */
    private function resizedCopy(Request $request, string $path): ?string
    {
        $width = (int)$request->query->get('width', 0);
        $height = (int)$request->query->get('height', 0);
        $storage = $this->getService(Storage::class);
        $size = $storage->imageSize($path);
        if ($width < 1 || $height < 1 || $size === false) {
            return null;
        }
        if (!in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $width = min($width, self::MAX_RESIZE);
        $height = min($height, self::MAX_RESIZE);
        $mode = $request->query->get('mode') === 'crop' ? 'crop' : 'fit';

        if ($mode === 'fit') {
            $width = min($width, $size[0]);
            $height = min($height, $size[1]);
        }

        $destination = FileManager::STORAGE_DIR . '/cache/' . pathinfo($path, PATHINFO_FILENAME)
            . "_{$mode}_{$width}_{$height}." . self::DERIVED_FORMAT;

        if ($storage->fileExists($destination)) {
            return $destination;
        }

        return $this->getService(ImageResizer::class)->resize($path, $destination, $width, $height, $mode) === $destination
            ? $destination
            : null;
    }

    /** List file entries the requester can read, for the file-picker UI (ticket 17). */
    #[Route('/api/files', methods: ['GET'], options: ['acl' => ['public']])]
    public function getFiles(Request $request): ApiResponse
    {
        $search = strtolower((string)$request->query->get('search', ''));
        $family = (string)$request->query->get('family', '');
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

            $entry = $this->withDerivedAttributes($entry);
            if (!empty($family) && $entry['family'] !== $family) {
                continue;
            }

            if (!empty($search)
                && !str_contains(strtolower((string)($entry['original_filename'] ?? '')), $search)
                && !str_contains($entry['extension'], $search)) {
                continue;
            }
            $entries[] = $entry;
        }

        return new ApiResponse(array_reverse($entries), Response::HTTP_OK);
    }
}

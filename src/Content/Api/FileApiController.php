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
use YesWiki\Identity\Service\AclService;

class FileApiController extends YesWikiController
{
    /** Ceiling on a `?width=`/`?height=`: they name a file on disk and arrive in a URL. */
    private const MAX_RESIZE = 4000;

    /**
     * Consolidated upload route (ticket 17, replaces tools/attach's legacy upload.php
     * page-handler AND the AJAX qqFileUploader path -- both funneled into the same
     * underlying attach code already, this is the one real validated path they become).
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
        if (empty($uploadedFile)) {
            return new ApiResponse(['error' => _t('ERROR_NO_FILE_UPLOADED')], Response::HTTP_BAD_REQUEST);
        }

        $fileManager = $this->getService(FileManager::class);
        try {
            // validation, sanitising and the SVG/XML purge all live in one place now, so
            // the form field cannot end up with a laxer set of them (ticket 13)
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
     * A file entry as this API hands it out: its stored body, plus the extension and the
     * family that follow from it. Derived on the way out rather than written into every
     * body, so an existing file gains a family the moment FileManager learns a new
     * extension, with no migration -- and so the freshly uploaded entry this route
     * returns is shaped exactly like the ones the listing returns.
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
        // `?width=&height=` serves a resized copy instead of the original -- what a bazar
        // image field asks for when its thumbnail sizes are set. It is served from HERE,
        // through the same ACL check above, and cached under private/ with the bytes:
        // a thumbnail dropped in the public cache/ directory would be a readable copy of
        // a file whose whole point is that reading it is checked.
        $resized = $this->resizedCopy($request, $path);
        if ($resized !== null) {
            $path = $resized;
        }
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
     * The resized copy this request asked for, generated on first use -- or null when it
     * asked for none, or when the file is not an image, or when resizing failed.
     *
     * Failure is null rather than an error: the caller wanted a smaller picture and gets
     * the picture. Sizes are clamped because they name a file on disk and arrive in a URL.
     */
    private function resizedCopy(Request $request, string $path): ?string
    {
        $width = (int)$request->query->get('width', 0);
        $height = (int)$request->query->get('height', 0);
        if ($width < 1 || $height < 1 || @getimagesize($path) === false) {
            return null;
        }
        $width = min($width, self::MAX_RESIZE);
        $height = min($height, self::MAX_RESIZE);
        $mode = $request->query->get('mode') === 'crop' ? 'crop' : 'fit';

        $cacheDir = FileManager::STORAGE_DIR . '/cache';
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $destination = $cacheDir . '/' . pathinfo($path, PATHINFO_FILENAME)
            . "_{$mode}_{$width}_{$height}" . ($extension === '' ? '' : ".{$extension}");

        if (file_exists($destination)) {
            return $destination;
        }

        return $this->getService(ImageResizer::class)->resize($path, $destination, $width, $height, $mode) === $destination
            ? $destination
            : null;
    }

    /**
     * List file entries the requester can read, for the file-picker UI (ticket 17).
     *
     * `search` narrows by filename or extension; `family` to one of
     * FileManager::FAMILIES. Both are answered here rather than left to the caller so
     * that any consumer filters the same way the picker does -- but the picker itself
     * asks for the whole list once per opening and narrows in the browser, which is
     * what makes its filter counts exact and its typing instant. That trade holds while
     * a wiki's uploads number in the thousands; past that, this route needs paging and
     * the picker needs to ask it per keystroke again.
     */
    #[Route('/api/files', methods: ['GET'], options: ['acl' => ['public']])]
    public function getFiles(Request $request)
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
            // the extension is part of what is searched: typing "pdf" is how someone
            // looks for a PDF, and the filename alone would only match it by accident
            if (!empty($search)
                && !str_contains(strtolower((string)($entry['original_filename'] ?? '')), $search)
                && !str_contains($entry['extension'], $search)) {
                continue;
            }
            $entries[] = $entry;
        }

        // newest first: the file someone is looking for in an editor is usually the one
        // they just uploaded, and getAllFileTags() hands them back oldest-first
        return new ApiResponse(array_reverse($entries), Response::HTTP_OK);
    }
}

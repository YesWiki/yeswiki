<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * Every file the wiki holds, for the screen that administers them.
 *
 * Distinct from the public `/api/files`, which loads every file entry and filters them in PHP for
 * a picker that shows a page's worth: this one pages in SQL, because an administrator's question
 * is "all of them" and a wiki can hold thousands. Upload here takes no owning page -- a file added
 * from the file manager belongs to the wiki rather than to a page, so it gets the default ACL
 * instead of a page's, and the public route keeps its `write` check on a page tag untouched.
 */
class AdminFilesApiController extends YesWikiController
{
    private const ALLOWED_SORTS = ['name', 'time', 'size'];
    private const ALLOWED_PERPAGES = [25, 50, 100, 200];

    #[Route('/api/admin/files', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getFiles(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        $dbService = $this->getService(DbService::class);
        $urlFormatter = $this->getService(UrlFormatter::class);

        $page = max(1, (int)$request->query->get('page', 1));
        $perpage = (int)$request->query->get('perpage', 50);
        if (!in_array($perpage, self::ALLOWED_PERPAGES, true)) {
            $perpage = 50;
        }
        $sort = (string)$request->query->get('sort', 'time');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'time';
        }
        $dir = strtolower((string)$request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $search = trim((string)$request->query->get('search', ''));
        $family = (string)$request->query->get('family', '');

        $pT = $dbService->prefixTable('pages');
        $typeCol = $dbService->quoteIdentifier('type');
        $timeCol = $dbService->quoteIdentifier('time');
        $nameExpr = $dbService->jsonExtractText('p.body', '$.original_filename');
        $sizeExpr = $dbService->castToInteger($dbService->jsonExtractText('p.body', '$.size'));
        $sortColumns = ['name' => $nameExpr, 'time' => "p.{$timeCol}", 'size' => $sizeExpr];

        $where = ["p.latest = 'Y'", "p.{$typeCol} = ?"];
        $params = [PageType::FILE];
        if ($search !== '') {
            $where[] = "LOWER({$nameExpr}) LIKE ?";
            $params[] = '%' . strtolower($search) . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perpage;

        $rows = $dbService->loadAll(
            "SELECT p.tag, p.body, p.owner, p.{$timeCol} AS time
             FROM {$pT} p WHERE {$whereClause}
             ORDER BY {$sortColumns[$sort]} {$dir}
             LIMIT ? OFFSET ?",
            [...$params, $perpage, $offset]
        );

        $total = (int)($dbService->loadSingle(
            "SELECT COUNT(*) AS total FROM {$pT} p WHERE {$whereClause}",
            $params
        )['total'] ?? 0);

        $files = [];
        foreach ($rows as $row) {
            $body = is_array($row['body'] ?? null) ? $row['body'] : (json_decode((string)($row['body'] ?? ''), true) ?: []);
            $filename = (string)($body['original_filename'] ?? '');
            $entryFamily = FileManager::familyOf((string)($body['mime_type'] ?? ''), $filename);
            if ($family !== '' && $entryFamily !== $family) {
                continue;
            }
            $files[] = [
                'tag' => (string)$row['tag'],
                'name' => $filename,
                'extension' => FileManager::extensionOf($filename),
                'family' => $entryFamily,
                'mimeType' => (string)($body['mime_type'] ?? ''),
                'size' => (int)($body['size'] ?? 0),
                'uploadedFrom' => (string)($body['uploaded_from'] ?? ''),
                'owner' => (string)($row['owner'] ?? ''),
                'time' => (string)$row['time'],
                'downloadUrl' => $urlFormatter->href('', 'api/files/' . rawurlencode((string)$row['tag']) . '/download'),
            ];
        }

        return new ApiResponse([
            'files' => $files,
            'total' => $total,
            'currentPage' => $page,
            'perpage' => $perpage,
            'totalPages' => max(1, (int)ceil($total / $perpage)),
        ], Response::HTTP_OK);
    }

    #[Route('/api/admin/files', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function uploadFile(Request $request): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

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
            '',
            $stored['size'],
            $stored['mime_type'],
        );

        return new ApiResponse($entry, Response::HTTP_CREATED);
    }

    #[Route('/api/admin/files/{tag}', methods: ['DELETE'], options: ['acl' => ['@admins']])]
    public function deleteFile(string $tag): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

        $fileManager = $this->getService(FileManager::class);
        if (!$fileManager->isFileTag($tag)) {
            return new ApiResponse(['error' => _t('ADMIN_FILES_NOT_FOUND')], Response::HTTP_NOT_FOUND);
        }
        $fileManager->delete($tag);

        return new ApiResponse(['deleted' => $tag], Response::HTTP_OK);
    }
}

<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\DiffService;
use YesWiki\Content\Service\DuplicationManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\MarkdownFormatterService;

class PageApiController extends YesWikiController
{
    #[Route('/api/pages', options: ['acl' => ['public']])]
    public function getAllPages()
    {
        $dbService = $this->getService(DbService::class);
        $aclService = $this->getService(AclService::class);
        // recuperation des pages wikis
        $sql = <<<SQL
            SELECT * FROM {$dbService->prefixTable('pages')}
            WHERE latest='Y' AND comment_on='' AND tag NOT LIKE 'LogDesActionsAdministratives%'
            AND tag NOT IN (SELECT resource FROM {$dbService->prefixTable('triples')} WHERE property='http://outils-reseaux.org/_vocabulary/type')
            ORDER BY tag ASC
        SQL;
        $pages = $dbService->loadAll($sql);
        $pages = array_filter($pages, function ($page) use ($aclService) {
            return $aclService->hasAccess('read', $page['tag']);
        });
        $pagesWithTag = [];
        foreach ($pages as $page) {
            $pagesWithTag[$page['tag']] = $page;
        }

        return new ApiResponse(empty($pagesWithTag) ? null : $pagesWithTag);
    }

    #[Route('/api/pages/{tag}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getPage(Request $request, $tag)
    {
        $this->denyAccessUnlessGranted('read', $tag);

        $pageManager = $this->getService(PageManager::class);
        $diffService = $this->getService(DiffService::class);
        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);
        $page = $pageManager->getOne($tag, $request->get('time'));
        if (!$page) {
            return new ApiResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($entryManager->isEntry($page['tag'])) {
            $page['html'] = $entryController->view($page['tag'], $page['time'], false);
            $page['code'] = $diffService->formatJsonCodeIntoHtmlTable($page);
        } else {
            $page['html'] = $this->getService(MarkdownFormatterService::class)->format($page['body']);
            $page['code'] = $page['body'];
        }

        if ($request->get('includeDiff')) {
            $prevVersion = $pageManager->getPreviousRevision($page);
            if (!$prevVersion) {
                $prevVersion = ['tag' => $tag, 'body' => '', 'time' => null];
            }
            $page['commit_diff_html'] = $diffService->getPageDiff($prevVersion, $page, true);
            $page['commit_diff_code'] = $diffService->getPageDiff($prevVersion, $page, false);

            $lastVersion = $pageManager->getOne($page['tag']);
            $page['diff_html'] = $diffService->getPageDiff($lastVersion, $page, true);
            $page['diff_code'] = $diffService->getPageDiff($lastVersion, $page, false);
        }

        return new ApiResponse($page);
    }

    #[Route('/api/pages/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function savePage(Request $request, $tag)
    {
        $this->denyAccessUnlessGranted('write', $tag);

        $body = $request->request->get('body');
        if ($body === null) {
            return new ApiResponse(['error' => "'body' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $pageManager = $this->getService(PageManager::class);
        $pageManager->save($tag, strval($body));

        $page = $pageManager->getOne($tag);

        return new ApiResponse($page, Response::HTTP_OK);
    }

    /**
     * Relocated from tools/templates's savemetadatas AJAX handler (ticket 12) - saves
     * per-page theme/style/squelette/background-image overrides. loadmetadatas had zero
     * callers and was simply deleted, not relocated.
     */
    #[Route('/api/pages/{tag}/metadatas', methods: ['POST'], options: ['acl' => ['+']])]
    public function savePageMetadatas(Request $request, $tag)
    {
        $this->denyAccessUnlessGranted('write', $tag);

        $metadatas = $request->request->all('metadatas');
        if (empty($metadatas)) {
            return new ApiResponse(['error' => "'metadatas' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $pageManager = $this->getService(PageManager::class);
        $pageManager->setMetadata($tag, $metadatas);

        return new ApiResponse($pageManager->getMetadata($tag), Response::HTTP_OK);
    }

    #[Route('/api/pages/{tag}/duplicate', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function duplicatePage(Request $request, $tag)
    {
        $this->denyAccessUnlessAdmin();
        $duplicationManager = $this->getService(DuplicationManager::class);
        try {
            $duplicationManager->importDistantContent($tag, $request);
        } catch (\Throwable $th) {
            return new ApiResponse($th->getMessage(), Response::HTTP_FORBIDDEN);
        }

        return new ApiResponse($request->request->all(), Response::HTTP_OK);
    }

    // no route: reachable through the canonical POST /api/pages/{tag}/delete below
    public function deletePage($tag)
    {
        $pageManager = $this->getService(PageManager::class);
        $pageOperationsService = $this->getService(PageOperationsService::class);
        $dbService = $this->getService(DbService::class);

        $result = [
            'notDeleted' => [$tag],
        ];
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        try {
            $page = $pageManager->getOne($tag, null, false);
            if (empty($page)) {
                $code = Response::HTTP_NOT_FOUND;
            } else {
                $tag = isset($page['tag']) ? $page['tag'] : $tag;
                $result['notDeleted'] = [$tag];
                if ($this->getService(AclService::class)->isOwner($tag) || $this->getService(AclService::class)->isAdmin()) {
                    if (!$pageManager->isOrphaned($tag)) {
                        $dbService->query("DELETE FROM {$dbService->prefixTable('links')} WHERE to_tag = '{$dbService->escape($tag)}'");
                    }
                    $done = $pageOperationsService->delete($tag);
                    if (!$done || !empty($pageManager->getOne($tag, null, false))) {
                        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                    } else {
                        $result['deleted'] = [$tag];
                        unset($result['notDeleted']);
                        $code = Response::HTTP_OK;
                    }
                } else {
                    $code = Response::HTTP_UNAUTHORIZED;
                }
            }
        } catch (\Throwable $th) {
            try {
                $page = $pageManager->getOne($tag, null, false);
                $result['error'] = $th->getMessage();
                if (!empty($page)) {
                    $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                } else {
                    $code = Response::HTTP_OK;
                    unset($result['notDeleted']);
                    $result['deleted'] = [$tag];
                }
            } catch (\Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result['error'] = $th->getMessage();
            }
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/pages/{tag}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deletePageViaPostMethod($tag)
    {
        $result = [];
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        try {
            $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);
            $csrfTokenChecker->checkToken('main', 'POST', 'csrfToken', false);
        } catch (TokenNotFoundException $th) {
            $code = Response::HTTP_UNAUTHORIZED;
            $result = [
                'notDeleted' => [$tag],
                'error' => $th->getMessage(),
            ];
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'notDeleted' => [$tag],
                'error' => $th->getMessage(),
            ];
        }

        return (empty($result))
            ? $this->deletePage($tag)
            : new ApiResponse($result, $code);
    }
}

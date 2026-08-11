<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\DiffService;
use YesWiki\Content\Service\DuplicationManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\MarkdownFormatterService;

class PageApiController extends YesWikiController
{
    /** The comment ACL that means "nobody", as ClaimHandler wrote it before ticket 35. */
    private const COMMENTS_CLOSED = 'comments-closed';

    #[Route('/api/pages', options: ['acl' => ['public']])]
    public function getAllPages()
    {
        $dbService = $this->getService(DbService::class);
        $aclService = $this->getService(AclService::class);
        $pageType = PageType::PAGE;
        // recuperation des pages wikis
        $sql = <<<SQL
            SELECT * FROM {$dbService->prefixTable('pages')}
            WHERE latest='Y' AND parent='' AND tag NOT LIKE 'LogDesActionsAdministratives%'
            AND {$dbService->quoteIdentifier('type')} = '{$pageType}'
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
            $page['code'] = PageBody::content($page['body']);
            $page['html'] = $this->getService(MarkdownFormatterService::class)->format($page['code']);
        }

        if ($request->get('includeDiff')) {
            $prevVersion = $pageManager->getPreviousRevision($page);
            if (!$prevVersion) {
                $prevVersion = ['tag' => $tag, 'body' => [], 'time' => null];
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
        $pageManager->save($tag, [PageBody::CONTENT => strval($body)]);

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

    /**
     * Take ownership of a page that has none (ticket 35, was `/PageName/claim`).
     *
     * Only an unowned page, and only for someone signed in: this grants the caller write access to
     * a page they did not have it on, so the "no current owner" test is the whole security model.
     * An owned page answers 409 rather than silently doing nothing, because "I clicked claim and
     * nothing happened" is indistinguishable from a bug.
     */
    #[Route('/api/pages/{tag}/claim', methods: ['POST'], options: ['acl' => ['+']])]
    public function claimPage(string $tag): ApiResponse
    {
        $pageManager = $this->getService(PageManager::class);
        if (empty($pageManager->getOne($tag))) {
            return new ApiResponse(['error' => _t('NOT_FOUND')], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        if (empty($user['name'])) {
            return new ApiResponse(['error' => _t('LOGIN_NO_CONNECTED_USER')], Response::HTTP_UNAUTHORIZED);
        }
        if (!empty($pageManager->getOwner($tag))) {
            return new ApiResponse(['error' => _t('YW_PAGE_ALREADY_OWNED')], Response::HTTP_CONFLICT);
        }

        $pageManager->setOwner($tag, (string)$user['name']);

        return new ApiResponse([
            'success' => _t('YW_YOU_ARE_NOW_OWNER_OF_PAGE'),
            'owner' => (string)$user['name'],
        ]);
    }

    /**
     * Open or close comments on a page (ticket 35, was `/PageName/claim&action=opencomments`).
     *
     * `access` is a group name, `+` for any signed-in user, or `closed`. Owner or admin only --
     * comment access is a permission, so handing it out is itself a privileged act.
     */
    #[Route('/api/pages/{tag}/comments-access', methods: ['POST'], options: ['acl' => ['+']])]
    public function setCommentsAccess(string $tag, Request $request): ApiResponse
    {
        $aclService = $this->getService(AclService::class);
        if (!$aclService->isAdmin() && !$aclService->isOwner($tag)) {
            return new ApiResponse(['error' => _t('LOGIN_NOT_AUTORIZED')], Response::HTTP_FORBIDDEN);
        }
        if (empty($this->getService(PageManager::class)->getOne($tag))) {
            return new ApiResponse(['error' => _t('NOT_FOUND')], Response::HTTP_NOT_FOUND);
        }

        $access = trim((string)$request->request->get('access', ''));
        if ($access === 'closed') {
            $aclService->save($tag, 'comment', self::COMMENTS_CLOSED);

            return new ApiResponse(['success' => _t('YW_COMMENTS_ARE_NOW_CLOSED')]);
        }

        // A group that does not exist would be saved verbatim and match nobody -- comments would
        // read as open and be closed in practice. `+` is every signed-in user.
        $groups = $this->getService(GroupOperationsService::class)->getAll();
        if ($access !== '+' && !in_array($access, $groups, true)) {
            return new ApiResponse(['error' => _t('YW_PROBLEM_WITH_ACLS_LIST')], Response::HTTP_BAD_REQUEST);
        }

        $aclService->save($tag, 'comment', $access);

        return new ApiResponse(['success' => _t('YW_COMMENTS_ARE_NOW_OPEN')]);
    }

    /**
     * A page's body as XML, with its `{{action}}` calls rendered (ticket 35, was `/PageName/xml`).
     *
     * Only the body, not the page's other properties -- that was true of the handler too, and
     * changing it would change what existing consumers receive. `GET /api/pages/{tag}` is the route
     * that answers with everything.
     */
    #[Route('/api/pages/{tag}/xml', methods: ['GET'], options: ['acl' => ['public']])]
    public function getPageAsXml(string $tag): Response
    {
        $declaration = '<?xml version="1.0" encoding="' . YW_CHARSET . '"?>';
        $headers = ['Content-Type' => 'text/xml; charset=' . YW_CHARSET];

        $page = $this->getService(PageManager::class)->getOne($tag);
        // An unreadable or absent page answers an empty document rather than 403/404: this replaces
        // a handler that did exactly that, and a feed-shaped consumer handles an empty document
        // better than it handles an error status it was never written to expect.
        if (empty($page) || !$this->getService(AclService::class)->hasAccess('read', $tag)) {
            return new Response($declaration, Response::HTTP_OK, $headers);
        }

        return new Response(
            $declaration . $this->getService(MarkdownFormatterService::class)->renderActionsOnly(
                PageBody::content($page['body'] ?? [])
            ),
            Response::HTTP_OK,
            $headers
        );
    }
}

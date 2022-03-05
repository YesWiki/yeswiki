<?php

namespace YesWiki\Core\Controller;

use Throwable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DiffService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api",options={"acl":{"public"}})
     */
    public function getDocumentation()
    {
        $output = '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/users');
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
            '<p><code>GET '.$urlUser.'</code></p>';

        $urlGroup = $this->wiki->Href('', 'api/groups');
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
            '<p><code>GET '.$urlGroup.'</code></p>';

        $urlPages = $this->wiki->Href('', 'api/pages');
        $output .= '<h2>'._t('PAGES').'</h2>'."\n".
            '<p><code>GET '.$urlPages.'</code></p>';
        $urlPagesComments = $this->wiki->Href('', 'api/pages/{pageTag}/comments');
        $output .= '<p><code>GET '.$urlPagesComments.'</code></p>';

        $urlComments = $this->wiki->Href('', 'api/comments');
        $output .= '<h2>'._t('COMMENTS').'</h2>'."\n".
            '<p><code>GET '.$urlComments.'</code></p>';

        // TODO use annotations to document the API endpoints
        $extensions = $this->wiki->extensions;
        foreach ($this->wiki->extensions as $extension => $pluginBase) {
            $response = null ;
            if (file_exists($pluginBase . 'controllers/ApiController.php')) {
                $apiClassName = 'YesWiki\\' . ucfirst($extension) . '\\Controller\\ApiController';
                if (!class_exists($apiClassName, false)) {
                    include($pluginBase . 'controllers/ApiController.php') ;
                }
                if (class_exists($apiClassName, false)) {
                    $apiController = new $apiClassName() ;
                    $apiController->setWiki($this->wiki);
                    if (method_exists($apiController, 'getDocumentation')) {
                        $response = $apiController->getDocumentation() ;
                    }
                }
            }
            if (empty($response)) {
                $func = 'documentation'.ucfirst(strtolower($extension));
                if (function_exists($func)) {
                    $output .= $func();
                }
            } else {
                $output .= $response ;
            }
        }

        $output = $this->wiki->Header().'<div class="api-container">'.$output.'</div>'.$this->wiki->Footer();

        return new Response($output);
    }

    /**
     * @Route("/api/users/{userId}")
     */
    public function getUser($userId)
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOne($userId));
    }

    /**
     * @Route("/api/users", options={"acl":{"public"}})
     */
    public function getAllUsers($userFields = ['name', 'email', 'signuptime'])
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getAll($userFields));
    }

    /**
     * @Route("/api/comments/{tag}",methods={"GET"}, options={"acl":{"public"}})
     */
    public function getAllComments($tag = '')
    {
        return new ApiResponse([$this->getService(CommentService::class)->loadComments($tag)]);
    }

    /**
     * @Route("/api/comments",methods={"POST"}, options={"acl":{"public","+"}})
     */
    public function postComment()
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAutorized($_POST);
        return new ApiResponse($result, $result['code']);
    }

    /**
     * @Route("/api/comments/{tag}",methods={"POST"}, options={"acl":{"public","+"}})
     */
    public function editComment($tag)
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAutorized($_POST, $tag);
        return new ApiResponse($result, $result['code']);
    }

    /**
     * @Route("/api/comments/{tag}",methods={"DELETE"}, options={"acl":{"public","+"}})
     */
    public function deleteComment($tag)
    {
        if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
            $pageManager = $this->getService(PageManager::class);
            $commentService = $this->getService(CommentService::class);
            // delete children comments
            $comments = $commentService->loadComments($tag);
            foreach ($comments as $com) {
                $pageManager->deleteOrphaned($com['tag']);
            }
            $pageManager->deleteOrphaned($tag);
            return new ApiResponse(['success' => _t('COMMENT_REMOVED')], 200);
        } else {
            return new ApiResponse(['error' => _t('NOT_AUTORIZED_TO_REMOVE_COMMENT')], 403);
        }
    }
    /**
     * @Route("/api/comments/{tag}/delete",methods={"GET"}, options={"acl":{"public","+"}})
     */
    public function deleteCommentByGetMethod($tag)
    {
        return $this->deleteComment($tag);
    }

    /**
     * @Route("/api/groups", options={"acl":{"public"}})
     */
    public function getAllGroups()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->wiki->GetGroupsList());
    }

    /**
     * @Route("/api/pages", options={"acl":{"public"}})
     */
    public function getAllPages()
    {
        $dbService = $this->getService(DbService::class);
        $aclService = $this->getService(AclService::class);
        // recuperation des pages wikis
        $sql = 'SELECT * FROM '.$dbService->prefixTable('pages');
        $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
        $sql .= ' AND tag NOT IN (SELECT resource FROM '.$dbService->prefixTable('triples').' WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
        $sql .= ' ORDER BY tag ASC';
        $pages = _convert($dbService->loadAll($sql), 'ISO-8859-15');
        $pages = array_filter($pages, function ($page) use ($aclService) {
            return $aclService->hasAccess('read', $page["tag"]);
        });
        $pagesWithTag = [];
        foreach ($pages as $page) {
            $pagesWithTag[$page['tag']] = $page;
        }
        return new ApiResponse(empty($pagesWithTag) ? null : $pagesWithTag);
    }

    /**
     * @Route("/api/pages/{tag}",methods={"GET"},options={"acl":{"public"}})
     */
    public function getPage(Request $request, $tag)
    {
        ob_start(); // to catch error messages
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
            $page['html'] = $this->wiki->Format($page["body"], 'wakka', $page['tag']);
            $page['code'] = $page['body'];
        }

        if ($request->get('includeDiff')) {
            $prevVersion = $pageManager->getPreviousRevision($page);
            if (!$prevVersion) {
                $prevVersion = ["tag" => $tag, "body" => "", "time" => null];
            }
            $page['commit_diff_html'] = $diffService->getPageDiff($prevVersion, $page, true);
            $page['commit_diff_code'] = $diffService->getPageDiff($prevVersion, $page, false);

            $lastVersion = $pageManager->getOne($page['tag']);
            $page['diff_html'] = $diffService->getPageDiff($lastVersion, $page, true);
            $page['diff_code'] = $diffService->getPageDiff($lastVersion, $page, false);
        }

        $errors = ob_get_contents();
        ob_end_clean();
        return new ApiResponse((empty($errors) ? [] : ['errors' => $errors])+$page);
    }

    /**
     * @Route("/api/pages/{tag}",methods={"DELETE"},options={"acl":{"public","+"}})
     */
    public function deletePage($tag)
    {
        ob_start(); // to catch error messages
        $pageManager = $this->getService(PageManager::class);
        $dbService = $this->getService(DbService::class);

        $result = [];
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        try {
            $page = $pageManager->getOne($tag, null, false);
            if (empty($page)) {
                $code = Response::HTTP_NOT_FOUND;
            } else {
                $tag = isset($page['tag']) ? $page['tag'] : $tag;
                if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
                    if (!$pageManager->isOrphaned($tag)) {
                        $dbService->query("DELETE FROM {$dbService->prefixTable('links')} WHERE to_tag = '$tag'");
                    }
                    $pageManager->deleteOrphaned($tag);
                    $page = $pageManager->getOne($tag, null, false);
                    if (!empty($page)) {
                        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                        $result = [
                            'notDeleted' => [$tag]
                        ];
                    } else {
                        $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), "Suppression de la page ->\"\"$tag\"\"", false);
                        $result = [
                            'deleted' => [$tag]
                        ];
                        $code = Response::HTTP_OK;
                    }
                } else {
                    $code = Response::HTTP_UNAUTHORIZED;
                }
            }
        } catch (Throwable $th) {
            try {
                $page = $pageManager->getOne($tag, null, false);
                if (!empty($page)) {
                    $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                    $result = [
                        'notDeleted' => [$tag],
                        'error' => $th->getMessage()
                    ];
                } else {
                    $code = Response::HTTP_OK;
                    $result = [
                        'deleted' => [$tag],
                        'error' => $th->getMessage()
                    ];
                }
            } catch (Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result = [
                    'notDeleted' => [$tag],
                    'error' => $th->getMessage()
                ];
            }
        }

        $errors = ob_get_contents();
        ob_end_clean();
        return new ApiResponse((empty($errors) ? [] : ['errors' => $errors])+$result, $code);
    }
}

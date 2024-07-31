<?php

namespace YesWiki\Core\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Throwable;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DiffService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api",options={"acl":{"public"}})
     */
    public function getDocumentation()
    {
        $output = '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/users');
        $output .= '<h2>' . _t('USERS') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlUser . '</code></p>';

        $urlGroup = $this->wiki->Href('', 'api/groups');
        $output .= '<h2>' . _t('GROUPS') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlGroup . '</code></p>';

        $urlPages = $this->wiki->Href('', 'api/pages');
        $output .= '<h2>' . _t('PAGES') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlPages . '</code></p>';
        $urlPagesComments = $this->wiki->Href('', 'api/pages/{pageTag}/comments');
        $output .= '<p><code>GET ' . $urlPagesComments . '</code></p>';

        $urlComments = $this->wiki->Href('', 'api/comments');
        $output .= '<h2>' . _t('COMMENTS') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlComments . '</code></p>';

        $urlTriples = $this->wiki->Href('', 'api/triples/{resource}', ['property' => 'http://outils-reseaux.org/_vocabulary/type', 'user' => 'username'], false);
        $output .= '<h2>' . _t('TRIPLES') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlTriples . '</code></p>';

        $urlArchives = $this->wiki->Href('', 'api/archives');
        $output .= '<h2>' . _t('ARCHIVES') . '</h2>' . "\n" .
            '<p>' . _t('ONLY_FOR_ADMINS') . '</p>' .
            '<p><code>GET ' . $urlArchives . '</code></p>' .
            '<p><code>GET ' . $urlArchives . '/{id}</code></p>' .
            '<p><code>POST ' . $urlArchives . '</code></p>' .
            '<p><code>POST ' . $urlArchives . '/{id}</code></p>';

        // TODO use annotations to document the API endpoints
        $extensions = $this->wiki->extensions;
        foreach ($this->wiki->extensions as $extension => $pluginBase) {
            $response = null;
            if (file_exists($pluginBase . 'controllers/ApiController.php')) {
                $apiClassName = 'YesWiki\\' . ucfirst($extension) . '\\Controller\\ApiController';
                if (!class_exists($apiClassName, false)) {
                    include $pluginBase . 'controllers/ApiController.php';
                }
                if (class_exists($apiClassName, false)) {
                    $apiController = new $apiClassName();
                    $apiController->setWiki($this->wiki);
                    if (method_exists($apiController, 'getDocumentation')) {
                        $response = $apiController->getDocumentation();
                    }
                }
            }
            if (empty($response)) {
                $func = 'documentation' . ucfirst(strtolower($extension));
                if (function_exists($func)) {
                    $output .= $func();
                }
            } else {
                $output .= $response;
            }
        }

        $output = $this->wiki->Header() . '<div class="api-container">' . $output . '</div>' . $this->wiki->Footer();

        return new Response($output);
    }

    /**
     * @Route("/api/users/{userId}",methods={"GET"})
     */
    public function getUser($userId)
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOne($userId));
    }

    /**
     * @Route("/api/users/{userId}/delete",methods={"POST"}, options={"acl":{"public","@admins"}})
     */
    public function deleteUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $userController = $this->getService(UserController::class);
        $userManager = $this->getService(UserManager::class);

        $result = [];
        try {
            $csrfTokenController = $this->getService(CsrfTokenController::class);
            $csrfTokenController->checkToken('main', 'POST', 'csrfToken', false);
            $user = $userManager->getOneByName($userId);
            if (empty($user)) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notDeleted' => [$userId],
                    'error' => 'not existing user',
                ];
            } else {
                $userController->delete($user);
                $code = Response::HTTP_OK;
                $result = [
                    'deleted' => [$userId],
                ];
            }
        } catch (TokenNotFoundException $th) {
            $code = Response::HTTP_UNAUTHORIZED;
            $result = [
                'notDeleted' => [$userId],
                'error' => $th->getMessage(),
            ];
        } catch (DeleteUserException $th) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'notDeleted' => [$userId],
                'error' => $th->getMessage(),
            ];
        } catch (Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'notDeleted' => [$userId],
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    /**
     * @Route("/api/users",methods={"POST"}, options={"acl":{"public","@admins"}})
     */
    public function createUser()
    {
        $this->denyAccessUnlessAdmin();
        $userController = $this->getService(UserController::class);

        if (empty($_POST['name'])) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'error' => "\$_POST['name'] should not be empty",
            ];
        } elseif (empty($_POST['email'])) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'error' => "\$_POST['email'] should not be empty",
            ];
        } else {
            try {
                $user = $userController->create([
                    'name' => strval($_POST['name']),
                    'email' => strval($_POST['email']),
                    'password' => $this->wiki->generateRandomString(30)
                ]);
                if (!boolval($this->wiki->config['contact_disable_email_for_password']) && !empty($user)) {
                    $link = $userController->sendPasswordRecoveryEmail($user);
                } else {
                    $link = '';
                }
                $code = Response::HTTP_OK;
                $result = [
                    'created' => [$user['name']],
                    'user' => [
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'signuptime' => $user['signuptime'],
                        'link' => $link
                    ],
                ];
            } catch (UserNameAlreadyUsedException $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [strval($_POST['name'])],
                    'error' => str_replace('{currentName}', strval($_POST['name']), _t('USERSETTINGS_NAME_ALREADY_USED')),
                ];
            } catch (UserEmailAlreadyUsedException $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [strval($_POST['name'])],
                    'error' => str_replace('{email}', strval($_POST['email']), _t('USERSETTINGS_EMAIL_ALREADY_USED')),
                ];
            } catch (ExitException $th) {
                throw $th;
            } catch (Exception $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [strval($_POST['name'])],
                    'error' => $th->getMessage(),
                ];
            } catch (Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result = [
                    'notCreated' => [strval($_POST['name'])],
                    'error' => $th->getMessage(),
                ];
            }
        }

        return new ApiResponse($result, $code);
    }

    /**
     * @Route("/api/users",methods={"GET"}, options={"acl":{"public"}})
     */
    public function getAllUsers($userFields = ['name', 'email', 'signuptime'])
    {
        $this->denyAccessUnlessAdmin();

        $users = $this->getService(UserManager::class)->getAll($userFields);

        // UserManager::getAll gives array of User but user does not have jsonSerialize
        // so extract only what is needed from each User
        $users = array_map(function ($user) use ($userFields) {
            if (!is_array($user)) {
                $user = $user->getArrayCopy();
            }

            return array_filter($user, function ($k) use ($userFields) {
                return in_array($k, $userFields);
            }, ARRAY_FILTER_USE_KEY);
        }, $users);

        return new ApiResponse($users);
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
        $result = $commentService->addCommentIfAuthorized($_POST);

        return new ApiResponse($result, $result['code']);
    }

    /**
     * @Route("/api/comments/{tag}",methods={"POST"}, options={"acl":{"public","+"}})
     */
    public function editComment($tag)
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($_POST, $tag);

        return new ApiResponse($result, $result['code']);
    }

    /**
     * @Route("/api/comments/{tag}",methods={"DELETE"}, options={"acl":{"public","+"}})
     */
    public function deleteComment($tag)
    {
        if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
            $commentService = $this->getService(CommentService::class);
            $errors = $commentService->delete($tag);

            return new ApiResponse(['success' => _t('COMMENT_REMOVED')] + $errors, 200);
        } else {
            return new ApiResponse(['error' => _t('NOT_AUTORIZED_TO_REMOVE_COMMENT')], 403);
        }
    }

    /**
     * @Route("/api/comments/{tag}/delete",methods={"POST"}, options={"acl":{"public","+"}})
     */
    public function deleteCommentViaPostMethod($tag)
    {
        // todo use Anti-Csrf token or Bearer HTTP header
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
        $sql = <<<SQL
            SELECT * FROM {$dbService->prefixTable('pages')}
            WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%"
            AND tag NOT IN (SELECT resource FROM {$dbService->prefixTable('triples')} WHERE property="http://outils-reseaux.org/_vocabulary/type")
            ORDER BY tag ASC
        SQL;
        $pages = _convert($dbService->loadAll($sql), 'ISO-8859-15');
        $pages = array_filter($pages, function ($page) use ($aclService) {
            return $aclService->hasAccess('read', $page['tag']);
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
            $page['html'] = $this->wiki->Format($page['body'], 'wakka', $page['tag']);
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

    /**
     * @Route("/api/pages/{tag}",methods={"DELETE"},options={"acl":{"public","+"}})
     */
    public function deletePage($tag)
    {
        $pageManager = $this->getService(PageManager::class);
        $pageController = $this->getService(PageController::class);
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
                if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
                    if (!$pageManager->isOrphaned($tag)) {
                        $dbService->query("DELETE FROM {$dbService->prefixTable('links')} WHERE to_tag = '$tag'");
                    }
                    $done = $pageController->delete($tag);
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
        } catch (Throwable $th) {
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
            } catch (Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result['error'] = $th->getMessage();
            }
        }

        return new ApiResponse($result, $code);
    }

    /**
     * @Route("/api/pages/{tag}/delete",methods={"POST"},options={"acl":{"public","+"}})
     */
    public function deletePageByGetMethod($tag)
    {
        $result = [];
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        try {
            $csrfTokenController = $this->wiki->services->get(CsrfTokenController::class);
            $csrfTokenController->checkToken('main', 'POST', 'csrfToken', false);
        } catch (TokenNotFoundException $th) {
            $code = Response::HTTP_UNAUTHORIZED;
            $result = [
                'notDeleted' => [$tag],
                'error' => $th->getMessage(),
            ];
        } catch (Throwable $th) {
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
     * @Route("/api/reactions", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getAllReactions()
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', []));
    }

    /**
     * @Route("/api/reactions/{id}", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getReactions($id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id));
    }

    /**
     * @Route("/api/user/{userId}/reactions", options={"acl":{"public"}})
     */
    public function getAllReactionsFromUser($userId)
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', [], $userId));
    }

    /**
     * @Route("/api/user/{userId}/reactions/{id}", options={"acl":{"public"}})
     */
    public function getReactionsFromUser($userId, $id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id, $userId));
    }

    /**
     * @Route("/api/reactions/{idreaction}/{id}/{page}/{username}", methods={"DELETE"}, options={"acl":{"public", "+"}})
     */
    public function deleteReaction($idreaction, $id, $page, $username)
    {
        if ($user = $this->wiki->getUser()) {
            if ($username == $user['name'] || $this->wiki->UserIsAdmin()) {
                $reactionManager = $this->getService(ReactionManager::class);
                if ($reactionManager->deleteUserReaction($page, $idreaction, $id, $username)) {
                    return new ApiResponse(
                        [
                            'idReaction' => $idreaction,
                            'id' => $id,
                            'page' => $page,
                            'user' => $username,
                        ],
                        Response::HTTP_OK
                    );
                } else {
                    return new ApiResponse(
                        ['error' => 'reaction not deleted'],
                        Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                }
            } else {
                return new ApiResponse(
                    ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent supprimer les réactions.'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        } else {
            return new ApiResponse(
                ['error' => 'Vous devez être connecté pour supprimer les réactions.'],
                Response::HTTP_UNAUTHORIZED
            );
        }
    }

    /**
     * @Route("/api/reactions/{idreaction}/{id}/{page}/{username}/delete",methods={"GET"},options={"acl":{"public","+"}})
     */
    public function deleteReactionByGetMethod($idreaction, $id, $page, $username)
    {
        return $this->deleteReaction($idreaction, $id, $page, $username);
    }

    /**
     * @Route("/api/reactions", methods={"POST"}, options={"acl":{"public", "+"}})
     */
    public function addReactionFromUser()
    {
        if ($user = $this->wiki->getUser()) {
            if ($_POST['username'] == $user['name'] || $this->wiki->UserIsAdmin()) {
                if ($_POST['reactionid']) {
                    if ($_POST['pagetag']) { // save the reaction
                        //get reactions from user for this page
                        $userReactions = $this->getService(ReactionManager::class)->getReactions($_POST['pagetag'], [$_POST['reactionid']], $user['name']);
                        $params = $this->getService(ReactionManager::class)->getActionParameters($_POST['pagetag']);
                        if (!empty($params[$_POST['reactionid']])) {
                            // un choix de vote est fait
                            if ($_POST['id']) {
                                // test if limits wherer put
                                if (!empty($params['maxreaction']) && count($userReactions) >= $params['maxreaction']) {
                                    return new ApiResponse(
                                        ['error' => 'Seulement ' . $params['maxreaction'] . ' réaction(s) possible(s). Vous pouvez désélectionner une de vos réactions pour changer.'],
                                        Response::HTTP_UNAUTHORIZED
                                    );
                                } else {
                                    $reactionValues = [
                                        'userName' => $user['name'],
                                        'reactionId' => $_POST['reactionid'],
                                        'id' => $_POST['id'],
                                        'date' => date('Y-m-d H:i:s'),
                                    ];
                                    $this->getService(ReactionManager::class)->addUserReaction(
                                        $_POST['pagetag'],
                                        $reactionValues
                                    );
                                    // hurra, the reaction is saved!
                                    return new ApiResponse(
                                        $reactionValues,
                                        Response::HTTP_OK
                                    );
                                }
                            } else {
                                return new ApiResponse(
                                    ['error' => 'Il faut renseigner une valeur de reaction (id).'],
                                    Response::HTTP_BAD_REQUEST
                                );
                            }
                        }

                        return new ApiResponse(
                            ['error' => "'" . strval($_POST['reactionid']) . "' n'est pas une réaction déclarée sur la page '" . strval($_POST['pagetag']) . "'"],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    } else {
                        return new ApiResponse(
                            ['error' => 'Il faut renseigner une page wiki contenant la réaction.'],
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                } else {
                    return new ApiResponse(
                        ['error' => 'Il faut renseigner un id de la réaction.'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            } else {
                return new ApiResponse(
                    ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent réagir.'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        } else {
            return new ApiResponse(
                json_encode(['error' => 'Vous devez être connecté pour réagir.']),
                Response::HTTP_UNAUTHORIZED
            );
        }
    }

    /**
     * @Route("/api/triples", methods={"GET"}, options={"acl":{"public", "+"}})
     */
    public function ByResource()
    {
        extract($this->extractTriplesParams(INPUT_GET, 'not empty'));
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $value = empty($username) ? null : "%\\\"user\\\":\\\"{$username}\\\"%";
        $triples = $this->getService(TripleStore::class)->getMatching(
            null,
            $property,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $triples,
            Response::HTTP_OK
        );
    }

    /**
     * @Route("/api/triples/{resource}", methods={"GET"}, options={"acl":{"public", "+"}})
     */
    public function getTriplesByResource($resource)
    {
        extract($this->extractTriplesParams(INPUT_GET, $resource));
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        $value = empty($username) ? null : "%\\\"user\\\":\\\"{$username}\\\"%";
        $triples = $this->getService(TripleStore::class)->getMatching(
            $resource,
            $property,
            $value,
            '=',
            '=',
            'LIKE'
        );

        return new ApiResponse(
            $triples,
            Response::HTTP_OK
        );
    }

    /**
     * @Route("/api/triples/{resource}", methods={"POST"}, options={"acl":{"public", "+"}})
     */
    public function setTriple($resource)
    {
        extract($this->extractTriplesParams(INPUT_POST, $resource));
        if (!empty($apiResponse)) {
            return $apiResponse;
        }
        if (empty($property)) {
            return new ApiResponse(
                ['error' => 'Property should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        }
        if (empty($username)) {
            $username = $this->getService(AuthController::class)->getLoggedUser()['name'];
        }
        $value = $_POST['value'] ?? [];
        if (is_array($value)) {
            $rawValue = array_filter($value, function ($elem) {
                return is_scalar($elem);
            });
        } elseif (is_scalar($value)) {
            $rawValue = [
                'value' => $value,
            ];
        } else {
            $rawValue = [];
        }
        $rawValue['user'] = $username;
        $rawValue['date'] = date('Y-m-d H:i:s');
        $value = json_encode($rawValue);
        $result = $this->getService(TripleStore::class)->create(
            $resource,
            $property,
            $value,
            '',
            ''
        );

        return new ApiResponse(
            ['result' => $result],
            in_array($result, [0, 3]) ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @Route("/api/triples/{resource}/delete", methods={"POST"}, options={"acl":{"public", "+"}})
     */
    public function deleteTriples($resource)
    {
        extract($this->extractTriplesParams(INPUT_POST, $resource));
        if (!empty($apiResponse)) {
            return $apiResponse;
        }

        if (empty($property)) {
            return new ApiResponse(
                ['error' => 'Property should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        }
        $rawFilters = $_POST['filters'] ?? [];
        if (is_array($rawFilters)) {
            $rawFilters = array_filter($rawFilters, function ($elem) {
                return is_scalar($elem);
            });
        } else {
            $rawFilters = [];
        }
        if (!empty($username)) {
            $rawFilters['user'] = $username;
        }

        $triples = [];
        if (!empty($rawFilters)) {
            foreach ($rawFilters as $key => $rawValue) {
                $value = empty($rawValue) ? null : "%\\\"{$key}\\\":\\\"{$rawValue}\\\"%";
                $newTriples = $this->getService(TripleStore::class)->getMatching(
                    $resource,
                    $property,
                    $value,
                    '=',
                    '=',
                    'LIKE'
                );
                if (!empty($newTriples)) {
                    $newTriples = array_filter($newTriples, function ($triple) use ($triples) {
                        $sameTriples = array_filter($triples, function ($registeredTriple) use ($triple) {
                            return $registeredTriple['resource'] == $triple['resource'] &&
                                $registeredTriple['property'] == $triple['property'] &&
                                $registeredTriple['value'] == $triple['value'];
                        });

                        return empty($sameTriples);
                    });
                    foreach ($newTriples as $triple) {
                        $triples[] = $triple;
                    }
                }
            }
        }

        $allOk = true;
        $notDeletedTriples = [];
        foreach ($triples as $triple) {
            if ($this->getService(TripleStore::class)->delete(
                $triple['resource'],
                $triple['property'],
                $triple['value'],
                '',
                ''
            ) === false) {
                $allOk = false;
                $notDeletedTriples[] = $triple;
            }
        }
        if ($allOk) {
            return new ApiResponse(
                $triples,
                Response::HTTP_OK
            );
        } else {
            return new ApiResponse(
                [
                    'triples' => $triples,
                    'notDeletedTriples' => $notDeletedTriples,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function extractTriplesParams(string $method, $resource): array
    {
        $property = null;
        $username = null;
        $apiResponse = null;
        if (empty($resource)) {
            $apiResponse = new ApiResponse(
                ['error' => 'Resource should not be empty !'],
                Response::HTTP_BAD_REQUEST
            );
        } else {
            $property = $this->getService(SecurityController::class)->filterInput($method, 'property', FILTER_DEFAULT, true);
            if (empty($property)) {
                $property = null;
            }
            $username = $this->getService(SecurityController::class)->filterInput($method, 'user', FILTER_DEFAULT, true);
            if (empty($username)) {
                if (!$this->wiki->UserIsAdmin()) {
                    $username = $this->getService(AuthController::class)->getLoggedUser()['name'];
                } else {
                    $username = null;
                }
            }
            $currentUser = $this->getService(AuthController::class)->getLoggedUser();
            if (!$this->wiki->UserIsAdmin() && $currentUser['name'] != $username) {
                $apiResponse = new ApiResponse(
                    ['error' => 'Not authorized to access a triple of another user if not admin !'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        return compact(['property', 'username', 'apiResponse']);
    }

    /**
     * @Route("/api/archives/{id}", methods={"GET"}, options={"acl":{"public", "@admins"}})
     */
    public function getArchive($id)
    {
        return $this->getService(ArchiveController::class)->getArchive($id);
    }

    /**
     * @Route("/api/archives/uidstatus/{uid}", methods={"GET"}, options={"acl":{"public", "@admins"}})
     */
    public function getArchiveStatus($uid)
    {
        return $this->getService(ArchiveController::class)->getArchiveStatus(
            $uid,
            empty($_GET['forceStarted']) ? false : in_array($_GET['forceStarted'], [1, true, '1', 'true'], true)
        );
    }

    /**
     * @Route("/api/archives/archivingStatus/", methods={"GET"}, options={"acl":{"public", "@admins"}})
     */
    public function getArchivingStatus()
    {
        return new ApiResponse(
            $this->getService(ArchiveService::class)->getArchivingStatus(),
            Response::HTTP_OK
        );
    }

    /**
     * @Route("/api/archives/forcedUpdateToken/", methods={"GET"}, options={"acl":{"public", "@admins"}})
     */
    public function getForcedUpdateToken()
    {
        $token = $this->getService(ArchiveService::class)->getForcedUpdateToken();

        return new ApiResponse(
            ['token' => $token],
            empty($token) ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK
        );
    }

    /**
     * @Route("/api/archives/", methods={"GET"}, options={"acl":{"public", "@admins"}})
     * @Route("/api/archives", methods={"GET"}, options={"acl":{"public", "@admins"}})
     */
    public function getArchives()
    {
        $archiveService = $this->getService(ArchiveService::class);

        return new ApiResponse(
            $archiveService->getArchives(),
            Response::HTTP_OK
        );
    }

    /**
     * @Route("/api/archives/{id}", methods={"POST"}, options={"acl":{"public", "@admins"}})
     */
    public function archiveAction($id)
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction($id);
    }

    /**
     * @Route("/api/archives", methods={"POST"}, options={"acl":{"public", "@admins"}})
     */
    public function archivesAction()
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction();
    }
}

<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Attach;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\InvalidGroupNameException;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Field\BazarField;
use YesWiki\Core\Field\TextareaField;
use YesWiki\Core\Service\AccountActivationService;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\BazarListService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\CSVManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DiffService;
use YesWiki\Core\Service\DuplicationManager;
use YesWiki\Core\Service\EntryExtraFieldsService;
use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FileManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\HashCashService;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Core\Service\SemanticTransformer;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Core\Service\AuthenticationService;
use YesWiki\Core\Service\CsrfTokenChecker;
use YesWiki\Core\Service\GeoJSONFormatter;
use YesWiki\Core\Service\GroupOperationsService;
use YesWiki\Core\Service\IcalFormatter;
use YesWiki\Core\Service\PageOperationsService;
use YesWiki\Core\Service\UserOperationsService;

class ApiController extends YesWikiController
{
    #[Route('/api', options: ['acl' => ['public']])]
    public function getDocumentation()
    {
        $output = '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/users');
        $output .= '<h2>' . _t('USERS') . '</h2>' . "\n" .
            '<h4>' . _t('LIST') . ' ' . _t('USERS') . '</h4>' . "\n" .
            '<p><code>GET ' . $urlUser . '</code></p>' . "\n" .
            '<h4>' . _t('GET') . ' ' . _t('USER') . '</h4>' . "\n" .
            '<p><code>GET ' . $urlUser . '/{userId}</code></p>' . "\n" . '<h4>' . _t('CREATE') . ' ' . _t('USER') . '</h4>' . "\n" .
            '<p><code>POST ' . $urlUser . '</code></p>' . "\n" .
            '<p><code> name=…&email=…</code></p>' . "\n" .
            '<h4>' . _t('DELETE') . ' ' . _t('USER') . '</h4>' . "\n" .
            '<p><code>POST ' . $urlUser . '/{userId}/delete</code></p>' . "\n";

        $urlGroup = $this->wiki->Href('', 'api/groups');
        $output .= '<h2>' . _t('GROUPS') . '</h2>' . "\n" .
            '<h4>' . _t('LIST') . ' ' . _t('GROUPS') . '</h4>' . "\n" .
            '<p><code>GET ' . $urlGroup . '</code></p>' . "\n" .
            '<h4>' . _t('GET') . ' ' . _t('GROUP') . '</h4>' . "\n" .
            '<p><code>GET ' . $urlGroup . '/{group_name}</code></p>' . "\n" . '<h4>' . _t('CREATE') . ' ' . _t('GROUP') . '</h4>' . "\n" .
            '<p><code>POST ' . $urlGroup . '</code></p>' . "\n" .
            '<p><code> name=…&users[0]=…&users[1]</code></p>' . "\n" .
            '<h4>' . _t('DELETE') . ' ' . _t('GROUP') . '</h4>' . "\n" .
            '<p><code>POST ' . $urlGroup . '/{group_name}/delete</code></p>' . "\n" .
            '<h4>' . _t('UPDATE') . ' ' . _t('GROUP') . '</h4>' . "\n" .
            '<p><code>POST ' . $urlGroup . '/{group_name}/update</code></p>' . "\n" .
            '<p><code> users[0]=…&users[1]</code></p>' . "\n";

        $urlTags = $this->wiki->Href('', 'api/tags');
        $output .= '<h2>' . _t('TAGS_TAGS') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlTags . '?search=…&page=…&perpage=…</code><br>Search tags (paginated, empty search returns everything)</p>';

        $urlPages = $this->wiki->Href('', 'api/pages');
        $output .= '<h2>' . _t('PAGES') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlPages . '</code><br>Get all pages</p>';
        $urlPages = $this->wiki->Href('', 'api/pages/{pageTag}');
        $output .= '<p><code>GET ' . $urlPages . '</code><br>Get indicated page\'s informations, with raw and html contents</p>';

        $urlPages = $this->wiki->Href('', 'api/pages/{pageTag}/comments');
        $output .= '<p><code>GET ' . $urlPages . '</code><br>Get indicated page\'s comments</p>';

        $urlPages = $this->wiki->Href('', 'api/pages/{pageTag}');
        $output .= '<p><code>POST ' . $urlPages . '</code><br>Save body content into indicated page (requires write access), params: body=…</p>';

        $urlPages = $this->wiki->Href('', 'api/pages/{pageTag}/duplicate');
        $output .= '<p><code>POST ' . $urlPages . '</code><br>Duplicate an external page into this YesWiki pageTag</p>';

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

        $urlCustomPresets = $this->wiki->Href('', 'api/templates/custom-presets/{presetFilename}');
        $output .= '<h2>' . _t('TEMPLATES') . '</h2>' . "\n" .
            '<p><code>POST ' . $urlCustomPresets . '</code><br>' . _t('TEMPLATE_ADD_CSS_PRESET_API_HINT') . '.</p>' .
            '<p><code>DELETE ' . $urlCustomPresets . '</code><br>' . _t('TEMPLATE_DELETE_CSS_PRESET_API_HINT') . '.</p>';

        $urlRelations = $this->wiki->Href('', 'api/relations/{type}');
        $output .= '<h2>' . _t('QRCODE_EXTENSION') . '</h2>' . "\n" .
            '<p><code>GET ' . $urlRelations . '</code><br>' . _t('QRCODE_DOC_GET_RELATIONS') . '.</p>' .
            '<p><code>POST ' . $this->wiki->Href('', 'api/relations') . '</code><br>' . _t('QRCODE_DOC_POST_RELATIONS') . '.</p>';

        $output .= '<h2>' . _t('ATTACH_EXTENSION') . '</h2>' . "\n" .
            '<p><code>POST ' . $this->wiki->Href('', 'api/files') . '</code><br>' . _t('ATTACH_DOC_POST_FILES') . '.</p>' .
            '<p><code>GET ' . $this->wiki->Href('', 'api/files/{tag}/download') . '</code><br>' . _t('ATTACH_DOC_GET_DOWNLOAD') . '.</p>' .
            '<p><code>GET ' . $this->wiki->Href('', 'api/files') . '</code><br>' . _t('ATTACH_DOC_GET_FILES') . '.</p>' .
            '<p><b>' .
            "<code>GET {$this->wiki->href('', 'api/images/{filename}/cache/{width}/{height}/{mode}', ['csrftoken' => 'xxxx'], false)}</code></b><br />" .
            nl2br(_t('ATTACH_GET_URLIMAGE_CACHE_API_HELP')) . '</p>';

        $output .= $this->getBazarDocumentation();

        // TODO use annotations to document the API endpoints
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

    #[Route('/api/users/{userId}', methods: ['GET'])]
    public function getUser($userId)
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOneByName($userId));
    }

    #[Route('/api/users/{userId}/delete', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function deleteUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $userOperationsService = $this->getService(UserOperationsService::class);
        $userManager = $this->getService(UserManager::class);

        $result = [];
        try {
            $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);
            $csrfTokenChecker->checkToken('main', 'POST', 'csrfToken', false);
            $user = $userManager->getOneByName($userId);
            if (empty($user)) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notDeleted' => [$userId],
                    'error' => 'not existing user',
                ];
            } else {
                $userOperationsService->delete($user);
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
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'notDeleted' => [$userId],
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/users', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function createUser()
    {
        $this->denyAccessUnlessAdmin();
        $userOperationsService = $this->getService(UserOperationsService::class);
        $userManager = $this->getService(UserManager::class);

        $post = $this->getRequest()->request;
        $postName = strval($post->get('name', ''));
        $postEmail = strval($post->get('email', ''));
        if (empty($postName)) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'error' => "\$_POST['name'] should not be empty",
            ];
        } elseif (empty($postEmail)) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'error' => "\$_POST['email'] should not be empty",
            ];
        } else {
            try {
                $user = $userOperationsService->create([
                    'name' => $postName,
                    'email' => $postEmail,
                    'password' => $this->wiki->generateRandomString(30),
                ]);
                $link = $userManager->sendPasswordRecoveryEmail($user);
                $code = Response::HTTP_OK;
                $result = [
                    'created' => [$user['name']],
                    'user' => [
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'signuptime' => $user['signuptime'],
                        'link' => $link,
                    ],
                ];
            } catch (UserNameAlreadyUsedException $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [$postName],
                    'error' => str_replace('{currentName}', $postName, _t('USERSETTINGS_NAME_ALREADY_USED')),
                ];
            } catch (UserEmailAlreadyUsedException $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [$postName],
                    'error' => str_replace('{email}', $postEmail, _t('USERSETTINGS_EMAIL_ALREADY_USED')),
                ];
            } catch (ExitException $th) {
                throw $th;
            } catch (\Exception $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [$postName],
                    'error' => $th->getMessage(),
                ];
            } catch (\Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result = [
                    'notCreated' => [$postName],
                    'error' => $th->getMessage(),
                ];
            }
        }

        return new ApiResponse($result, $code);
    }

    /**
     * Attempt to login a user (ticket 08, relocated from tools/login's own ApiController).
     *
     * @return string json
     */
    #[Route('/api/login', methods: ['POST'], options: ['acl' => ['public']])]
    public function login()
    {
        $post = $this->getRequest()->request;
        $userManager = $this->getService(UserManager::class);

        // Try login by user name
        $user = $userManager->getOneByName($post->get('username'));

        // Try login by email
        if (!$user && filter_var($post->get('username'), FILTER_VALIDATE_EMAIL)) {
            $user = $userManager->getOneByEmail($post->get('username'));
        }

        if (!$user) {
            return new ApiResponse(['error' => _t('LOGIN_WRONG_USER')], Response::HTTP_UNAUTHORIZED);
        }

        $authenticationService = $this->getService(AuthenticationService::class);
        if (!$authenticationService->checkPassword($post->get('password'), $user)) {
            return new ApiResponse(['error' => _t('LOGIN_WRONG_PASSWORD')], Response::HTTP_UNAUTHORIZED);
        }

        $authenticationService->login($user);

        return new ApiResponse([
            'user' => $user->getName(),
            'isAdmin' => $this->wiki->UserIsAdmin(),
        ]);
    }

    /**
     * Return basic information if the current user is authenticated.
     *
     * @return string json
     */
    #[Route('/api/auth/me', options: ['acl' => ['public']])]
    public function getMyAuth()
    {
        $loggedUser = $this->getService(AuthenticationService::class)->getLoggedUser();
        if (!$loggedUser) {
            return new ApiResponse(['error' => _t('LOGIN_NO_CONNECTED_USER')], Response::HTTP_UNAUTHORIZED);
        }

        return new ApiResponse([
            'user' => $loggedUser['name'],
            'isAdmin' => $this->wiki->UserIsAdmin(),
        ]);
    }

    #[Route('/api/users', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllUsers($userFields = ['name', 'email', 'signuptime'])
    {
        $this->denyAccessUnlessAdmin();

        $users = $this->getService(UserManager::class)->getAll($userFields);
        $accountActivationService = $this->getService(AccountActivationService::class);

        // UserManager::getAll gives array of User but user does not have jsonSerialize
        // so extract only what is needed from each User
        $users = array_map(function ($user) use ($userFields, $accountActivationService) {
            if (!is_array($user)) {
                $user = $user->getArrayCopy();
            }

            $filtered = array_filter($user, function ($k) use ($userFields) {
                return in_array($k, $userFields);
            }, ARRAY_FILTER_USE_KEY);

            // isAdmin/activatedStatus (accountactivationbyemail, absorbed into core, ticket
            // 07) are read through AccountActivationService's own internal fetch, not from
            // $user itself -- activation_status is Field-ACL-hidden on the generic body a
            // page-read would otherwise return
            $filtered['isAdmin'] = $this->wiki->UserIsAdmin($user['name']);
            $filtered['activatedStatus'] = $accountActivationService->isActivated($user['name']);

            return $filtered;
        }, $users);

        return new ApiResponse($users);
    }

    #[Route('/api/emailactivation/{userId}/activate', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function activateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->activate($userId, '', true);

        return new ApiResponse(null);
    }

    #[Route('/api/emailactivation/{userId}/inactivate', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function inactivateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->inactivate($userId);

        return new ApiResponse(null);
    }

    #[Route('api/groups/{group_name}/delete', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function deleteGroup(string $group_name)
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);
        try {
            $groupOperationsService->delete($group_name);
            $code = Response::HTTP_OK;
            $result = [
                'deleted' => [$group_name],
            ];
        } catch (GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_NOT_FOUND;
            $result = [
                'name' => [$group_name],
                'error' => str_replace('{currentName}', $group_name, _t('USERSETTINGS_NAME_NOT_FOUND')),
            ];
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'name' => [$group_name],
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups', methods: ['POST'], options: ['acl' => ['public']])]
    public function createGroup()
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        $post = $this->getRequest()->request;
        $postName = $post->get('name', '');
        if (empty($postName)) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'name' => '',
                'error' => $postName . 'should not be empty',
            ];
        } else {
            try {
                $group_name = $postName;
                $users = $post->has('users') ? $post->all('users') : [];
                $result = $groupOperationsService->create($group_name, $users);
                $code = Response::HTTP_OK;
            } catch (GroupNameAlreadyUsedException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => str_replace('{currentName}', $group_name, _t('GROUP_NAME_ALREADY_USED')),
                ];
            } catch (InvalidGroupNameException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            } catch (UserNameDoesNotExistException|GroupNameDoesNotExistException $th) {
                $code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $result = [
                    'name' => $group_name,
                    'error' => str_replace('{currentName}', $th->getMessage(), _t('USERSETTINGS_NAME_NOT_FOUND')),
                ];
            } catch (ExitException $th) {
                throw $th;
            } catch (\Exception $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            } catch (\Throwable $th) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                $result = [
                    'name' => $group_name,
                    'error' => $th->getMessage(),
                ];
            }
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups/{group_name}/update', methods: ['POST'], options: ['acl' => ['public']])]
    public function updateGroup(string $group_name)
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        $post = $this->getRequest()->request;
        try {
            $users = $post->has('users') ? $post->all('users') : [];
            $result = $groupOperationsService->update($group_name, $users);
            $code = Response::HTTP_OK;
        } catch (InvalidGroupNameException $th) {
            $code = Response::HTTP_UNPROCESSABLE_ENTITY;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        } catch (UserNameDoesNotExistException|GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_UNPROCESSABLE_ENTITY;
            $result = [
                'name' => $group_name,
                'error' => str_replace('{currentName}', $th->getMessage(), _t('USERSETTINGS_NAME_NOT_FOUND')),
            ];
        } catch (ExitException $th) {
            throw $th;
        } catch (\Exception $th) {
            $code = Response::HTTP_BAD_REQUEST;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        } catch (\Throwable $th) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/groups', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllGroups()
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        return new ApiResponse($groupOperationsService->getAll());
    }

    #[Route('/api/groups/{group_name}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getGroup(string $group_name)
    {
        $this->denyAccessUnlessAdmin();
        $groupOperationsService = $this->getService(GroupOperationsService::class);

        try {
            $result = $groupOperationsService->getMembers($group_name);
            $code = Response::HTTP_OK;
        } catch (GroupNameDoesNotExistException $th) {
            $code = Response::HTTP_NOT_FOUND;
            $result = [
                'name' => $group_name,
                'error' => $th->getMessage(),
            ];
        }

        return new ApiResponse($result, $code);
    }

    #[Route('/api/comments/{tag}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllComments($tag = '')
    {
        return new ApiResponse([$this->getService(CommentService::class)->loadComments($tag)]);
    }

    #[Route('/api/comments', methods: ['POST'], options: ['acl' => ['+']])]
    public function postComment()
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all());

        return new ApiResponse($result, $result['code']);
    }

    #[Route('/api/comments/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function editComment($tag)
    {
        $commentService = $this->getService(CommentService::class);
        $result = $commentService->addCommentIfAuthorized($this->getRequest()->request->all(), $tag);

        return new ApiResponse($result, $result['code']);
    }

    #[Route('/api/comments/{tag}', methods: ['DELETE'], options: ['acl' => ['+']])]
    public function deleteComment($tag)
    {
        if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
            $commentService = $this->getService(CommentService::class);
            $errors = $commentService->delete($tag);

            return new ApiResponse(['success' => _t('COMMENT_REMOVED')] + $errors, 200);
        }

        return new ApiResponse(['error' => _t('NOT_AUTORIZED_TO_REMOVE_COMMENT')], 403);
    }

    #[Route('/api/comments/{tag}/delete', methods: ['POST'], options: ['acl' => ['+']])]
    public function deleteCommentViaPostMethod($tag)
    {
        // todo use Anti-Csrf token or Bearer HTTP header
        return $this->deleteComment($tag);
    }

    #[Route('/api/tags', methods: ['GET'], options: ['acl' => ['public']])]
    public function getTags(Request $request)
    {
        $perpage = max(1, min((int)$request->query->get('perpage', 20), 100));
        $page = max(1, (int)$request->query->get('page', 1));

        $result = $this->getService(TagsManager::class)->search(
            (string)$request->query->get('search', ''),
            $perpage,
            ($page - 1) * $perpage
        );

        return new ApiResponse([
            'tags' => $result['tags'],
            'total' => $result['total'],
            'page' => $page,
            'perpage' => $perpage,
        ]);
    }

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

    #[Route('/api/pages/{tag}', methods: ['POST'], options: ['acl' => ['+']])]
    public function savePage(Request $request, $tag)
    {
        $this->denyAccessUnlessGranted('write', $tag);

        $body = $request->request->get('body');
        if ($body === null) {
            return new ApiResponse(['error' => "'body' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $pageManager = $this->getService(PageManager::class);
        $pageManager->save($tag, $body);

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

    #[Route('/api/pages/{tag}', methods: ['DELETE'], options: ['acl' => ['+']])]
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
                if ($this->wiki->UserIsOwner($tag) || $this->wiki->UserIsAdmin()) {
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
    public function deletePageByGetMethod($tag)
    {
        $result = [];
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        try {
            $csrfTokenChecker = $this->wiki->services->get(CsrfTokenChecker::class);
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

    #[Route('/api/reactions', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllReactions()
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', []));
    }

    #[Route('/api/reactions/{id}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getReactions($id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id));
    }

    #[Route('/api/user/{userId}/reactions', options: ['acl' => ['public']])]
    public function getAllReactionsFromUser($userId)
    {
        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', [], $userId));
    }

    #[Route('/api/user/{userId}/reactions/{id}', options: ['acl' => ['public']])]
    public function getReactionsFromUser($userId, $id)
    {
        $id = array_map('trim', explode(',', $id));

        return new ApiResponse($this->getService(ReactionManager::class)->getReactions('', $id, $userId));
    }

    #[Route('/api/reactions/{idreaction}/{id}/{page}/{username}', methods: ['DELETE'], options: ['acl' => ['+']])]
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
                }

                return new ApiResponse(
                    ['error' => 'reaction not deleted'],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            return new ApiResponse(
                ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent supprimer les réactions.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new ApiResponse(
            ['error' => 'Vous devez être connecté pour supprimer les réactions.'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    #[Route('/api/reactions/{idreaction}/{id}/{page}/{username}/delete', methods: ['GET'], options: ['acl' => ['+']])]
    public function deleteReactionByGetMethod($idreaction, $id, $page, $username)
    {
        return $this->deleteReaction($idreaction, $id, $page, $username);
    }

    #[Route('/api/reactions', methods: ['POST'], options: ['acl' => ['+']])]
    public function addReactionFromUser()
    {
        $post = $this->getRequest()->request;
        if ($user = $this->wiki->getUser()) {
            if ($post->get('username') == $user['name'] || $this->wiki->UserIsAdmin()) {
                $reactionid = $post->get('reactionid');
                $pagetag = $post->get('pagetag');
                $reactionIdValue = $post->get('id');
                if ($reactionid) {
                    if ($pagetag) { // save the reaction
                        // get reactions from user for this page
                        $userReactions = $this->getService(ReactionManager::class)->getReactions($pagetag, [$reactionid], $user['name']);
                        $params = $this->getService(ReactionManager::class)->getActionParameters($pagetag);
                        if (!empty($params[$reactionid])) {
                            // un choix de vote est fait
                            if ($reactionIdValue) {
                                // test if limits wherer put
                                if (!empty($params['maxreaction']) && count($userReactions) >= $params['maxreaction']) {
                                    return new ApiResponse(
                                        ['error' => 'Seulement ' . $params['maxreaction'] . ' réaction(s) possible(s). Vous pouvez désélectionner une de vos réactions pour changer.'],
                                        Response::HTTP_UNAUTHORIZED
                                    );
                                }
                                $reactionValues = [
                                    'userName' => $user['name'],
                                    'reactionId' => $reactionid,
                                    'id' => $reactionIdValue,
                                    'date' => date('Y-m-d H:i:s'),
                                ];
                                $this->getService(ReactionManager::class)->addUserReaction(
                                    $pagetag,
                                    $reactionValues
                                );

                                // hurra, the reaction is saved!
                                return new ApiResponse(
                                    $reactionValues,
                                    Response::HTTP_OK
                                );
                            }

                            return new ApiResponse(
                                ['error' => 'Il faut renseigner une valeur de reaction (id).'],
                                Response::HTTP_BAD_REQUEST
                            );
                        }

                        return new ApiResponse(
                            ['error' => "'" . strval($reactionid) . "' n'est pas une réaction déclarée sur la page '" . strval($pagetag) . "'"],
                            Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    }

                    return new ApiResponse(
                        ['error' => 'Il faut renseigner une page wiki contenant la réaction.'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                return new ApiResponse(
                    ['error' => 'Il faut renseigner un id de la réaction.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return new ApiResponse(
                ['error' => 'Seul les admins ou l\'utilisateur concerné peuvent réagir.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new ApiResponse(
            json_encode(['error' => 'Vous devez être connecté pour réagir.']),
            Response::HTTP_UNAUTHORIZED
        );
    }

    #[Route('/api/triples', methods: ['GET'], options: ['acl' => ['+']])]
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

    #[Route('/api/triples/{resource}', methods: ['GET'], options: ['acl' => ['+']])]
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

    #[Route('/api/triples/{resource}', methods: ['POST'], options: ['acl' => ['+']])]
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
            $username = $this->getService(AuthenticationService::class)->getLoggedUser()['name'];
        }
        $value = $this->getRequest()->request->get('value', []);
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

    #[Route('/api/triples/{resource}/delete', methods: ['POST'], options: ['acl' => ['+']])]
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
        $rawFilters = $this->getRequest()->request->get('filters', []);
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
                            return $registeredTriple['resource'] == $triple['resource']
                                && $registeredTriple['property'] == $triple['property']
                                && $registeredTriple['value'] == $triple['value'];
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
        }

        return new ApiResponse(
            [
                'triples' => $triples,
                'notDeletedTriples' => $notDeletedTriples,
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
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
            $bag = ($method === INPUT_POST) ? $this->getRequest()->request : $this->getRequest()->query;
            $rawProperty = $bag->get('property');
            $property = (empty($rawProperty) || !is_string($rawProperty)) ? '' : htmlspecialchars(strip_tags($rawProperty));
            if (empty($property)) {
                $property = null;
            }

            $rawUsername = $bag->get('user');
            $username = (empty($rawUsername) || !is_string($rawUsername)) ? '' : htmlspecialchars(strip_tags($rawUsername));
            if (empty($username)) {
                if (!$this->wiki->UserIsAdmin()) {
                    $username = $this->getService(AuthenticationService::class)->getLoggedUser()['name'];
                } else {
                    $username = null;
                }
            }
            $currentUser = $this->getService(AuthenticationService::class)->getLoggedUser();
            if (!$this->wiki->UserIsAdmin() && $currentUser['name'] != $username) {
                $apiResponse = new ApiResponse(
                    ['error' => 'Not authorized to access a triple of another user if not admin !'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        return compact(['property', 'username', 'apiResponse']);
    }

    #[Route('/api/archives/{id}', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchive($id)
    {
        return $this->getService(ArchiveController::class)->getArchive($id);
    }

    #[Route('/api/archives/uidstatus/{uid}', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchiveStatus($uid)
    {
        $forceStarted = $this->getRequest()->query->get('forceStarted');

        return $this->getService(ArchiveController::class)->getArchiveStatus(
            $uid,
            !empty($forceStarted) && in_array($forceStarted, [1, true, '1', 'true'], true)
        );
    }

    #[Route('/api/archives/archivingStatus/', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchivingStatus()
    {
        return new ApiResponse(
            $this->getService(ArchiveService::class)->getArchivingStatus(),
            Response::HTTP_OK
        );
    }

    #[Route('/api/archives/forcedUpdateToken/', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getForcedUpdateToken()
    {
        $token = $this->getService(ArchiveService::class)->getForcedUpdateToken();

        return new ApiResponse(
            ['token' => $token],
            empty($token) ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK
        );
    }

    #[Route('/api/archives/', methods: ['GET'], options: ['acl' => ['@admins']])]
    #[Route('/api/archives', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getArchives()
    {
        $archiveService = $this->getService(ArchiveService::class);

        return new ApiResponse(
            $archiveService->getArchives(),
            Response::HTTP_OK
        );
    }

    #[Route('/api/archives/{id}', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function archiveAction($id)
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction($id);
    }

    #[Route('/api/archives', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function archivesAction()
    {
        return $this->getService(ArchiveController::class)->manageArchiveAction();
    }

    /**
     * Bootstrap script that inserts the hashcash hidden field into a form, then fetches the
     * puzzle from getHashcashKey() below. Replaces the old tools/security/wp-hashcash-js.php,
     * which - being a plain file under tools/ - can't be reached by URL on farm instances
     * (see src/bootstrap_paths.php: only the source tree has a tools/ folder on disk).
     */
    #[Route('/api/hashcash', methods: ['GET'], options: ['acl' => ['public']])]
    public function getHashcashScript(Request $request): Response
    {
        $formId = (string)($request->query->get('formid') ?: 'ACEditor');

        return new Response(
            $this->getService(HashCashService::class)->getEnableScript($formId),
            Response::HTTP_OK,
            ['Content-Type' => 'application/javascript']
        );
    }

    /**
     * The actual hashcash puzzle, fetched by the script above. Replaces the old
     * tools/security/wp-hashcash-getkey.php, same reasoning as getHashcashScript().
     */
    #[Route('/api/hashcash/key', methods: ['GET'], options: ['acl' => ['public']])]
    public function getHashcashKey(): Response
    {
        return new Response(
            $this->getService(HashCashService::class)->getKeyScript(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/javascript',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * @param string $hashb64
     *
     * @throws \Exception if error
     */
    #[Route('/api/captcha/{hashb64}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getCaptcha($hashb64): StreamedResponse
    {
        // clean headers and cache
        if (!headers_sent()) {
            header_remove();
        }
        if (ob_get_level() > 1) {
            ob_end_clean();
        }
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
            'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
            'Access-Control-Allow-Methods' => 'GET',
            'Cache-Control' => 'no-store, no-cache, must-revalidate', // HTTP/1.1
            'Content-Type' => 'Content-type: image/png',
        ];
        $hash = base64_decode($hashb64);

        return new StreamedResponse(
            function () use ($hash) {
                // callable only call when sending
                if (ob_get_level() > 1) {
                    ob_end_clean();
                }
                $this->getService(CaptchaController::class)->printImage($hash);
            },
            Response::HTTP_OK,
            $headers
        );
    }

    /**
     * List qrcode relations (ticket 14, formerly yeswiki-extension-qrcode's own ApiController) --
     * pairs of Bazar entries linked via {{qrscan}}'s paired QR-code scanning flow.
     */
    #[Route('/api/relations/{type}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllRelations(string $type = 'contact')
    {
        $entryCache = [];
        $options = [
            'formsIds' => $this->wiki->config['qrcode_config']['relation_form_id'],
        ];
        $query = $this->getService(EntryController::class)
                ->formatQuery(empty($type) ? [] : ['query' => ['bf_relation' => $type]], $_GET);
        if (!empty($query)) {
            $options['queries'] = $query;
        }
        $entries = $this->getService(EntryManager::class)->search($options, true, true);
        foreach ($entries as $k => $e) {
            $entryCache[$e['bf_fiche1']] = isset($entryCache[$e['bf_fiche1']]) ?
                $entryCache[$e['bf_fiche1']] :
                $this->getService(EntryManager::class)->getOne($e['bf_fiche1']);
            $entryCache[$e['bf_fiche2']] = isset($entryCache[$e['bf_fiche2']]) ?
                $entryCache[$e['bf_fiche2']] :
                $this->getService(EntryManager::class)->getOne($e['bf_fiche2']);
            $entries[$k]['entry1'] = $entryCache[$e['bf_fiche1']];
            $entries[$k]['entry2'] = $entryCache[$e['bf_fiche2']];
        }

        return new ApiResponse(empty($entries) ? null : $entries);
    }

    /**
     * Create a qrcode relation entry linking two scanned Bazar entries (ticket 14).
     */
    #[Route('/api/relations', methods: ['POST'], options: ['acl' => ['public']])]
    public function createRelation()
    {
        $_POST['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create(
            $this->wiki->config['qrcode_config']['relation_form_id'],
            $_POST,
            false,
            $_SERVER['HTTP_SOURCE_URL'] ?? null
        );
        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->wiki->Href('', $entry['tag'])],
            Response::HTTP_CREATED
        );
    }

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
        $authorizedExtensions = $this->wiki->config['authorized-extensions'] ?? [];
        if (!empty($authorizedExtensions) && !array_key_exists($ext, $authorizedExtensions)) {
            return new ApiResponse(['error' => _t('ERROR_NOT_AUTHORIZED_EXTENSION')], Response::HTTP_BAD_REQUEST);
        }

        $maxFileSize = $this->wiki->config['attach_config']['max_file_size']
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

    /**
     * Consolidated contact-mail-sending route (ticket 18, replaces the ajax branch of
     * tools/contact's handlers/page/mail.php page-handler). Mail is now sent through
     * Mailer::send() instead of a direct send_mail() call. No CSRF token or spam/
     * honeypot protection is added here -- the contact form had neither before this
     * ticket, and both are explicitly deferred to a later, dedicated security pass.
     *
     * Handles the same three request shapes the old handler multiplexed onto one
     * route, distinguished by which POST fields are present: a plain contact/
     * abonnement/desabonnement form (mail=), a per-field "contact via this entry
     * field" link (field=, reading the entry's own value for that field), and
     * "send this wiki page by email" (type=mail). $pageTag is new: the old handler
     * read its ACL/page-body context implicitly from the page it was dispatched on;
     * an API route has no such implicit context, so the caller passes it explicitly.
     */
    #[Route('/api/contact/mail', methods: ['POST'], options: ['acl' => ['public']])]
    public function sendContactMail(Request $request)
    {
        include_once YESWIKI_SOURCE_DIR . '/src/contact.functions.php';

        $pageTag = (string)$request->request->get('pageTag', '');
        if (empty($pageTag)) {
            return new ApiResponse(['type' => 'danger', 'message' => "'pageTag' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $aclService = $this->getService(AclService::class);
        $pageManager = $this->getService(PageManager::class);
        $entryManager = $this->getService(EntryManager::class);

        $field = (string)$request->request->get('field', '');
        $infomsg = '';
        $hasReadAccess = true;

        if (!empty($field)) {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            $mailReceiver = [];
            if ($hasReadAccess) {
                $val = $entryManager->getOne($pageTag);
                if (is_array($val) && isset($val[$field])) {
                    $mailReceiver[] = $val[$field];
                }
                $form = baz_valeurs_formulaire($val['form_id'] ?? null);
                $mailSenderForMsg = (string)$request->request->get('email', '');
                $infomsg .= '<em>' . _t('CONTACT_THIS_MESSAGE') . ' « <a href="' . $this->wiki->href('', $val['tag']) . '">'
                    . $val['bf_titre'] . '</a> » ' . _t('CONTACT_FROM_FORM') . ' « ' . $form['label'] . ' » '
                    . _t('CONTACT_FROM_WEBSITE') . ' « ' . $this->wiki->config['yeswiki_name'] . ' ». ' .
                    ($mailSenderForMsg ? _t('CONTACT_REPLY') . ' <strong>' . $mailSenderForMsg . '</strong> '
                        . _t('CONTACT_REPLY2') : '') . '.</em><br><br>';
            }
        } else {
            $mailReceiver = trim((string)$request->request->get('mail', '')) ?: false;
        }

        $page = $pageManager->getOne($pageTag, null, true, true);

        if (!$mailReceiver) {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            if ($hasReadAccess) {
                // le squelette du theme pourrait contenir des actions avec des mails
                $themeManager = $this->getService(ThemeManager::class);
                $chemin = 'themes/' . $themeManager->getFavoriteTheme() . '/squelettes/' . $themeManager->getFavoriteSquelette();
                $fileContent = file_exists($chemin) ? file_get_contents($chemin) : '{WIKINI_PAGE}';
                $body = str_replace('{WIKINI_PAGE}', $page['body'] ?? '', $fileContent);
                $nbActionMail = $request->request->get('nbactionmail');
                $mailReceiver = !empty($nbActionMail) ? FindMailFromWikiPage($body, $nbActionMail) : false;
                if ($mailReceiver) {
                    $mailList = array_map('trim', explode(',', $mailReceiver));
                    $mailReceiver = parseMails($mailList);
                }
            }
        }

        $mailSender = trim((string)$request->request->get('email', '')) ?: false;
        $nameSender = (string)$request->request->get('name', '') ?: false;
        $type = (string)$request->request->get('type', '');

        if ($type == 'mail') {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            if ($hasReadAccess) {
                $subject = (string)$request->request->get('subject', '') ?: false;
                if ($entryManager->isEntry($pageTag)) {
                    $renderedPage = $this->getService(EntryController::class)->view($pageTag);
                } else {
                    $renderedPage = $this->wiki->Format($page['body'] ?? '', 'wakka', $pageTag);
                }
                $messageHtml = html_entity_decode($renderedPage);
                $messageTxt = strip_tags($messageHtml);
            }
        } elseif ($type == 'abonnement' || $type == 'desabonnement') {
            $messageHtml = $messageTxt = 'Mailinglist : ' . $type;
        } else {
            $entete = (string)$request->request->get('entete', '');
            $subject = (!empty($entete) ? '[' . trim($entete) . '] ' : '') . (string)$request->request->get('subject', '');
            $rawMessage = (string)$request->request->get('message', '');
            $messageTxt = trim(strip_tags($rawMessage));
            $messageHtml = trim(nl2br(str_replace('€', '&euro;', htmlspecialchars($rawMessage, ENT_COMPAT, YW_CHARSET))));
        }

        if ($hasReadAccess) {
            $message = check_parameters_mail($type, $mailSender, $nameSender, $mailReceiver ?? '', $subject ?? '', $messageTxt ?? '');
            if ($type != 'abonnement' && $type != 'desabonnement' && !empty($infomsg)) {
                $messageTxt = strip_tags($infomsg) . '\n\n' . ($messageTxt ?? '');
                $messageHtml = $infomsg . ($messageHtml ?? '');
            }
        } else {
            $message = [
                'class' => 'danger',
                'message' => _t('CONTACT_MESSAGE_NOT_SENT') . ' :<br />' . _t('LOGIN_NOT_AUTORIZED'),
            ];
        }

        if ($message['class'] == 'success') {
            $mailingList = (string)$request->request->get('mailinglist', '');
            if (!empty($mailingList)) {
                $mailReceiver = array_pop($mailReceiver); // for the lists, only one mail receiver possible
                if ($mailingList == 'ezmlm') {
                    $mailReceiver = str_replace('@', '-' . str_replace('@', '=', $mailSender) . '@', $mailReceiver);
                } elseif ($mailingList == 'sympa') {
                    $tabmail = explode('@', $mailReceiver);
                    $listname = $tabmail[0];
                    $listdomain = $tabmail[1];
                    $mailReceiver = 'sympa@' . $listdomain;
                    if ($type == 'abonnement') {
                        $subject = 'subscribe ' . $listname;
                    } elseif ($type == 'desabonnement') {
                        $subject = 'unsubscribe ' . $listname;
                    }
                }
                if (empty($messageTxt)) {
                    $messageTxt = $messageHtml = 'dummy message';
                }
            }
            if ($this->getService(Mailer::class)->send($mailSender, $nameSender, $mailReceiver, $subject, $messageTxt, $messageHtml)) {
                if (empty($type) || $type == 'contact' || $type == 'mail') {
                    $message['message'] = _t('CONTACT_MESSAGE_SUCCESSFULLY_SENT');
                } elseif ($type == 'abonnement') {
                    $message['message'] = _t('CONTACT_SUBSCRIBE_ORDER_SENT');
                } elseif ($type == 'desabonnement') {
                    $message['message'] = _t('CONTACT_UNSUBSCRIBE_ORDER_SENT');
                }
            } else {
                $message['class'] = 'danger';
                $message['message'] = _t('CONTACT_MESSAGE_NOT_SENT');
            }
        }

        return new ApiResponse(['type' => $message['class'], 'message' => $message['message']], Response::HTTP_OK);
    }

    public const POST_CACHE_URLIMAGE_TOKEN_ID = 'POST api/images/cache/{width}/{height}/{mode}';

    /**
     * Generate/serve a resized cached copy of an image (ticket 17, relocated from
     * tools/attach). $filename is a raw legacy filename (Bazar's own image/file fields
     * upload through the same {pageTag}_{name}_{dates}.{ext} convention tools/attach
     * always used, and were never migrated to a file-entry tag) -- this route used to
     * perform NO ownership check at all beyond `file_exists()`, the same vulnerability
     * class as the download route above. Fixed the same way: recover the owning page
     * tag from the filename's legacy prefix and deny access unless its read ACL grants
     * this requester read.
     */
    #[Route('/api/images/{filename}/cache/{width}/{height}/{mode}', methods: ['POST'], options: ['acl' => ['public']])]
    public function getCacheUrlImageViaPost($filename, $width, $height, $mode)
    {
        try {
            $this->checkParamsGetCacheUrlImageViaPost($filename, $width, $height, $mode);
            $newToken = $this->checkTokenForGetCacheUrlImageViaPost($width, $height, $mode);

            if (!file_exists("files/$filename")) {
                return new ApiResponse([
                    'error' => _t('ATTACH_GET_CACHE_URLIMAGE_NO_FILE'),
                    'filename' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mode' => $mode,
                    'newToken' => $newToken,
                ], Response::HTTP_BAD_REQUEST);
            }

            // fail closed: every legitimate caller's filename follows the legacy
            // {pageTag}_{name}_{dates}.{ext} convention, so if we can't recover an owner
            // page tag from it, there's no ACL we could possibly check -- deny rather
            // than silently serving the image unauthenticated
            $ownerPageTag = $this->getService(FileManager::class)->guessOwnerPageTagFromLegacyFilename($filename);
            if (empty($ownerPageTag)) {
                throw new AccessDeniedHttpException();
            }
            $this->denyAccessUnlessGranted('read', $ownerPageTag);

            try {
                $cachefilename = $this->getCacheFileName($filename, $width, $height, $mode);
            } catch (\Exception $e) {
                return new ApiResponse([
                    'error' => $e->getMessage(),
                    'cachefilename' => '',
                    'filename' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mode' => $mode,
                    'newToken' => $newToken,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new ApiResponse([
                'cachefilename' => $cachefilename,
                'filename' => $filename,
                'width' => $width,
                'height' => $height,
                'mode' => $mode,
                'newToken' => $newToken,
            ], Response::HTTP_OK);
        } catch (TokenNotFoundException $th) {
            return new ApiResponse(['error' => $th->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function checkParamsGetCacheUrlImageViaPost(string $filename, string &$width, string &$height, string $mode)
    {
        if (strval($width) != strval(intval($width))) {
            throw new \Exception('width should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $width = intval($width);
        if (empty($width)) {
            throw new \Exception('width should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (strval($height) != strval(intval($height))) {
            throw new \Exception('height should be an integer for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        $height = intval($height);
        if (empty($height)) {
            throw new \Exception('height should not be 0 or null for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (!in_array($mode, ['fit', 'crop'], true)) {
            throw new \Exception("mode should be in ['fit','mode'] for " . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
        if (empty(trim($filename))) {
            throw new \Exception('filename should not be empty for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }
    }

    /**
     * use $_POST['csrftoken'].
     */
    private function checkTokenForGetCacheUrlImageViaPost(int $width, int $height, string $mode): string
    {
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

        $tokenId = str_replace(
            ['{width}', '{height}', '{mode}'],
            [$width, $height, $mode],
            self::POST_CACHE_URLIMAGE_TOKEN_ID
        );

        if (!$csrfTokenChecker->checkToken($tokenId, 'POST', 'csrftoken', false)) {
            // Falling through here used to return null from a `: string` method, i.e. a
            // TypeError -- an \Error, so neither of the caller's catches (TokenNotFoundException
            // -> 401, \Exception -> 400) caught it and a bad token produced an uncaught 500.
            // It failed closed, but it crashed instead of erroring. Throw the exception the
            // caller already maps to 401, as the other two routes in this controller do.
            throw new TokenNotFoundException('invalid csrftoken for ' . self::POST_CACHE_URLIMAGE_TOKEN_ID);
        }

        $csrfTokenManager->removeToken($tokenId);

        return $csrfTokenManager->getToken($tokenId)->getValue();
    }

    private function getCacheFileName(string $filename, int $width, int $height, string $mode): string
    {
        $attach = new Attach($this->wiki);
        $newFileName = $attach->getResizedFilename("files/$filename", $width, $height, $mode);
        if (file_exists($newFileName)) {
            return $newFileName;
        }
        $attach->redimensionner_image("files/$filename", $newFileName, $width, $height, $mode);

        return $newFileName;
    }

    /**
     * The form arrays carry the ActivityPub keypair (merged from page metadata for
     * internal use); the private key must never leave through the public API.
     */
    private function stripFormSecrets(array $form): array
    {
        unset($form['activitypub_private_key']);

        return $form;
    }

    #[Route('/api/forms', methods: ['GET'], options: ['acl' => ['public']])]
    #[Route('/api/forms/', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllForms()
    {
        $forms = $this->getService(FormManager::class)->getAll();
        $forms = array_map([$this, 'stripFormSecrets'], $forms);

        return new ApiResponse(empty($forms) ? null : $forms);
    }

    /**
     * Live preview backing the form designer's field cards: each field object of the
     * posted template goes through the very path the entry form uses
     * (FormManager::prepareData + BazarField::renderInputIfPermitted), so a card shows
     * the real Twig markup of the input instead of a JS look-alike.
     *
     * Every posted item yields exactly one `previews` entry -- an empty string when the
     * field cannot be built or its rendering throws -- so the designer maps the answer
     * positionally back onto its cards. Fields are prepared one at a time on purpose:
     * prepareData() silently drops typeless entries, which would shift every index after
     * them.
     *
     * `styles` carries the stylesheet links the input templates registered while
     * rendering (date.css, leaflet.css, ...); the designer page has no other way to know
     * about them, and the previews would show up unstyled without them.
     *
     * Declared here rather than on FormController because routed controllers are
     * instantiated by YesWikiControllerResolver with `new $class()`: only a controller
     * with a no-argument constructor can back a route.
     */
    #[Route('/api/forms/preview', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function previewFormTemplate(Request $request)
    {
        $template = json_decode((string)$request->request->get('template', ''), true);
        if (!is_array($template)) {
            return new ApiResponse(['error' => _t('FORM_BUILDER_INVALID_JSON')], 400);
        }

        $cssLength = strlen($GLOBALS['css'] ?? '');
        $previews = [];
        // a field rendering may echo instead of returning (old-style extension fields):
        // keep any stray output out of the JSON body
        ob_start();
        try {
            foreach ($template as $fieldObject) {
                $previews[] = $this->renderFieldPreview($fieldObject);
            }
        } finally {
            ob_end_clean();
        }

        return new ApiResponse([
            'previews' => $previews,
            'styles' => substr($GLOBALS['css'] ?? '', $cssLength),
        ]);
    }

    /** One template field object => its entry-form input HTML, or '' if unrenderable. */
    private function renderFieldPreview($fieldObject): string
    {
        if (!is_array($fieldObject) || empty($fieldObject['type'])) {
            return '';
        }
        try {
            $prepared = $this->getService(FormManager::class)->prepareData(['template' => [$fieldObject]]);
            $field = reset($prepared);

            return $field instanceof BazarField ? (string)$field->renderInputIfPermitted(null) : '';
        } catch (\Throwable $th) {
            return '';
        }
    }

    #[Route('/api/forms/{formId}', methods: ['GET'], options: ['acl' => ['public']])]
    #[Route('/api/forms/{formId}/', methods: ['GET'], options: ['acl' => ['public']])]
    public function getForm($formId)
    {
        if (strpos($formId, 'b64_') === 0) {
            $vFormId = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vForm = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $vFormID])[$vFormID];

        if (!$vForm || !isset($vForm['id'])) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($this->stripFormSecrets($vForm));
    }

    #[Route('/api/forms/{formId}/entries/{output}/{selectedEntries}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllFormEntries($formId, $output = null, $selectedEntries = null)
    {
        if (!is_array($formId) && strpos($formId, 'b64_') === 0) {
            $vFormID = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vSearchManager = $this->getService(SearchManager::class);
        $get = $this->getRequest()->query;

        $vQuery = $get->get('query') ?? $get->get('queries') ?? null;
        $vQuery = $vSearchManager->aggregateQueries(
            !empty($selectedEntries) ? ['queries' => ['tag' => $selectedEntries]] : [],
            isset($vQuery) ? urldecode($vQuery) : ''
        );

        $vKeywords = $vSearchManager->aggregateKeywords($get->get('keywords', ''), $get->get('q', ''));

        $vSearchFields = $get->has('searchfields') ? urldecode($get->get('searchfields')) : null;
        $vCorrespondance = $get->has('correspondance') ? urldecode($get->get('correspondance')) : null;
        $vDateFilter = $get->has('datefilter') ? urldecode($get->get('datefilter')) : null;
        $vOrdre = $get->get('ordre', 'asc');
        $vChamp = $get->get('champ', 'title');
        $vNb = intval($get->get('nbitem') ?? $get->get('nb') ?? null);
        $vMinDate = urldecode($get->get('dateMin') ?? $get->get('minDate') ?? $get->get('period') ?? '');

        if ($output == 'csv') { // Search is done in the CSV Manager
            $csvManager = $this->getService(CSVManager::class);
            $csvManager->sendCsvOrZip($vFormID, [
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'correspondance' => $vCorrespondance,
                'ordre' => $vOrdre,
                'champ' => $vChamp,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);
        } else {
            $vBazarListService = $this->getService(BazarListService::class);

            $entries = $vBazarListService->getEntries([
                'idtypeannonce' => $vFormID,
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'correspondance' => $vCorrespondance,
                'ordre' => $vOrdre,
                'champ' => $vChamp,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);

            $acceptHeader = $this->getRequest()->headers->get('accept', '');
            if ($output == 'json-ld' || strpos($acceptHeader, 'application/ld+json') !== false) {
                return $this->getAllSemanticEntries($formId, $entries);
            } // add entries in html format if asked
            elseif ($output == 'html') {
                foreach ($entries as $id => $entry) {
                    $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', 0);
                }
            } elseif ($output == 'geojson') {
                $entries = $this->getService(GeoJSONFormatter::class)->formatToGeoJSON($entries);
            } elseif ($output == 'ical') {
                return $this->getService(IcalFormatter::class)->apiResponse($entries, $formId, $get->all());
            } elseif ($get->has('fields')) {
                $fields = explode(',', $get->get('fields'));
                $lightEntries = [];
                if (!empty($entries) && !empty($fields)) {
                    foreach ($entries as $id => $entry) {
                        $lightEntry = [];
                        foreach ($fields as $field_name) {
                            if (isset($entry[$field_name])) {
                                $lightEntry[$field_name] = $entry[$field_name];
                            }
                        }
                        if (!empty($lightEntry)) {
                            $lightEntries[$id] = $lightEntry;
                        }
                    }
                }

                return new ApiResponse(empty($lightEntries) ? null : $lightEntries);
            }
        }

        return new ApiResponse(empty($entries) ? null : $entries);
    }

    #[Route('/api/entries/{output}/{selectedEntries}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllEntries($output = null, $selectedEntries = null)
    {
        // fast access for one entry
        $get = $this->getRequest()->query;
        if ($this->isEntryViewFastAccess($output, $selectedEntries, $get->all())) {
            $entryId = explode(',', $selectedEntries)[0];
            if ($this->getService(AclService::class)->hasAccess('read', $entryId)) {
                $html = $this->getService(EntryController::class)->view($entryId, '', 1);
                $isInIframe = $get->get('isInIframe');
                if ($isInIframe && $isInIframe == 'iframe') {
                    $html = replaceLinksWithIframe($html);
                }
            } else {
                $html = $this->render('@core/alert-message.twig', [
                    'type' => 'info',
                    'message' => _t('ERROR_NO_ACCESS'),
                ]);
            }

            return new ApiResponse(empty($html) ? null : [$entryId => ['html_output' => $html]]);
        }

        return $this->getAllFormEntries([], $output, $selectedEntries);
    }

    /**
     * helper to check if EntryView fast access.
     *
     * @param string|null $output
     * @param string|null $selectedEntries
     * @param array|null  $get
     * @param bool
     */
    private function isEntryViewFastAccess($output, $selectedEntries, $get): bool
    {
        return $output == 'html'
            && !empty($selectedEntries) && is_string($selectedEntries) && count(explode(',', $selectedEntries)) == 1
            && !empty($get['fields']) && $get['fields'] == 'html_output';
    }

    /**
     * helper to check if EntryView fast access for Bazar/Service/Guard.
     *
     * @param bool
     */
    public function isEntryViewFastAccessHelper(): bool
    {
        $queryAll = $this->getRequest()->query->all();
        $route = array_key_first($queryAll);
        if (substr($route, strlen('api/entries/html'), 1) == '/') {
            $output = substr($route, strlen('api/entries/'), strlen('html'));
            $selectedEntries = substr($route, strlen('api/entries/html/'));
        } else {
            $output = '';
            $selectedEntries = '';
        }

        return $this->isEntryViewFastAccess($output, $selectedEntries, $queryAll);
    }

    public function getAllSemanticEntries($formId, $entries)
    {
        // Put data inside LDP container
        $form = $this->getService(FormManager::class)->getOne($formId);

        $resources = array_map(function ($entry) use ($form) {
            return $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry, true);
        }, array_values($entries));

        $context = !empty($resources) ? ($resources[0]['@context'] ?? null) : null;
        foreach ($resources as &$resource) {
            unset($resource['@context']);
        }

        return new ApiResponse(
            [
                '@context' => $context,
                '@id' => $this->wiki->Href('fiche/' . $formId, 'api'),
                '@type' => ['ldp:Container', 'ldp:BasicContainer'],
                'dcterms:title' => $form['label'],
                'ldp:contains' => $resources,
            ],
            Response::HTTP_OK,
            ['Content-Type: application/ld+json; charset=UTF-8']
        );
    }

    #[Route('/api/entry/url/{sourceUrl}')]
    public function getEntryUrl($sourceUrl)
    {
        $triples = $this->getService(TripleStore::class)->getMatching(
            null,
            'http://outils-reseaux.org/_vocabulary/sourceUrl',
            urldecode($sourceUrl)
        );
        if (!$triples) {
            throw new NotFoundHttpException();
        }

        $resources = array_map(function ($triple) {
            return $this->wiki->Href('', $triple['resource']);
        }, $triples);

        return new ApiResponse($resources);
    }

    /**
     * Create or update an entry.
     */
    #[Route('/api/entries/{formId}', methods: ['POST'], options: ['acl' => ['+']])]
    public function createEntry($formId)
    {
        $request = $this->getRequest();
        if (strpos($request->headers->get('content-type', ''), 'application/ld+json') !== false) {
            $this->createSemanticEntry($formId);
        }

        $postData = $request->request->all();
        if (empty($postData) && strpos($request->headers->get('content-type', ''), 'application/json') !== false) {
            $jsonData = json_decode($request->getContent(), true);
            if (is_array($jsonData)) {
                $postData = $jsonData;
            }
        }
        $postData['antispam'] = 1;

        if (!isset($postData['tag']) || !$this->getService(EntryManager::class)->isEntry($postData['tag'])) {
            $entry = $this->getService(EntryManager::class)->create($formId, $postData, false, $request->headers->get('source-url'));
        } else {
            $entry = $this->getService(EntryManager::class)->update($postData['tag'], $postData, false, true);
        }

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->wiki->Href('', $entry['tag'])],
            Response::HTTP_CREATED
        );
    }

    #[Route('/api/entries/{formId}/json-ld', methods: ['POST'], options: ['acl' => ['+']])]
    public function createSemanticEntry($formId)
    {
        $postData = $this->getRequest()->request->all();
        $postData['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create($formId, $postData, true, $this->getRequest()->headers->get('source-url'));

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new Response('', Response::HTTP_CREATED, [
            'Link: <http://www.w3.org/ns/ldp#Resource>; rel="type"',
            'Location: ' . $this->wiki->Href('', $entry['tag']),
        ]);
    }

    #[Route('/api/entries/bazarlist', methods: ['GET'], options: ['acl' => ['public']], priority: 2)]
    public function getBazarListData()
    {
        $vBazarListService = $this->getService(BazarListService::class);

        /* ------------------------------------ */
        /*             Format Params */
        /* ------------------------------------ */

        $queryAll = $this->getRequest()->query->all();
        $formattedGet = array_map(function ($value) {
            return ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }, $queryAll);

        $get = $this->getRequest()->query;
        $searchfields = $get->get('searchfields');
        $searchfields = is_string($searchfields) ? explode(',', urldecode($searchfields)) : $searchfields;
        $searchfields = $searchfields == null ? [] : $searchfields;

        $vKeywords = $get->has('keywords') ? urldecode($get->get('keywords')) : '';

        $formattedGet['keywords'] = $vKeywords;
        $formattedGet['searchfields'] = $searchfields;
        $formattedGet['idtypeannonce'] = $get->get('idtypeannonce') ?? $get->get('id') ?? null;

        /* ------------------------------------ */
        /*               Get Data */
        /* ------------------------------------ */
        // All forms
        $refreshVal = $get->get('refresh');
        $forms = $vBazarListService->getForms($formattedGet + ['refresh' => isset($refreshVal) ? in_array($refreshVal, [1, true, '1', 'true'], true) : false]);

        // Entries
        $entries = $vBazarListService->getEntries($formattedGet, $forms);

        // Filters
        $filters = $vBazarListService->getFilters($formattedGet, $entries, $forms);

        /* ------------------------------------ */
        /*            Transform Data */
        /* ------------------------------------ */

        // Associated Forms
        $formIds = array_unique(array_map(function ($entry) {
            return $entry['form_id'];
        }, $entries));
        $usedForms = array_filter($forms, function ($form) use ($formIds) {
            return in_array($form['id'], $formIds);
        });
        $usedForms = array_map(function ($f) {
            return $f['prepared'];
        }, $usedForms);

        // Basic fields
        $fieldList = ['tag', 'bf_titre', 'url', '-is-external-', 'external-data'];
        // If no id, we need idtypeannonce (== formId) to filter
        if (!$get->has('id')) {
            $fieldList[] = 'form_id';
        }
        // fields for color / icon
        $colorfield = $get->get('colorfield');
        $fieldList = array_merge($fieldList, $colorfield ? [$colorfield] : []);
        $iconfield = $get->get('iconfield');
        $fieldList = array_merge($fieldList, $iconfield ? [$iconfield] : []);
        // Fields used to search
        $fieldList = array_merge($fieldList, $searchfields);
        // Fields used to sort
        $fieldList = array_merge($fieldList, $get->has('sortfields') ? $get->all('sortfields') : []);
        // Fields used by template
        $fieldList = array_merge($fieldList, $get->has('displayfields') ? $get->all('displayfields') : []);
        // extra fields required by template
        $fieldList = array_merge($fieldList, $get->has('necessary_fields') ? $get->all('necessary_fields') : []);
        $fieldList = array_merge($fieldList, $get->has('necessaryfields') ? $get->all('necessaryfields') : []);
        // Fields for filters
        foreach ($filters as $filter) {
            $fieldList[] = $filter['propName'];
        }

        // filter blank values, remove duplicates, array_values to have incremental keys
        $fieldList = array_values(array_unique(array_filter($fieldList)));

        // Reduce the size of the data sent by transforming entries object into array
        // we use the $fieldMapping to transform back the data when receiving data in the front end
        $entryFieldsService = $this->getService(EntryExtraFieldsService::class);

        $entries = array_map(function ($entry) use ($fieldList, $entryFieldsService) {
            $entryFieldsService->setEntryId($entry['tag']);
            $result = [];
            foreach ($fieldList as $fieldName) {
                // when the field is a TextareaField with the SYNTAX_WIKI syntax, transform the field value into HTML
                $field = $this->getService(FormManager::class)->findFieldFromNameOrPropertyName($fieldName, $entry['form_id']);
                if ($field && $field->getType() == 'textelong' && $field->getSyntax() == TextareaField::SYNTAX_WIKI) {
                    $entry[$fieldName] = $this->wiki->Format($entry[$fieldName]);
                }
                // handle specific fields like comments, reactions
                if (!isset($entry[$fieldName]) || (is_string($entry[$fieldName]) && trim($entry[$fieldName]) == '')) {
                    $entry[$fieldName] = $entryFieldsService->get($fieldName);
                }
                $result[] = $entry[$fieldName] ?? null;
            }

            return $result;
        }, $entries);

        return new ApiResponse(
            [
                'entries' => $entries,
                'fieldMapping' => $fieldList,
                'filters' => $filters,
                'forms' => $usedForms,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Display Bazar API documentation, folded into getDocumentation()'s output
     * (relocated from tools/bazar/controllers/ApiController.php, ticket 24).
     */
    private function getBazarDocumentation(): string
    {
        $output = '<h2>Bazar</h2>' . "\n";

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms') . '</code></b><br />
        Retourne la liste de tous les formulaires Bazar.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}') . '</code></b><br />
        Retourne les informations sur le formulaire <code>formId</code>.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Si le header <code>Accept</code> est <code>application/json</code>, retourne la fiche au format JSON.<br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, retourne la fiche au format JSON-LD.<br />
        </p>';

        $output .= '
        <p>
        <b><code>PUT ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Si le header <code>Content-Type</code> est <code>application/json</code>, modifie la fiche selon le JSON fourni.<br />
        Si le header <code>Content-Type</code> est <code>application/ld+json</code>, modifie la fiche selon le JSON-LD fourni.<br />
        </p>';

        $output .= '
        <p>
        <b><code>DELETE ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Supprime la fiche Bazar.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries') . '</code></b><br />
        Obtenir la liste des fiches de tous les formulaires Bazar.<br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, le JSON retourné sera au format sémantique (container LDP)
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code><br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, le JSON retourné sera au format sémantique (container LDP)
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/json-ld') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format sémantique (container LDP)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/html') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format json, avec la représentation html de la fiche dans le champ <code>html_output</code><br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/geojson') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format geojson<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/ical') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format ical<br />
        Il est possible de filtrer sur les dates en ajoutant à l\'url <code>&datefilter=>-6M</code> (exemple pour les dates plus récentes que 6 mois)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries&fields=bf_titre') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> en ne gardant que les titres (il est possible de spécifier d\autres champs en séparant leur nom par des \',\')<br />
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}') . '</code></b><br />
        Créer une nouvelle fiche en utilisant le formulaire <code>formId</code><br />
        Si le header <code>Content-Type</code> est <code>application/ld+json</code>, un JSON sémantique est attendu.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/html') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format json, avec la représentation html de la fiche dans le champ <code>html_output</code><br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/bazarlist') . '</code></b><br />
        Obtenir les données nécessaires à bazarliste dynamic au format json<br />
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}/json-ld') . '</code></b><br />
        Créer une nouvelle fiche de type <code>formId</code> au format sémantique<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/geojson') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format geojson<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/ical') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format ical<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/{output}&fields=bf_titre') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format spécifié en ne gardant que les titres (il est possible de spécifier d\'autres champs en séparant leur nom par des \',\' ex: <code>&field=bf_titre,url</code>)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entry/url/{sourceUrl}') . '</code></b><br />
        Retourne l\'URL de la page Wiki synchronisée avec <code>sourceUrl</code><br />
        </p>';

        return $output;
    }
}

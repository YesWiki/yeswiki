<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Exception\UserEmailAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameReservedException;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\StringUtilService;

class UserApiController extends YesWikiController
{
    #[Route('/api/users/{userId}', methods: ['GET'])]
    public function getUser(string $userId): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOneByName($userId));
    }

    #[Route('/api/users/{userId}/delete', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function deleteUser(string $userId): ApiResponse
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
    public function createUser(): ApiResponse
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
                    'password' => StringUtilService::generateRandomString(30),
                ]);
                if ($user === null) {
                    throw new \RuntimeException('user creation failed');
                }
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
            } catch (UserNameReservedException $th) {
                $code = Response::HTTP_BAD_REQUEST;
                $result = [
                    'notCreated' => [$postName],
                    'error' => $th->getMessage(),
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

    /** @param string[] $userFields the user properties to expose */
    #[Route('/api/users', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllUsers(array $userFields = ['name', 'email', 'signuptime']): ApiResponse
    {
        $this->denyAccessUnlessAdmin();

        $users = $this->getService(UserManager::class)->getAll($userFields);
        $accountActivationService = $this->getService(AccountActivationService::class);

        $users = array_map(function ($user) use ($userFields, $accountActivationService) {
            $user = $user->getArrayCopy();

            $filtered = array_filter($user, function ($k) use ($userFields) {
                return in_array($k, $userFields);
            }, ARRAY_FILTER_USE_KEY);

            $filtered['isAdmin'] = $this->getService(AclService::class)->isAdmin($user['name']);
            $filtered['activatedStatus'] = $accountActivationService->isActivated($user['name']);

            return $filtered;
        }, $users);

        return new ApiResponse($users);
    }
}

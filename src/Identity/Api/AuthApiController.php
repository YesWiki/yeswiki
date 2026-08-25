<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;

class AuthApiController extends YesWikiController
{
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

        $username = (string)$post->get('username', '');
        $user = $userManager->getOneByName($username);

        if (!$user && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user = $userManager->getOneByEmail($username);
        }

        $authenticationService = $this->getService(AuthenticationService::class);

        if (!$user) {
            $authenticationService->recordFailedLogin($username, 'unknown-user');

            return new ApiResponse(['error' => _t('LOGIN_WRONG_USER')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$authenticationService->checkPassword(strval($post->get('password')), $user)) {
            $obsolete = $authenticationService->requiresPasswordReset($user);
            $authenticationService->recordFailedLogin(
                (string)$user['name'],
                $obsolete ? 'password-format-obsolete' : 'wrong-password'
            );

            return new ApiResponse(
                ['error' => _t($obsolete ? 'LOGIN_PASSWORD_FORMAT_OBSOLETE' : 'LOGIN_WRONG_PASSWORD')],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $authenticationService->login($user);

        return new ApiResponse([
            'user' => $user->getName(),
            'isAdmin' => $this->getService(AclService::class)->isAdmin(),
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
            'isAdmin' => $this->getService(AclService::class)->isAdmin(),
        ]);
    }
}

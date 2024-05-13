<?php

namespace YesWiki\Login\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\UserManager;

class ApiController extends YesWikiController
{
    /**
     * Attempt to login user
     *
     * @return string json
     *
     * @Route("/api/login",methods={"POST"}, options={"acl":{"public"}})
     */
    public function login()
    {
        if (filter_var($_POST['username'], FILTER_VALIDATE_EMAIL)) {
            $user = $this->wiki->services->get(UserManager::class)->getOneByEmail($_POST['username']);
        } else {
            $user = $this->wiki->services->get(UserManager::class)->getOneByName($_POST['username']);
        }
        if (!$user) {
            return new ApiResponse(['error' => _t('LOGIN_WRONG_USER')], Response::HTTP_UNAUTHORIZED);
        }
        $isRightPassword = $this->wiki->services->get(AuthController::class)->checkPassword($_POST['password'], $user);
        if (!$isRightPassword) {
            return new ApiResponse(['error' => _t('LOGIN_WRONG_PASSWORD')], Response::HTTP_UNAUTHORIZED);
        } else {
            $this->wiki->services->get(AuthController::class)->login($user);
            return new ApiResponse([
                'user' => $user->getName(),
                'isAdmin' => $this->wiki->UserIsAdmin()
            ]);
        }
    }

    /**
     * Return basic information if user is authenticated
     *
     * @return string json
     *
     * @Route("/api/auth/me", options={"acl":{"public"}})
     */
    public function getMyAuth()
    {
        $loggedUser = $this->wiki->services->get(AuthController::class)->getLoggedUser();
        if (!$loggedUser) {
            return new ApiResponse(['error' => _t('LOGIN_NO_CONNECTED_USER')], Response::HTTP_UNAUTHORIZED);
        } else {
            return new ApiResponse([
                'user' => $loggedUser['name'],
                'isAdmin' => $this->wiki->UserIsAdmin()
            ]);
        }
    }

    /**
     * Get all users or one user's information
     *
     * @param string $username specify username
     *
     * @return string json
     *
     * @Route("/api/auth/{username}",options={"acl":{"public"}})
     */
    public function getAuth($username = '')
    {
        $this->denyAccessUnlessAdmin();
        $wiki = $this->wiki;
        if (!empty($username[0])) {
            if ($wiki->UserIsAdmin() || $wiki->services->get(AuthController::class)->getLoggedUserName() == $username[0]) {
                $user = $wiki->services->get(UserManager::class)->getOneByName($username[0]);
                if ($user) {
                    $response = $user;
                } else {
                    $response = ['error' => ['User ' . $username[0] . ' not found.']];
                }
            } else {
                $response = ['error' => ['Unauthorized']];
            }
        } else {
            $users = $wiki->services->get(UserManager::class)->getOneByName($username[0]);
            $response = $users;
        }

        return new ApiResponse($response);
    }

    /**
     * @Route("/api/auth/",options={"acl":{"public"}})
     */
    public function getAuthAll()
    {
        $this->denyAccessUnlessAdmin();

        return $this->getAuth();
    }

    /**
     * Display Auth api documentation.
     *
     * @return string
     */
    public function getDocumentation()
    {
        $urlAuth = $this->wiki->href('', 'api/auth');
        $output = '<h2>Extension Login</h2>' . "\n" .
            '<p><code>GET ' . $urlAuth . '</code> Get all users (admin only)</p>' .
            '<p><code>GET ' . $urlAuth . '/{user}</code> Get indicated user (admin only)</p>' .
            '<p><code>GET ' . $urlAuth . '/me</code> Get basic info (username, isAdmin) for connected user (needs authenticated user)</p>' .
            '<p><code>POST ' . $urlAuth . '/login</code> login user with param user and password</p>' .
            '<p><code>POST ' . $urlAuth . '/logout</code> logout current connected user</p>';
        return $output;
    }
}

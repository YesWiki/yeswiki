<?php

namespace YesWiki\Login\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * Get all users or one user's information
     *
     * @param string $username specify username
     *
     * @return string json
     */

    /**
     * @Route("/api/auth/{username}")
     */
    public function getAuth($username = '')
    {
        $this->denyAccessUnlessAdmin();
        $wiki = $this->wiki;
        if (!empty($username[0])) {
            if ($wiki->UserIsAdmin() or $wiki->GetUserName() == $username[0]) {
                $user = $wiki->LoadUser($username[0]);
                if ($user) {
                    $response = $user;
                } else {
                    $response = array('error' => array('User '.$username[0].' not found.'));
                }
            } else {
                $response = array('error' => array('Unauthorized'));
            }
        } else {
            $users = $wiki->LoadUsers();
            $response = $users;
        }
        return new ApiResponse($response);
    }

    /**
     * @Route("/api/auth/")
     */
    public function getAuthAll()
    {
        $this->denyAccessUnlessAdmin();
        return $this->getAuth();
    }
}

<?php

namespace YesWiki\Login\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * Get all users or one user's information.
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
                    $response = ['error' => ['User ' . $username[0] . ' not found.']];
                }
            } else {
                $response = ['error' => ['Unauthorized']];
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

    /**
     * Display Auth api documentation.
     *
     * @return string
     */
    public function getDocumentation()
    {
        $urlAuth = $this->wiki->href('', 'api/auth');
        $output = '<h2>Extension login</h2>' . "\n" .
        '<p><code>GET ' . $urlAuth . '</code></p>';

        return $output;
    }
}

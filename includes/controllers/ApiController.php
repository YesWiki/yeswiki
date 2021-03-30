<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api")
     * @RouteACL({"public"})
     */
    public function getDocumentation()
    {
        $output = $this->wiki->Header();

        $output .= '<h1>YesWiki API</h1>';

        $urlUser = $this->wiki->Href('', 'api/user');
        $output .= '<h2>'._t('USERS').'</h2>'."\n".
            'GET <code><a href="'.$urlUser.'">'.$urlUser.'</a></code><br />';

        $urlGroup = $this->wiki->Href('', 'api/group');
        $output .= '<h2>'._t('GROUPS').'</h2>'."\n".
            'GET <code><a href="'.$urlGroup.'">'.$urlGroup.'</a></code><br />';

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

        $output .= $this->wiki->Footer();

        return new Response($output);
    }

    /**
     * @Route("/api/user/{userId}")
     */
    public function getUser($userId)
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getOne($userId));
    }

    /**
     * @Route("/api/user")
     */
    public function getAllUsers()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(UserManager::class)->getAll());
    }

    /**
     * @Route("/api/group")
     */
    public function getAllGroups()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->wiki->GetGroupsList());
    }
}

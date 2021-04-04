<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouteCollection;

class ApiService
{
    protected $params;
    protected $aclService;

    public function __construct(ParameterBagInterface $params, AclService $aclService)
    {
        $this->aclService = $aclService;
        $this->params = $params;
    }

    public function isAuthorized(array $requestParams, RouteCollection $routes)
    {
        $acl = $this->loadACL($requestParams, $routes);
        $publicMode = in_array("public", $acl);
        // remove public
        $acl = array_diff($acl, ["public"]);
        // check ACL if not empty after removing public
        if (!empty(implode(' ', $acl)) && !$this->aclService->check(implode(' ', $acl))) {
            // acl defined but not allowed
            return false;
        }
        return(
            $publicMode ||
            (
                $this->params->has('api_allowed_keys') &&
                (
                    (
                        isset($this->params->get('api_allowed_keys')['public']) &&
                        $this->params->get('api_allowed_keys')['public'] === true
                    ) ||
                    in_array($this->getBearerToken(), $this->params->get('api_allowed_keys'))
                )
            )
        );
    }

    /**
     * Get header Authorization
     * */
    private function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
    * get access token from header
    * */
    private function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function loadACL(array $requestParams = [], ?RouteCollection $routes = null): array
    {
        $routeName = $requestParams['_route'] ?? null;
        if (empty($routeName) ||
            empty($requestParams['_controller']) ||
            empty($routes->all()[$routeName])) {
            return [];
        }
        $route =  $routes->all()[$routeName] ;
        return $route->hasOption('acl') ? $route->getOption('acl') : [];
    }
}

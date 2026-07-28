<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouteCollection;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Wiki;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;

class ApiService
{
    protected $authenticationService;
    protected $params;
    protected $aclService;
    protected $userManager;
    protected $wiki;

    public function __construct(AuthenticationService $authenticationService, ParameterBagInterface $params, AclService $aclService, UserManager $userManager, Wiki $wiki)
    {
        $this->authenticationService = $authenticationService;
        $this->aclService = $aclService;
        $this->params = $params;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    public function isAuthorized(array $requestParams, RouteCollection $routes)
    {
        $bearerToken = $this->getBearerToken();
        // connect user from api_allowed_keys (format 'userName' => 'key')
        // to be admin, the userName should exist and be in @admins group
        $bearerIsConnected = $this->connectBearer($bearerToken);

        // acl
        $acl = $this->loadACL($requestParams, $routes);
        $publicMode = in_array('public', $acl);
        // a route restricted to one or several groups (e.g. "@admins") must always be
        // checked against the connected user's real membership
        $requiresGroup = !empty(array_filter($acl, fn ($entry) => is_string($entry) && strpos($entry, '@') === 0));
        // remove public
        $acl = array_diff($acl, ['public']);
        // check ACL if not empty after removing public
        $hasAcl = !empty(implode(' ', $acl)) && $this->aclService->check(implode("\n", $acl));

        if ($requiresGroup) {
            return $publicMode || $hasAcl;
        }

        return
            $publicMode
            || $hasAcl
            || (
                $this->params->has('api_allowed_keys')
                && (
                    $bearerIsConnected
                    || (
                        isset($this->params->get('api_allowed_keys')['public'])
                        && $this->params->get('api_allowed_keys')['public'] === true
                    )
                )
            )
        ;
    }

    /**
     * Get header Authorization.
     * */
    private function getAuthorizationHeader()
    {
        $headers = $this->wiki->request->headers->get('authorization')
            ? trim($this->wiki->request->headers->get('authorization'))
            : null;

        return $headers;
    }

    /**
     * get access token from header.
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
        if (empty($routeName)
            || empty($requestParams['_controller'])
            || empty($routes->all()[$routeName])) {
            return [];
        }
        $route = $routes->all()[$routeName];

        return $route->hasOption('acl') ? $route->getOption('acl') : [];
    }

    /**
     * connect user from bearer token.
     */
    private function connectBearer(?string $bearerToken = null): bool
    {
        if (empty($bearerToken) || !$this->params->has('api_allowed_keys')) {
            return false;
        }

        $apiAllowedKeys = $this->params->get('api_allowed_keys');
        if (!is_array($apiAllowedKeys)) {
            return false;
        }
        $userName = array_search($bearerToken, $apiAllowedKeys);
        if (!empty($userName)) {
            // get user from key
            $user = $this->userManager->getOneByName($userName);
        }

        if (empty($user)) {
            return false;
        }
        // login
        $this->authenticationService->logout();
        $this->authenticationService->login($user);

        return true;
    }
}

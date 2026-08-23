<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouteCollection;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;

class ApiService
{
    protected AuthenticationService $authenticationService;
    protected ParameterBagInterface $params;
    protected AclService $aclService;
    protected UserManager $userManager;

    protected CurrentRequest $currentRequest;

    public function __construct(AuthenticationService $authenticationService, ParameterBagInterface $params, AclService $aclService, UserManager $userManager, CurrentRequest $currentRequest)
    {
        $this->currentRequest = $currentRequest;
        $this->authenticationService = $authenticationService;
        $this->aclService = $aclService;
        $this->params = $params;
        $this->userManager = $userManager;
    }

    /** @param array<string, mixed> $requestParams the matched route's parameters */
    public function isAuthorized(array $requestParams, RouteCollection $routes): bool
    {
        $bearerToken = $this->getBearerToken();

        $bearerIsConnected = $this->connectBearer($bearerToken);

        $acl = $this->loadACL($requestParams, $routes);
        $publicMode = in_array('public', $acl);

        $requiresGroup = !empty(array_filter($acl, fn ($entry) => is_string($entry) && strpos($entry, '@') === 0));

        $acl = array_diff($acl, ['public']);

        $hasAcl = !empty(implode(' ', $acl)) && $this->aclService->check(implode("\n", $acl));

        if ($requiresGroup) {
            return $publicMode || $hasAcl;
        }

        if (!$this->params->has('api_allowed_keys')) {
            return $publicMode || $hasAcl;
        }
        $allowedKeys = $this->params->get('api_allowed_keys');
        $anyKeyIsPublic = is_array($allowedKeys) && ($allowedKeys['public'] ?? null) === true;

        return $publicMode || $hasAcl || $bearerIsConnected || $anyKeyIsPublic;
    }

    /** Get header Authorization. */
    private function getAuthorizationHeader(): ?string
    {
        $header = $this->currentRequest->get()->headers->get('authorization');

        return empty($header) ? null : trim($header);
    }

    /** get access token from header. */
    private function getBearerToken(): ?string
    {
        $headers = $this->getAuthorizationHeader();

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $requestParams the matched route's parameters
     *
     * @return array<mixed> what the route declares under the `acl` option, empty when it declares none
     */
    private function loadACL(array $requestParams, RouteCollection $routes): array
    {
        $routeName = $requestParams['_route'] ?? null;
        if (empty($routeName)
            || empty($requestParams['_controller'])
            || empty($routes->all()[$routeName])) {
            return [];
        }
        $route = $routes->all()[$routeName];
        $acl = $route->hasOption('acl') ? $route->getOption('acl') : [];

        return is_array($acl) ? $acl : [];
    }

    /** connect user from bearer token. */
    private function connectBearer(?string $bearerToken = null): bool
    {
        if (empty($bearerToken) || !$this->params->has('api_allowed_keys')) {
            return false;
        }

        $apiAllowedKeys = $this->params->get('api_allowed_keys');
        if (!is_array($apiAllowedKeys)) {
            return false;
        }
        $user = null;
        $userName = array_search($bearerToken, $apiAllowedKeys);
        if (is_string($userName) && $userName !== '') {
            $user = $this->userManager->getOneByName($userName);
        }

        if (empty($user)) {
            return false;
        }

        $this->authenticationService->logout();
        $this->authenticationService->login($user);

        return true;
    }
}

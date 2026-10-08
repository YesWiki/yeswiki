<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ApiService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/YesWiki.php';
require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for GHSA (api public mode bypassing @group-restricted routes' ACL,
 * e.g. admin-only config mutation / archive management endpoints).
 */
#[CoversMethod(ApiService::class, 'isAuthorized')]
class ApiServiceTest extends TestCase
{
    private const REQUEST_PARAMS = ['_route' => 'test_route', '_controller' => 'x::y'];

    private function routesFor(array $acl): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('test_route', new Route('/api/test', [], [], ['acl' => $acl]));

        return $routes;
    }

    private function buildService(
        bool $apiAllowedKeysPublic,
        bool $aclCheckReturns,
        ?string $bearerToken = null,
        ?string $bearerUserName = null
    ): ApiService {
        $authController = $this->createMock(AuthController::class);
        $aclService = $this->createStub(AclService::class);
        $aclService->method('check')->willReturn($aclCheckReturns);

        if (!empty($bearerUserName)) {
            $user = $this->createStub(User::class);
            $userManager = $this->createMock(UserManager::class);
            $userManager->expects($this->once())->method('getOneByName')->with($bearerUserName)->willReturn($user);
            $authController->expects($this->once())->method('login')->with($user);
        } else {
            $userManager = $this->createStub(UserManager::class);
            $authController->expects($this->never())->method('login');
        }

        $apiAllowedKeys = ['public' => $apiAllowedKeysPublic];
        if (!empty($bearerUserName)) {
            $apiAllowedKeys[$bearerUserName] = $bearerToken;
        }
        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('has')->willReturnCallback(fn ($key) => $key === 'api_allowed_keys');
        $params->method('get')->willReturnCallback(
            fn ($key) => $key === 'api_allowed_keys' ? $apiAllowedKeys : null
        );

        $wiki = (new \ReflectionClass(Wiki::class))->newInstanceWithoutConstructor();
        $wiki->request = empty($bearerToken)
            ? Request::create('/')
            : Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $bearerToken]);

        return new ApiService($authController, $params, $aclService, $userManager, $wiki);
    }

    public function testGroupRestrictedRouteIsNotBypassedByPublicApiMode()
    {
        $service = $this->buildService(true, false);
        $this->assertFalse($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testGroupRestrictedRouteIsGrantedWhenAclActuallySatisfied()
    {
        $service = $this->buildService(true, true);
        $this->assertTrue($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testGroupRestrictedRouteIsNotGrantedToNonAdminBearerToken()
    {
        $service = $this->buildService(false, false, 'sometoken', 'someuser');
        $this->assertFalse($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testNonGroupRouteIsStillOpenedByPublicApiMode()
    {
        $service = $this->buildService(true, false);
        $this->assertTrue($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['+'])));
    }

    public function testNonGroupRouteIsClosedWithoutApiMode()
    {
        $service = $this->buildService(false, false);
        $this->assertFalse($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['+'])));
    }

    public function testPublicAclRouteIsAlwaysOpen()
    {
        $service = $this->buildService(false, false);
        $this->assertTrue($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['public'])));
    }
}

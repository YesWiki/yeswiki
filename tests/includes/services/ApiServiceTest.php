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

        $userManager = $this->createMock(UserManager::class);
        if (!empty($bearerUserName)) {
            $user = $this->createStub(User::class);
            $userManager->method('getOneByName')->with($bearerUserName)->willReturn($user);
            $authController->expects($this->once())->method('login')->with($user);
        } else {
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

        // Wiki declares a legacy Method() function, which collides case-insensitively with
        // PHPUnit's own mock-builder method() and makes it undoublable ; build a real,
        // constructor-free instance instead and set only the property ApiService reads.
        $wiki = (new \ReflectionClass(Wiki::class))->newInstanceWithoutConstructor();
        $wiki->request = empty($bearerToken)
            ? Request::create('/')
            : Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $bearerToken]);

        return new ApiService($authController, $params, $aclService, $userManager, $wiki);
    }

    public function testGroupRestrictedRouteIsNotBypassedByPublicApiMode()
    {
        // GHSA: 'api_allowed_keys' => ['public' => true] must not grant access to a
        // route restricted to a group (e.g. options={"acl":{"@admins"}}) for an
        // anonymous caller with no token at all.
        $service = $this->buildService(true, false);
        $this->assertFalse($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testGroupRestrictedRouteIsGrantedWhenAclActuallySatisfied()
    {
        // a genuinely connected admin (aclService->check() true) must still be granted access
        $service = $this->buildService(true, true);
        $this->assertTrue($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testGroupRestrictedRouteIsNotGrantedToNonAdminBearerToken()
    {
        // a *valid* api_allowed_keys bearer token for a non-admin user must not, on its
        // own, satisfy a group-restricted route (aclService->check() correctly returns false)
        $service = $this->buildService(false, false, 'sometoken', 'someuser');
        $this->assertFalse($service->isAuthorized(self::REQUEST_PARAMS, $this->routesFor(['@admins'])));
    }

    public function testNonGroupRouteIsStillOpenedByPublicApiMode()
    {
        // ordinary content routes (acl "+", "public", or none) must keep working as
        // documented in docs/en/dev.md "Public API scenario"
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

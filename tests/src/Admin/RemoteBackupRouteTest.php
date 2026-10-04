<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Api\RemoteBackupApiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Fetching another wiki's backup is for administrators, and an action on it needs the page's token. */
class RemoteBackupRouteTest extends YesWikiTestCase
{
    public function testBothRoutesAreForAdministrators(): void
    {
        $methods = [];
        foreach ($this->getWiki()->services->get(RouteProvider::class)->get() as $route) {
            if ($route->getPath() === '/api/remotebackup') {
                $this->assertContains('@admins', (array)$route->getOption('acl'));
                $methods = array_merge($methods, $route->getMethods());
            }
        }
        sort($methods);

        $this->assertSame(['GET', 'POST'], $methods);
    }

    public function testAnActionWithoutTheTokenIsRefused(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $acl = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $acl->isAdmin($user['name'])
        ));
        if ($admin === false) {
            $this->markTestSkipped('an administrator is needed to reach the route');
        }
        $authentication = $wiki->services->get(AuthenticationService::class);
        $authentication->login($admin);

        try {
            $wiki->services->get(CurrentRequest::class)->replace(Request::create('/?api/remotebackup', 'POST', ['action' => 'cancel']));
            $response = $wiki->services->get(RemoteBackupApiController::class)->remoteBackupAction();

            $this->assertSame(403, $response->getStatusCode());
        } finally {
            $authentication->logout();
        }
    }
}

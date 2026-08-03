<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Admin\Api\DocumentationApiController;
use YesWiki\Admin\Controller\AdminController;
use YesWiki\Admin\Controller\DashboardController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Administration as routes rather than as seeded wiki pages.
 *
 * Two things are worth a test here and neither is the markup. First, that every `/admin/*`
 * route declares `@admins`: the gate is the route's own option, checked by ApiService
 * before the controller runs, so a route added later without it would be silently public.
 * Second, that each screen actually renders -- these controllers are thin wrappers around
 * actions, and the way they break is an action name that no longer exists, which shows up
 * as an empty page rather than as an error.
 */
class DashboardRoutesTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testEveryAdminRouteRequiresAdmins(YesWikiRuntime $wiki): void
    {
        $adminRoutes = [];
        foreach ($wiki->services->get(RouteProvider::class)->get() as $route) {
            if (str_starts_with($route->getPath(), '/admin')) {
                $adminRoutes[$route->getPath()] = (array)$route->getOption('acl');
            }
        }

        $this->assertNotEmpty($adminRoutes, 'the admin routes must be discoverable');
        foreach ($adminRoutes as $path => $acl) {
            $this->assertContains('@admins', $acl, "{$path} must be admins-only");
        }
    }

    #[Depends('testWikiExisting')]
    public function testDashboardRoutesArePublicAndReserveTheirTags(YesWikiRuntime $wiki): void
    {
        $public = [];
        foreach ($wiki->services->get(RouteProvider::class)->get() as $route) {
            if (str_starts_with($route->getPath(), '/dashboard')) {
                $public[$route->getPath()] = (array)$route->getOption('acl');
            }
        }

        $this->assertNotEmpty($public);
        foreach ($public as $path => $acl) {
            $this->assertContains('public', $acl, "{$path} is meant to be readable by anyone");
        }

        // a page tagged `dashboard` or `admin` would have no URL at all, so both names are
        // reserved -- ReservedTagsTest checks the list against the route table itself
        $this->assertTrue(ReservedTags::isReserved('dashboard'));
        $this->assertTrue(ReservedTags::isReserved('Admin'), 'reserving is case-insensitive');
    }

    #[Depends('testWikiExisting')]
    public function testEveryScreenRenders(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $authentication = $wiki->services->get(AuthenticationService::class);
        $acl = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($u) => $acl->isAdmin($u['name'])
        ));
        $this->assertNotFalse($admin, 'the admin screens need an admin to render for');
        $authentication->login($admin);

        try {
            $dashboard = $wiki->services->get(DashboardController::class);
            foreach ([
                'activity' => 'DASHBOARD_ACTIVITY',
                'forms' => 'DASHBOARD_FORMS',
                'export' => 'DASHBOARD_EXPORT',
            ] as $method => $key) {
                $body = (string)$dashboard->{$method}()->getContent();
                $this->assertStringContainsString('yw-dashboard__sidebar', $body, "/dashboard/{$method} renders the rail");
                $this->assertStringNotContainsString($key, $body, 'every label is translated');
            }

            // the API route list is a dashboard screen too, from its own controller
            $api = (string)$wiki->services->get(DocumentationApiController::class)->getDocumentation()->getContent();
            $this->assertStringContainsString('yw-dashboard__sidebar', $api, '/api renders the rail');

            // an action linking to "this page" must get the whole route path: the URL
            // parser reads `?dashboard/forms` as tag `dashboard` + method `forms`, and a
            // self-link built from the tag alone lands on a different screen
            $forms = (string)$dashboard->forms()->getContent();
            $this->assertStringContainsString('dashboard/forms&', $forms, 'BazaR links keep the whole route');
            $this->assertStringNotContainsString('?dashboard&', $forms);
            // BazaR's own tab bar is off on these routes: the rail is the navigation
            $this->assertStringNotContainsString('BAZ_menu', $forms);

            $adminController = $wiki->services->get(AdminController::class);
            $screens = [
                'content', 'lists', 'imports', 'keywords', 'specialPages', 'appearance',
                'users', 'groups', 'permissions', 'reactions', 'spam', 'config', 'updates',
                'backups',
            ];
            foreach ($screens as $method) {
                $body = (string)$adminController->{$method}()->getContent();
                $this->assertStringContainsString('yw-dashboard__canvas', $body, "admin/{$method} renders");
                // an action that no longer exists renders as its own name in a comment
                $this->assertStringNotContainsString('does not exist', $body, "admin/{$method} runs its action");
            }
        } finally {
            $authentication->logout();
            unset($GLOBALS['wiki']);
        }
    }
}

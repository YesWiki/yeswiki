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

/** Administration as routes rather than as seeded wiki pages. */
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
                'index' => 'DASHBOARD_TITLE',
                'forms' => 'DASHBOARD_FORMS',
                'lists' => 'DASHBOARD_ADMIN_LISTS',
                'sources' => 'DASHBOARD_SOURCES',
            ] as $method => $key) {
                $body = (string)$dashboard->{$method}()->getContent();
                $this->assertStringContainsString('yw-dashboard__sidebar', $body, "/dashboard/{$method} renders the rail");
                $this->assertStringNotContainsString($key, $body, 'every label is translated');
            }

            $api = (string)$wiki->services->get(DocumentationApiController::class)->getDocumentation()->getContent();
            $this->assertStringContainsString('yw-dashboard__sidebar', $api, '/api renders the rail');

            $forms = (string)$dashboard->forms()->getContent();
            $this->assertStringContainsString('dashboard/forms&', $forms, 'BazaR links keep the whole route');
            $this->assertStringNotContainsString('?dashboard&', $forms);

            $this->assertStringNotContainsString('BAZ_menu', $forms);

            $adminController = $wiki->services->get(AdminController::class);
            $screens = [
                'content', 'files', 'imports', 'keywords',

                'layout', 'preset', 'customCss', 'customTemplates',
                'users', 'groups', 'reactions', 'spam', 'config', 'updates', 'backups',
            ];
            foreach ($screens as $method) {
                $body = (string)$adminController->{$method}()->getContent();
                $this->assertStringContainsString('yw-dashboard__canvas', $body, "admin/{$method} renders");

                $this->assertStringNotContainsString('does not exist', $body, "admin/{$method} runs its action");
                $this->assertNoUntranslatedKeys($body, "admin/{$method}");
                $this->assertFilePickerIsWiredUp($body, "admin/{$method}");
            }
        } finally {
            $authentication->logout();
            unset($GLOBALS['wiki']);
        }
    }

    /** The rail groups screens by the errand that brings someone to them. */
    #[Depends('testWikiExisting')]
    public function testTheRailGroupsScreensByErrand(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $authentication = $wiki->services->get(AuthenticationService::class);
        $acl = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($u) => $acl->isAdmin($u['name'])
        ));
        $this->assertNotFalse($admin, 'the admin rail needs an admin to render for');

        try {
            $authentication->login($admin);
            $rail = (string)$wiki->services->get(AdminController::class)->content()->getContent();

            $this->assertStringContainsString(_t('DASHBOARD_SECTION_SYSTEM'), $rail);
            $this->assertStringNotContainsString(
                _t('DASHBOARD_ADMIN_PERMISSIONS'),
                $rail,
                'the permissions are a section of the content screen now, not an entry of their own'
            );

            foreach (['ADMIN_PERMISSIONS_PAGES', 'ADMIN_PERMISSIONS_ACTIONS', 'ADMIN_PERMISSIONS_HANDLERS'] as $key) {
                $this->assertStringContainsString(_t($key), $rail, "the content screen carries {$key}");
            }

            $this->assertSame(
                ['dashboard', 'doc', 'dashboard/sources'],
                $this->railOrder($rail, ['dashboard/sources', 'doc', 'dashboard']),
                'the public rail is the dashboard, the documentation and where content came from'
            );
            $this->assertStringNotContainsString(
                'dashboard/forms"',
                $rail,
                'the forms screen is reached from the dashboard, not from a rail entry'
            );

            $this->assertStringContainsString(_t('DASHBOARD_SECTION_APPEARANCE'), $rail);
            $this->assertSame(
                ['admin/layout', 'admin/preset', 'admin/custom-css', 'admin/custom-templates'],
                $this->railOrder($rail, [
                    'admin/custom-templates', 'admin/custom-css', 'admin/preset', 'admin/layout',
                ]),
                'Appearance reads outwards: the shape, the theme, a stylesheet, the templates'
            );
            $this->assertStringNotContainsString(
                'admin/appearance',
                $rail,
                'the appearance screen is retired: its blocks are on the preset screen'
            );

            $this->assertSame(
                ['admin/content', 'admin/files', 'admin/users', 'admin/groups', 'admin/keywords', 'admin/reactions'],
                $this->railOrder($rail, [
                    'admin/reactions', 'admin/keywords', 'admin/groups', 'admin/users', 'admin/files', 'admin/content',
                ]),
                'Administration reads from the content to the people to what they leave on it'
            );

            $this->assertStringNotContainsString(
                '<span>' . _t('DASHBOARD_API') . '</span>',
                $rail,
                'the API is reached from Exports, not from a rail entry'
            );

            $this->assertSame(
                ['admin/config', 'admin/updates', 'admin/backups', 'admin/imports', 'admin/spam'],
                $this->railOrder($rail, ['admin/spam', 'admin/imports', 'admin/backups', 'admin/updates', 'admin/config']),
                'System reads from the file that configures the wiki to the mess to clean up'
            );

            $authentication->logout();
            $visitor = (string)$wiki->services->get(DashboardController::class)->index()->getContent();
            $this->assertStringContainsString('dashboard/sources', $visitor);
            $this->assertStringNotContainsString(_t('DASHBOARD_SECTION_ADMIN'), $visitor, 'and none of the admin half');
        } finally {
            $authentication->logout();
            unset($GLOBALS['wiki']);
        }
    }

    /** A file-picker button needs two other things on the page, and both are easy to forget. */
    private function assertFilePickerIsWiredUp(string $body, string $screen): void
    {
        if (!str_contains($body, 'data-yw-file-picker-field')) {
            return;
        }

        $this->assertStringContainsString(
            'YesWikiFilePickerPanel',
            $body,
            "{$screen} renders a file-picker button but not the panel it opens"
            . ' -- include @core/aceditor-rails.twig'
        );
        $this->assertStringContainsString(
            'inputs/file-picker-field.js',
            $body,
            "{$screen} renders a file-picker button but not the module that binds it"
            . ' -- include_javascript javascripts/inputs/file-picker-field.js'
        );
    }

    /** A key that has no translation renders as itself, which reads as a label nobody wrote. */
    private function assertNoUntranslatedKeys(string $body, string $screen): void
    {
        preg_match_all('/>\s*([A-Z][A-Z0-9]*(?:_[A-Z0-9]+){2,})\s*</', $body, $found);

        $this->assertSame(
            [],
            array_values(array_unique($found[1])),
            "{$screen} shows what looks like an untranslated key: _t() hands back the key it "
            . 'was given when no catalogue defines it'
        );
    }

    /**
     * The given routes, in the order the rail actually lists them.
     *
     * @param list<string> $routes
     *
     * @return list<string>
     */
    private function railOrder(string $page, array $routes): array
    {
        $from = strpos($page, 'yw-dashboard__sidebar');
        $this->assertNotFalse($from, 'the premise: this screen renders the rail');
        $rail = substr($page, $from, (int)strpos($page, '</nav>', $from) - $from);

        $positions = [];
        foreach ($routes as $route) {
            $at = strpos($rail, $route . '"');
            $this->assertNotFalse($at, "{$route} is missing from the rail");
            $positions[$route] = $at;
        }
        asort($positions);

        return array_keys($positions);
    }
}

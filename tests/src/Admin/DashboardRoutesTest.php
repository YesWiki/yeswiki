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
                'lists' => 'DASHBOARD_ADMIN_LISTS',
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
                'content', 'imports', 'keywords',
                // `appearance` is retired: its two blocks live on `preset` now (ticket 30),
                // beside the component gallery a colour preset is actually judged against
                'layout', 'preset', 'customCss', 'customTemplates',
                'users', 'groups', 'reactions', 'spam', 'config', 'updates', 'backups',
            ];
            foreach ($screens as $method) {
                $body = (string)$adminController->{$method}()->getContent();
                $this->assertStringContainsString('yw-dashboard__canvas', $body, "admin/{$method} renders");
                // an action that no longer exists renders as its own name in a comment
                $this->assertStringNotContainsString('does not exist', $body, "admin/{$method} runs its action");
                $this->assertNoUntranslatedKeys($body, "admin/{$method}");
                $this->assertFilePickerIsWiredUp($body, "admin/{$method}");
            }
        } finally {
            $authentication->logout();
            unset($GLOBALS['wiki']);
        }
    }

    /**
     * The rail groups screens by the errand that brings someone to them.
     *
     * Administration is what is *in* the wiki; System is the wiki itself. Two screens have
     * no entry of their own at all: the permissions render on the Content screen (they are
     * permissions over content) and the lists sit with the Forms they belong to.
     */
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

            // ...and the content screen is where they went
            foreach (['ADMIN_PERMISSIONS_PAGES', 'ADMIN_PERMISSIONS_ACTIONS', 'ADMIN_PERMISSIONS_HANDLERS'] as $key) {
                $this->assertStringContainsString(_t($key), $rail, "the content screen carries {$key}");
            }

            $this->assertSame(
                ['dashboard/forms', 'dashboard/lists', 'dashboard/export'],
                $this->railOrder($rail, ['dashboard/export', 'dashboard/lists', 'dashboard/forms']),
                'the lists sit with the forms they belong to'
            );
            // Appearance is its own errand (ticket 30) and reads outwards: the layout is the
            // shape of every page, the preset its colours, and the CSS what you reach for
            // when neither of the two can say it
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

            // Administration reads people-ward: what is in the wiki, who is in it, how they
            // are grouped, then the two things that describe and answer content
            $this->assertSame(
                ['admin/content', 'admin/users', 'admin/groups', 'admin/keywords', 'admin/reactions'],
                $this->railOrder($rail, [
                    'admin/reactions', 'admin/keywords', 'admin/groups', 'admin/users', 'admin/content',
                ]),
                'Administration reads from the content to the people to what they leave on it'
            );

            // The API has no entry of its own: it is one of the ways the wiki's content comes
            // out, so it is listed on the Export screen with the feeds and the per-form formats.
            // The route itself still renders (see testEveryDashboardRouteRenders).
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

            // a visitor gets the public half of the rail, lists included: what a list says
            // is already visible in every field that uses it
            $authentication->logout();
            $visitor = (string)$wiki->services->get(DashboardController::class)->activity()->getContent();
            $this->assertStringContainsString('dashboard/lists', $visitor);
            $this->assertStringNotContainsString(_t('DASHBOARD_SECTION_ADMIN'), $visitor, 'and none of the admin half');
        } finally {
            $authentication->logout();
            unset($GLOBALS['wiki']);
        }
    }

    /**
     * A file-picker button needs two other things on the page, and both are easy to forget.
     *
     * The panel (`@core/aceditor-rails.twig`) and the module that binds the click
     * (`javascripts/inputs/file-picker-field.js`). Miss either and the button renders,
     * looks right, and does nothing -- there is no error anywhere, which is why this went
     * unnoticed on the Layout screen until someone clicked it. `usersettings.twig` shipped
     * the button without the panel once, for the same reason.
     *
     * Asserted as a rule over every screen rather than on the one that broke: the next
     * screen to add a picker will forget the same two lines.
     */
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

    /**
     * A key that has no translation renders as itself, which reads as a label nobody wrote.
     *
     * The Layout screen shipped a button labelled `ATTACH_FILE_PICKER_CHOOSE_EXISTING` --
     * a key that exists in no catalogue, so `_t()` handed back the key. Nothing fails, and
     * it looks like a translation that has not been done yet rather than a name that was
     * never defined.
     */
    private function assertNoUntranslatedKeys(string $body, string $screen): void
    {
        // an all-caps token of three or more underscore-separated words, in text position:
        // narrow enough not to trip on CSS class names, JS constants or SQL
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
        // the rail only: the canvas beside it is full of links to these same routes, and
        // an order read across both would be an order of whatever the screen happens to
        // render today
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

<?php

namespace YesWiki\Core;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;

/**
 * What every screen rendered inside `@core/dashboard/layout.twig` has to tell the shell.
 *
 * Five controllers render that layout -- dashboard, admin, account, the documentation and
 * the API route list -- and the rail hides or shows whole sections from these flags. They
 * were being spelled out at each call site, which is how a section comes to appear on four
 * screens out of five.
 *
 * `is_connected` in particular cannot be left to Twig's `user` global: that global is a
 * snapshot of `$_SESSION` taken when TemplateEngine was constructed, so anything that
 * signs in after the engine exists (every test, and any request that renders after a login)
 * reads it as anonymous. Asked here, it is asked of the session as it stands now.
 */
trait DashboardShell
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function dashboardShell(string $current, array $data = []): array
    {
        // getLoggedUser(), not getLoggedUserName(): the latter answers with the client's IP
        // address when nobody is signed in (an anonymous editor's "name" is their IP), so it
        // says yes to everyone
        $connected = !empty($this->getService(AuthenticationService::class)->getLoggedUser());

        return $data + [
            'current' => $current,
            'is_admin' => $this->getService(AclService::class)->isAdmin(),
            'is_connected' => $connected,
            'shell_scope' => $this->scopeOf($current),
        ];
    }

    /**
     * Which half of the rail this screen belongs to.
     *
     * The account and the wiki's administration are two different errands: on `/user` the
     * rail lists what you can do with your account and nothing else, and on the dashboard it
     * lists the wiki. Derived from the route's own path rather than passed, so a screen
     * cannot be added to one section and keep showing the other's.
     *
     * The signed-out screens do not come through here at all: they render in
     * `@core/account/guest-layout.twig`, which has no rail to scope.
     */
    private function scopeOf(string $current): string
    {
        return $current === 'user' || str_starts_with($current, 'user/') ? 'account' : 'wiki';
    }
}

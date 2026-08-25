<?php

namespace YesWiki\Core;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\HealthService;

/** What every screen rendered inside `@core/dashboard/layout.twig` has to tell the shell. */
trait DashboardShell
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function dashboardShell(string $current, array $data = []): array
    {
        $connected = !empty($this->getService(AuthenticationService::class)->getLoggedUser());
        $isAdmin = $this->getService(AclService::class)->isAdmin();

        return $data + [
            'current' => $current,
            'is_admin' => $isAdmin,
            'is_connected' => $connected,
            'shell_scope' => $this->scopeOf($current),
            // The same count the chrome badge shows, on the rail entry that leads to it (ticket 52).
            'health_broken' => $isAdmin ? $this->getService(HealthService::class)->brokenCount() : 0,
        ];
    }

    /** Which half of the rail this screen belongs to. */
    private function scopeOf(string $current): string
    {
        return $current === 'user' || str_starts_with($current, 'user/') ? 'account' : 'wiki';
    }
}

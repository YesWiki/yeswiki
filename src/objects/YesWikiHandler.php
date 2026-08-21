<?php

namespace YesWiki\Core;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use YesWiki\Identity\Service\AclService;

abstract class YesWikiHandler extends YesWikiPerformable
{
    public const ROLE_ERRORS = [
        'read' => 'DENY_READ',
        'write' => 'DENY_WRITE',
        'comment' => 'DENY_COMMENT',
        'delete' => 'DENY_DELETE',
    ];

    protected function denyAccessUnlessGranted(string $role, ?string $tag = null): void
    {
        // hasAccess() reads '' as "the current page", which is what a caller that names no
        // tag means; it is declared string, so the null default cannot be handed over as-is
        if (!$this->getService(AclService::class)->hasAccess($role, $tag ?? '')) {
            throw new AccessDeniedHttpException(_t(self::ROLE_ERRORS[$role] ?? 'DENY_READ'));
        }
    }
}

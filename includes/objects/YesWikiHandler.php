<?php

namespace YesWiki\Core;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use YesWiki\Core\Service\AclService;

abstract class YesWikiHandler extends YesWikiPerformable
{
    public const ROLE_ERRORS = [
        'read' => 'DENY_READ',
        'write' => 'DENY_WRITE',
        'comment' => 'DENY_COMMENT',
        'delete' => 'DENY_DELETE',
    ];

    protected function denyAccessUnlessGranted($role, $tag = null)
    {
        if (!$this->getService(AclService::class)->hasAccess($role, $tag)) {
            throw new AccessDeniedHttpException(_t(self::ROLE_ERRORS[$role] ?? 'DENY_READ'));
        }
    }
}

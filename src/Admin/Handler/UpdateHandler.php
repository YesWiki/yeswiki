<?php

namespace YesWiki\Admin\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;

class UpdateHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/update` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'update';
    }

    public function run(): string
    {
        if ($this->getService(HibernationService::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->renderFullPage('@core/handlers/update.twig', [
                'output' => '<div class="alert alert-danger">' . _t('ACLS_RESERVED_FOR_ADMINS') . '</div>',
            ]);
        }

        $requestGuard = $this->getService(RequestGuard::class);
        if (!$requestGuard->linkIsSigned($this->getRequest())) {
            return $this->renderFullPage('@core/handlers/update.twig', [
                'output' => '',
                'confirmUrl' => $requestGuard->sign($this->getService(UrlFormatter::class)->href('update', '', null, false)),
            ]);
        }

        $res = [];
        exec('./yeswicli migrate', $res);

        return $this->renderFullPage('@core/handlers/update.twig', [
            'output' => implode('<br>', $res),
        ]);
    }
}

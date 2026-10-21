<?php

use YesWiki\Core\Service\RequestGuard;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler extends YesWikiHandler
{
    public function run(): string
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (!$this->wiki->UserIsAdmin()) {
            return $this->renderInSquelette('@core/handlers/update.twig', [
                'output' => '<div class="alert alert-danger">' . _t('ACLS_RESERVED_FOR_ADMINS') . '</div>',
            ]);
        }

        $requestGuard = $this->getService(RequestGuard::class);
        if (!$requestGuard->linkIsSigned($this->wiki->request)) {
            return $this->renderInSquelette('@core/handlers/update.twig', [
                'output' => '',
                'confirmUrl' => $requestGuard->sign($this->wiki->href('update', '', null, false)),
            ]);
        }

        $res = [];
        exec('./yeswicli migrate', $res);

        return $this->renderInSquelette('@core/handlers/update.twig', [
            'output' => implode('<br>', $res),
        ]);
    }
}

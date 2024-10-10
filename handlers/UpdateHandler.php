<?php

use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler extends YesWikiHandler
{
    public function run(): string
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $output = '';

        if ($this->wiki->UserIsAdmin()) {
            $res = [];
            exec('./yeswicli migrate', $res);
            $output .= implode('<br>', $res);
        } else {
            $output .= '<div class="alert alert-danger">' . _t('ACLS_RESERVED_FOR_ADMINS') . '</div>';
        }

        return $this->renderInSquelette('@core/handlers/update.twig', [
            'output' => $output,
        ]);
    }
}

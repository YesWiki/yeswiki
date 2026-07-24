<?php

use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\YesWikiHandler;

class UpdateHandler extends YesWikiHandler
{
    public function run(): string
    {
        if ($this->getService(HibernationService::class)->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
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

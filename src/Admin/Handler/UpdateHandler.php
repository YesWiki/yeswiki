<?php

namespace YesWiki\Admin\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\HibernationService;

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

        $output = '';

        if ($this->wiki->UserIsAdmin()) {
            $res = [];
            exec('./yeswicli migrate', $res);
            $output .= implode('<br>', $res);
        } else {
            $output .= '<div class="alert alert-danger">' . _t('ACLS_RESERVED_FOR_ADMINS') . '</div>';
        }

        return $this->renderFullPage('@core/handlers/update.twig', [
            'output' => $output,
        ]);
    }
}

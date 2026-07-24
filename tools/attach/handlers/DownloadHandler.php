<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;

class DownloadHandler extends YesWikiHandler
{
    public function run(): string
    {
        if ($HasAccessRead = $this->getService(AclService::class)->HasAccess('read')) {
            if (!class_exists('attach')) {
                include YESWIKI_SOURCE_DIR . '/tools/attach/libs/attach.lib.php';
            }
            $att = new attach($this->wiki);
            $att->doDownload();
            unset($att);
        } else {
            return $this->renderInSquelette('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('ERROR_NO_ACCESS'),
            ]);
        }
    }
}

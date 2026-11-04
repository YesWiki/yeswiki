<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;

class DownloadHandler extends YesWikiHandler
{
    public function run(): string
    {
        if (!$this->getService(AclService::class)->HasAccess('read')) {
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('ERROR_NO_ACCESS'),
            ]);
        }
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $att = new attach($this->wiki);
        if ($att->doDownload()) {
            $this->wiki->exit();
        }
        http_response_code(404);

        return $this->renderInSquelette('@templates/alert-message.twig', [
            'type' => 'danger',
            'message' => _t('ATTACH_DOWNLOAD_NOT_FOUND'),
        ]);
    }
}

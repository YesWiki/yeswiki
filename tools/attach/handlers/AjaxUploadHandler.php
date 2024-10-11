<?php

namespace YesWiki\Attach;

use Attach;
use qqFileUploader;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

class AjaxUploadHandler extends YesWikiHandler
{
    private $hasTempTag;

    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (!$this->hasAccesUpload($_GET)) {
            return $this->formatOuput(['error' => _t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE')]);
        }

        // load classes
        require_once 'tools/attach/libs/qq.lib.php';

        if (!class_exists('attach')) {
            include_once 'tools/attach/libs/attach.lib.php';
        }
        $errorsMessage = '';
        ob_start();
        try {
            $att = new attach($this->wiki);

            // list of valid extensions, ex. array("jpeg", "xml", "bmp")
            $allowedExtensions = array_keys($this->params->get('authorized-extensions'));

            // max file size in bytes
            $sizeLimit = $att->attachConfig['max_file_size'];

            $uploader = new qqFileUploader($allowedExtensions, $sizeLimit, $this->hasTempTag);
            $result = $uploader->handleUpload($att->attachConfig['upload_path']);
        } catch (\Throwable $th) {
            $errorsMessage .= "{$th->getMessage()} in {$th->getFile()}, line {$th->getLine()}";
        }
        $errorsMessage .= ob_get_contents();
        ob_end_clean();
        if (!empty($errorsMessage)) {
            $result['error'] = ($result['error'] ?? '') . $errorsMessage;
        }

        return $this->formatOuput($result);
    }

    private function hasAccesUpload(array $get): bool
    {
        $tag = $this->wiki->getPageTag();
        if (empty(trim($tag))) {
            return false;
        }

        $this->hasTempTag = (
            isset($get['tempTag'])
            && preg_match("/^{$this->params->get('temp_tag_for_entry_creation')}_[A-Fa-f0-9]+$/m", $get['tempTag'])
        );
        $page = $this->getService(PageManager::class)->getOne($tag);
        $aclService = $this->getService(AclService::class);

        return (
            empty($page) // new page
                    && $aclService->hasAccess('write', $tag) // default rights to write
        ) || (
            !empty($page) // existing page
                && $aclService->hasAccess('write', $tag)
        ) || (
            !empty($page) // existing page
                && $aclService->hasAccess('read', $tag) // page with cration of entries
                && $this->hasTempTag
        )
        ;
    }

    private function formatOuput(array $ouput): string
    {
        return htmlspecialchars(json_encode($ouput), ENT_NOQUOTES, YW_CHARSET);
    }
}

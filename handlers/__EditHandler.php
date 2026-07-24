<?php

use YesWiki\Core\Controller\SecurityController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TagsManager;
use YesWiki\Core\YesWikiHandler;

class __EditHandler extends YesWikiHandler
{
    public function run()
    {
        // get services
        $aclService = $this->getService(AclService::class);
        $tagsManager = $this->getService(TagsManager::class);

        if (
            !$this->params->get('hide_keywords')
            && $aclService->hasAccess('write')
        ) {
            // save new tag if authorized
            $post = $this->getRequest()->request;
            if (
                $post->get('submit') == SecurityController::EDIT_PAGE_SUBMIT_VALUE
                && $post->has('pagetags')
                && $post->get('antispam') == 1
            ) {
                $tagsManager->save($this->wiki->GetPageTag(), stripslashes($post->get('pagetags')));
            }

            // display: the live-search tag-input widget (javascripts/yw-tags-input.js)
            // queries GET /api/tags itself as the user types -- no tag list to dump here
            if ($aclService->hasAccess('read')) {
                $this->wiki->AddJavascriptFile('javascripts/yw-tags-input.js');
            }
        }
    }
}

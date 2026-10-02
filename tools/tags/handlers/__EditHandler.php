<?php

namespace YesWiki\Tags;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;

class __EditHandler extends YesWikiHandler
{
    public function run()
    {
        $aclService = $this->getService(AclService::class);
        $tagsManager = $this->getService(TagsManager::class);

        if (
            !$this->params->get('hide_keywords')
            && $aclService->hasAccess('write')
        ) {
            $post = $this->getRequest()->request;
            if (
                $post->get('submit') == SecurityController::EDIT_PAGE_SUBMIT_VALUE
                && $post->has('pagetags')
                && $this->getService(BotGuard::class)->check($this->getRequest()) === null
            ) {
                $tagsManager->save($this->wiki->GetPageTag(), stripslashes($post->get('pagetags')));
            }

            if ($aclService->hasAccess('read')) {
                $formattedTags = [];
                $tags = $tagsManager->getAll();
                $tags = is_array($tags)
                    ? array_map(
                        function ($t) {
                            return $t['value'];
                        },
                        $tags
                    )
                    : [];
                sort($tags);

                $formattedTags = json_encode($tags);
                $this->wiki->AddJavascript(<<<JS
                    var existingTags = $formattedTags
                    
                JS);
                $this->wiki->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
                $this->wiki->AddJavascriptFile('tools/tags/javascripts/edit-tags.js');
            }
        }
    }
}

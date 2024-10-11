<?php

namespace YesWiki\Tags;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;

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
            if (
                isset($_POST['submit'])
                && $_POST['submit'] == SecurityController::EDIT_PAGE_SUBMIT_VALUE
                && isset($_POST['pagetags'])
                && $_POST['antispam'] == 1
            ) {
                $tagsManager->save($this->wiki->GetPageTag(), stripslashes($_POST['pagetags']));
            }

            // display
            if ($aclService->hasAccess('read')) {
                $formattedTags = [];
                // get all tags
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

                // not possible to use ->render because output is entrirely defined by edit.php
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

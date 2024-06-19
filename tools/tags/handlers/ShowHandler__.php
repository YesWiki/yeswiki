<?php

namespace YesWiki\Tags;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Tags\Service\TagsManager;

class ShowHandler__ extends YesWikiHandler
{
    public function run()
    {
        // get services
        $aclService = $this->getService(AclService::class);
        $entryManager = $this->getService(EntryManager::class);
        $tagsManager = $this->getService(TagsManager::class);

        // display tags if needed
        $tag = $this->wiki->getPageTag();
        if (!$this->params->get('hide_keywords') && (bool)$this->wiki->page && !empty($tag) && $aclService->hasAccess('read', $tag) && !$entryManager->isEntry($tag)) {
            $tags = array_column($tagsManager->getAll($tag), 'value');
            if (!empty($tags)) {
                $output = $this->render('@tags/tags-at-page-bottom.twig', [
                    'pageTag' => $tag,
                    'tags' => $tags,
                ]);
                $replaced = preg_replace('/\<hr class=\"hr_clear\" \/\>/', "$output\n<hr class=\"hr_clear\" />", $this->output);
                if (!empty($replaced)) {
                    $this->output = $replaced;
                }
            }
        }
    }
}

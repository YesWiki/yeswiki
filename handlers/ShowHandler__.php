<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\EntryManager;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Core\YesWikiHandler;

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
                $output = $this->render('@core/tags-at-page-bottom.twig', [
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

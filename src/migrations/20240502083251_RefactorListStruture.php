<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class RefactorListStruture extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $listManager = $this->getService(ListManager::class);
        foreach ($pageManager->tagsOfType(PageType::LIST) as $tag) {
            $page = $pageManager->getOne($tag);
            if ($page === null) {
                continue;
            }

            $body = $page['body'] ?? [];
            $oldJson = is_array($body) ? $body : json_decode((string)$body, true);
            $newJson = $listManager->convertDataStructure(is_array($oldJson) ? $oldJson : []);
            $pageManager->save($tag, $newJson);
        }
    }
}

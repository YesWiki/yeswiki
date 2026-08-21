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

            // ticket 09 made a body a decoded array; before it, this column held the JSON text
            // this migration was written against, and an upgrade can reach either shape
            $body = $page['body'] ?? [];
            $oldJson = is_array($body) ? $body : json_decode((string)$body, true);
            $newJson = $listManager->convertDataStructure(is_array($oldJson) ? $oldJson : []);
            $pageManager->save($tag, $newJson);
        }
    }
}

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
            $oldJson = json_decode($page['body'], true);
            $newJson = $listManager->convertDataStructure($oldJson);
            $pageManager->save($tag, json_encode($newJson));
        }
    }
}

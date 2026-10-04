<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/** Converts every list from { titre_liste, label: {id: label} } to { title, nodes: [{id, label}] }, read-protected lists included. */
class RefactorListStruture extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $listManager = $this->getService(ListManager::class);
        foreach ($pageManager->tagsOfType(PageType::LIST) as $tag) {
            $page = $pageManager->getOne($tag, null, false, true);
            if ($page === null) {
                continue;
            }

            $body = $page['body'] ?? [];
            $oldJson = is_array($body) ? $body : json_decode((string)$body, true);
            if (!is_array($oldJson)) {
                continue;
            }
            $newJson = $listManager->convertDataStructure($oldJson);
            if ($newJson !== $oldJson) {
                $pageManager->save($tag, $newJson, '', true);
            }
        }
    }
}

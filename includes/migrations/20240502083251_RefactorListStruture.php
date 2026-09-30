<?php

use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

/** Converts every list from { titre_liste, label: {id: label} } to { title, nodes: [{id, label}] }. */
class RefactorListStruture extends YesWikiMigration
{
    public function run()
    {
        $tripleStore = $this->wiki->services->get(TripleStore::class);
        $pageManager = $this->wiki->services->get(PageManager::class);
        $listManager = $this->wiki->services->get(ListManager::class);
        $lists = $tripleStore->getMatching(null, TripleStore::TYPE_URI, ListManager::TRIPLES_LIST_ID, '', '');
        foreach ($lists as $list) {
            $tag = $list['resource'];
            $page = $pageManager->getOne($tag, null, false, true);
            if (empty($page)) {
                continue;
            }
            $oldJson = json_decode($page['body'], true);
            $newJson = $listManager->convertDataStructure($oldJson);
            if ($newJson !== $oldJson) {
                $pageManager->save($tag, json_encode($newJson), '', true);
            }
        }
    }
}

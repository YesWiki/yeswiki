<?php

use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

// Convert old List { titre_liste: "My List", label: { id1: "first Key", id2: "second id" } }
// to { title: "My List", values: [{ id: "id1", label: "first id"}, { id: "id2", label: "second id"}]}
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
            $page = $pageManager->getOne($tag);
            $oldJson = json_decode($page['body'], true);
            $newJson = $listManager->convertDataStructure($oldJson);
            $pageManager->save($tag, json_encode($newJson));
        }
    }
}
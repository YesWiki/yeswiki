<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

class ListManager
{
    protected $wiki;
    protected $dbService;
    protected $tripleStore;
    protected $pageManager;
    protected $params;

    protected $cachedLists;

    public function __construct(Wiki $wiki, DbService $dbService, TripleStore $tripleStore, PageManager $pageManager, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->tripleStore = $tripleStore;
        $this->pageManager = $pageManager;
        $this->params = $params;

        $this->cachedLists = [];
    }

    public function getOne($id)
    {
        if (isset($this->cachedLists[$id])) {
            return $this->cachedLists[$id];
        }

        // Ensure a list exist with this ID
        if (!$this->tripleStore->exist($id, 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '')) {
            return false;
        }

        $page = $this->pageManager->getOne($id);
        $json = json_decode($page['body'], true);

        if (YW_CHARSET !== 'UTF-8') {
            $this->cachedLists[$id]['titre_liste'] = utf8_decode($json['titre_liste']);
            $this->cachedLists[$id]['label'] = array_map('utf8_decode', $json['label']);
        } else {
            $this->cachedLists[$id] = $json;
        }

        return $this->cachedLists[$id];
    }

    public function getAll()
    {
        $lists = $this->tripleStore->getMatching(null, 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '');

        return array_map(function($list) {
            return $this->getOne($list['resource']);
        }, $lists);
    }
}

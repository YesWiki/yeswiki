<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\Mailer;
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

    public const TRIPLES_LIST_ID = 'liste';

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
        if (!$this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '')) {
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
        $lists = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        $result = [];
        foreach($lists as $list) {
            $result[$list['resource']] = $this->getOne($list['resource']);
        }

        return $result;
    }

    public function create($title, $values)
    {
        $id = genere_nom_wiki('Liste '.$title);

        if (YW_CHARSET !== 'UTF-8') {
            $values = array_map('utf8_encode', $values);
            $title = utf8_encode($title);
        }

        $this->pageManager->save($id, json_encode([
            'titre_liste' => $title,
            'label' => $values
        ]));

        $this->tripleStore->create($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');
    }

    public function update($id, $title, $values)
    {
        if (YW_CHARSET !== 'UTF-8') {
            $values = array_map('utf8_encode', $values);
            $title = utf8_encode($title);
        }

        $this->pageManager->save($id, json_encode([
            'titre_liste' => $title,
            'label' => $values
        ]));
    }

    public function delete($id)
    {
        if( !isset($id) || $id === '') {
            throw new \Exception('List ID not specified');
        }

        if( !$GLOBALS['wiki']->UserIsAdmin() && !$GLOBALS['wiki']->UserIsOwner($id)) {
            throw new \Exception('Unauthorized');
        }

        $this->pageManager->deleteOrphaned($id);

        $this->tripleStore->delete($id, TripleStore::TYPE_URI, null, '', '');
    }
}

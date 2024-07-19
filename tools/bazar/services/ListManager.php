<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class ListManager
{
    protected $wiki;
    protected $dbService;
    protected $htmlPurifierService;
    protected $pageManager;
    protected $params;
    protected $securityController;
    protected $tripleStore;

    public const TRIPLES_LIST_ID = 'liste';

    protected $cachedLists;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        HtmlPurifierService $htmlPurifierService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        SecurityController $securityController,
        TripleStore $tripleStore
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->tripleStore = $tripleStore;
        $this->pageManager = $pageManager;
        $this->htmlPurifierService = $htmlPurifierService;
        $this->params = $params;
        $this->securityController = $securityController;

        $this->cachedLists = [];
    }

    public function getOne($id): ?array
    {
        if (isset($this->cachedLists[$id])) {
            return $this->cachedLists[$id];
        }

        // Ensure a list exist with this ID
        if (!$this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '')) {
            return null;
        }

        $page = $this->pageManager->getOne($id);
        $json = json_decode($page['body'], true);
        $json = $this->convertDataStructure($json);
        $json['id'] = $id;
        $this->cachedLists[$id] = $json;

        return $json;
    }

    // The structure of List object has been changed in 2024
    // Convert old List { titre_liste: "My List", label: { id1: "first Key", id2: "second id" } }
    // to { title: "My List", values: [{ id: "id1", label: "first id"}, { id: "id2", label: "second id"}]}
    // We still convert the strucure on the fly in case the migration went wrong
    public function convertDataStructure($json)
    {
        if (isset($json['titre_liste'])) {
            $newJson = ['title' => $json['titre_liste'], 'nodes' => []];
            foreach ($json['label'] as $id => $label) {
                $newJson['nodes'][] = ['id' => $id, 'label' => $label];
            }

            return $newJson;
        }

        return $json;
    }

    public function getAll(): array
    {
        $lists = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        $result = [];
        foreach ($lists as $list) {
            $result[$list['resource']] = $this->getOne($list['resource']);
        }

        return $result;
    }

    public function create($title, $nodes)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $id = genere_nom_wiki('List' . $title);
        $this->pageManager->save($id, json_encode([
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ]));

        $this->tripleStore->create($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        return $id;
    }

    public function update($id, $title, $nodes)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->pageManager->save($id, json_encode([
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ]));
    }

    public function delete($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!isset($id) || $id === '') {
            throw new \Exception('List ID not specified');
        }

        if (!$GLOBALS['wiki']->UserIsAdmin() && !$GLOBALS['wiki']->UserIsOwner($id)) {
            throw new \Exception('Unauthorized');
        }

        $this->pageManager->deleteOrphaned($id);

        $this->tripleStore->delete($id, TripleStore::TYPE_URI, null, '', '');
    }

    private function sanitizeHMTL(array $nodes)
    {
        return array_map(function ($node) {
            $node['label'] = $this->htmlPurifierService->cleanHTML($node['label']);
            $node['children'] = $this->sanitizeHMTL($node['children']);

            return $node;
        }, $nodes);
    }
}

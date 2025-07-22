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

    public function isList($id): bool
    {
        return boolval($this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', ''));
    }

    public function getOne($id, $parent = null): ?array
    {
        if (isset($this->cachedLists[$id]) && $parent === null) { // we cache all information, not just a level
            return $this->cachedLists[$id];
        }

        // Ensure a list exist with this ID
        if (!$this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '')) {
            return null;
        }

        $page = $this->pageManager->getOne($id);
        if (empty($page)) {
            echo '<div class="alert alert-danger">List id not found: ' . $id . '</div>';

            return null;
        }
        $data = $this->loadJson($page['body'], $id);
        if ($parent != null) {
            $this->cachedLists[$id] = $data;
        }

        if ($parent === 'root') {
            $data['nodes'] = array_map(function ($a) {
                unset($a['children']);

                return $a;
            }, $data['nodes']);
            $data['parentId'] = $parent;
        } elseif (!empty($parent)) {
            $data['nodes'] = multiArraySearch($data['nodes'], 'id', $parent)[0]['children'] ?? null;
            $data['parentId'] = $parent;
        }

        return $data;
    }

    private function loadJson(string $json, $id): array
    {
        $data = $this->convertDataStructure(json_decode($json, true));
        $data['id'] = $id;

        return $data;
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

    public function getAll($parent = null): array
    {
        $lists = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        $result = [];
        foreach ($lists as $list) {
            $result[$list['resource']] = $this->getOne($list['resource'], $parent);
        }

        return $result;
    }

    public function create($title, $nodes, $id = null)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $id = $id ?? genere_nom_wiki('List ' . $title);
        $nodes = $nodes ?? [];
        $this->trimRecursiveInPlace($nodes);
        $json = json_encode([
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ]);
        $this->pageManager->save($id, $json);

        $data = $this->loadJson($json, $id);
        $this->cachedLists[$id] = $data;

        $this->tripleStore->create($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        return $id;
    }

    public function update($id, $title, $nodes)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $nodes = $nodes ?? [];
        $this->trimRecursiveInPlace($nodes);
        $json = json_encode([
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ]);
        $this->pageManager->save($id, $json);

        $data = $this->loadJson($json, $id);
        $this->cachedLists[$id] = $data;
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

        unset($this->cachedLists[$id]);
        $this->tripleStore->delete($id, TripleStore::TYPE_URI, null, '', '');
    }

    private function sanitizeHMTL(array $nodes)
    {
        return array_map(function ($node) {
            $node['label'] = $this->htmlPurifierService->cleanHTML($node['label']);
            $node['children'] = $this->sanitizeHMTL($node['children'] ?? []);

            return $node;
        }, $nodes);
    }

    /**
     * Recursively trims string values in a multidimensional array (in-place).
     * Non-string values are left untouched.
     *
     * @param array  $array    The input array (will be modified by reference)
     * @param string $charlist Optional. The characters to trim. Defaults to whitespace.
     */
    private function trimRecursiveInPlace(array &$array, string $charlist = " \t\n\r\0\x0B"): void
    {
        array_walk_recursive($array, function (&$value) use ($charlist) {
            if (is_string($value)) {
                $value = trim($value, $charlist);
            }
        });
    }
}

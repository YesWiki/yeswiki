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

    public function getOne($id, $parent = null, $lang='default'): ?array
    {
        if (isset($this->cachedLists[$id. $lang]) && $parent === null) { // we cache all information, not just a level
            return $this->cachedLists[$id. $lang];
        }

        // Ensure a list exist with this ID
        if (!$this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '')) {
            return null;
        }

        $page = $this->pageManager->getOne($id, null, true, false, null, $lang);
        if (empty($page)) {
            echo '<div class="alert alert-danger">List id not found: ' . $id . '</div>';

            return null;
        }
        $data = json_decode($page['body'], true);
        if ($parent != null) {
            $this->cachedLists[$id. $lang] = $data;
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

    public function getAll($parent = null): array
    {
        $data = $this->pageManager->getManyFromTriple("liste", "tag, body");

        $result = [];
        foreach ( $data as $value) {
            $result[$value['tag']] = json_decode($value['body'], true) ;
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
            'id' => $id,
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ], JSON_FORCE_OBJECT);
        $this->pageManager->save($id, $json);

        // cache the newly created list
        $this->cachedLists[$id] = json_decode($json);

        $this->tripleStore->create($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        return $id;
    }

    public function update($id, $title, $nodes, $extra_lang = [])
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $nodes = $nodes ?? [];
        $this->trimRecursiveInPlace($nodes);
        $list = [
        'id' => $id,
        'title' => $title,
        'nodes' => $this->sanitizeHMTL($nodes)
        ];
        if (!empty($extra_lang)) {
            $sanitized_langs = [];
            foreach ($extra_lang as $lang => $value) {
                $sanitized_langs[$lang] = [];
                if (array_key_exists('title', $value)) {
                    $sanitized_langs[$lang]['title'] = $this->htmlPurifierService->cleanHTML($value['title']);
                }
                if (array_key_exists('nodes', $value)) {
                    $sanitized_langs[$lang]['nodes'] = $this->sanitizeHMTL($value['nodes']);
                }
            }
            $list['extralang'] = $sanitized_langs;
        }
        $json = json_encode($list, JSON_FORCE_OBJECT);
        $this->pageManager->save($id, $json);

        // cache the newly created list
        $this->cachedLists[$id] = json_decode($json);
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

    public function getLabel($idList, $key): string
    {
        $list = $this->getOne($idList);
        if (empty($list)) {
            return '';
        }
        $val = multiArraySearch($list['nodes'], 'id', $key);
        $val = array_shift($val);

        return $val['label'] ?? '';
    }

    private function sanitizeHMTL(array $nodes)
    {
        return array_map(function ($node) {
            if (array_key_exists('label', $node)) {
                $node['label'] = $this->htmlPurifierService->cleanHTML($node['label']);
            }
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

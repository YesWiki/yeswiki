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

    /**
     * Retrieve list from database.
     *
     * @param $id : id of the list to retriev
     * @param $lang : extra param pour get translated list. Can be :
     *   - Code of lang to retrieve
     *   - "default" : get list with only default wiki language
     *  - "all" : same as default but keep all translation. Useful for edition page to translate the list.
     */
    public function getOne($id, $lang = 'default'): ?array
    {
        if ($lang === 'default' and isset($_GET['lang'])) {
            $lang = $_GET['lang'];
        }
        $lang_id = $id;
        if ($lang != 'default') {
            $lang_id .= '_' . $lang;
        }
        if (isset($this->cachedLists[$lang_id])) {
            return $this->cachedLists[$lang_id];
        }

        // Ensure a list exist with this ID
        if (!$this->tripleStore->exist($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '')) {
            return null;
        }

        if ($lang === 'all' || $lang === 'default') {
            $select_options = '*';
        } else {
            $select_options = "id, tag, time, body_r, owner, user, latest, handler, comment_on ,JSON_MERGE_PATCH(body, COALESCE(JSON_EXTRACT(body, \"\$.__extra_lang.$lang\"), body)) as body";
        }
        $page = $this->pageManager->getOne($id, null, true, false, null, $select_options);
        $json = json_decode($page['body'], true);
        $json = $this->convertDataStructure($json);
        if ($lang != 'all') {
            unset($json['__extra_lang']);
        }
        $json['nodes'] = array_values($json['nodes']);
        dump('array values', $json);
        $json['id'] = $id;
        $this->cachedLists[$lang_id] = $json;

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

    public function getAll($lang = 'default'): array
    {
        $lists = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        $result = [];
        foreach ($lists as $list) {
            $result[$list['resource']] = $this->getOne($list['resource'], $lang);
        }

        return $result;
    }

    public function create($title, $nodes, $id = null, $extra_lang = [], )
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $id = $id ?? genere_nom_wiki('List' . $title);

        $this->pageManager->save($id, json_encode($this->sanitizeList($title, $nodes, $extra_lang), JSON_FORCE_OBJECT));

        $this->tripleStore->create($id, TripleStore::TYPE_URI, self::TRIPLES_LIST_ID, '', '');

        return $id;
    }

    public function update($id, $title, $nodes, $extra_lang)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->pageManager->save($id, json_encode($this->sanitizeList($title, $nodes, $extra_lang), JSON_FORCE_OBJECT));
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

    private function sanitizeList($title, $nodes, $extra_lang)
    {
        $list = [];
        $list['title'] = $title;
        $list['nodes'] = $this->sanitizeHTML($nodes ?? []);
        $list['__extra_lang'] = [];
        foreach ($extra_lang as $lang => $value) {
            $list['__extra_lang'][$lang] = [];
            if (isset($value['title'])) {
                $list['__extra_lang'][$lang]['title'] = $value['title'];
            }
            if (isset($value['nodes'])) {
                $list['__extra_lang'][$lang]['nodes'] = $this->sanitizeHTML($value['nodes']);
            }
        }

        return $list;
    }

    private function sanitizeHTML(array $nodes)
    {
        return array_map(function ($node) {
            if (isset($node['label'])) {
                $node['label'] = $this->htmlPurifierService->cleanHTML($node['label']);
            }
            $node['children'] = $this->sanitizeHTML($node['children'] ?? []);

            return $node;
        }, $nodes);
    }
}

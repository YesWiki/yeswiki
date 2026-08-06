<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\HtmlPurifierService;

class ListManager
{
    protected $dbService;
    protected $htmlPurifierService;
    protected $pageManager;
    protected $params;
    protected $hibernationService;

    protected $cachedLists;
    protected AclService $aclService;

    public function __construct(
        DbService $dbService,
        HtmlPurifierService $htmlPurifierService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        AclService $aclService
    ) {
        $this->aclService = $aclService;
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->htmlPurifierService = $htmlPurifierService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;

        $this->cachedLists = [];
    }

    public function isList($id): bool
    {
        return $this->pageManager->isType((string)$id, PageType::LIST);
    }

    public function getOne($id, $parent = null): ?array
    {
        if (isset($this->cachedLists[$id]) && $parent === null) { // we cache all information, not just a level
            return $this->cachedLists[$id];
        }

        // Ensure a list exist with this ID
        if (!$this->isList($id)) {
            return null;
        }

        $page = $this->pageManager->getOne($id);
        if (empty($page)) {
            echo '<div class="alert alert-danger">List id not found: ' . $id . '</div>';

            return null;
        }
        $data = $this->loadBody($page['body'], $id);
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

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function loadBody(array $body, string $id): array
    {
        $data = $this->convertDataStructure($body);
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
        $result = [];
        foreach ($this->pageManager->tagsOfType(PageType::LIST) as $listId) {
            $result[$listId] = $this->getOne($listId, $parent);
        }

        return $result;
    }

    public function create($title, $nodes, $id = null)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $id = $id ?? generateWikiName('List ' . $title);
        $nodes = $nodes ?? [];
        $this->trimRecursiveInPlace($nodes);
        $body = [
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ];
        $this->pageManager->save($id, $body, '', false, null, PageType::LIST);
        $this->pageManager->cacheType($id, PageType::LIST);

        $data = $this->loadBody($body, $id);
        $this->cachedLists[$id] = $data;

        return $id;
    }

    public function update($id, $title, $nodes)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $nodes = $nodes ?? [];
        $this->trimRecursiveInPlace($nodes);
        $body = [
            'title' => $title,
            'nodes' => $this->sanitizeHMTL($nodes),
        ];
        $this->pageManager->save($id, $body);

        $data = $this->loadBody($body, $id);
        $this->cachedLists[$id] = $data;
    }

    public function delete($id)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!isset($id) || $id === '') {
            throw new \Exception('List ID not specified');
        }

        if (!$GLOBALS['yeswikiServices']->get(AclService::class)->isAdmin() && !$this->aclService->isOwner($id)) {
            throw new \Exception('Unauthorized');
        }

        // deleteOrphaned() already drops every triple keyed on the tag, and the type is a
        // column on the row it just deleted
        $this->pageManager->deleteOrphaned($id);

        unset($this->cachedLists[$id]);
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

<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\StringUtilService;

class ListManager
{
    protected DbService $dbService;
    protected HtmlPurifierService $htmlPurifierService;
    protected PageManager $pageManager;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;

    /** @var array<string, array<string, mixed>> */
    protected array $cachedLists = [];
    protected AclService $aclService;

    private WikiNameGenerator $wikiNames;

    public function __construct(
        DbService $dbService,
        HtmlPurifierService $htmlPurifierService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        AclService $aclService,
        WikiNameGenerator $wikiNames
    ) {
        $this->wikiNames = $wikiNames;
        $this->aclService = $aclService;
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->htmlPurifierService = $htmlPurifierService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;

        $this->cachedLists = [];
    }

    /** @param string $id */
    public function isList($id): bool
    {
        return $this->pageManager->isType($id, PageType::LIST);
    }

    /**
     * @param string      $id
     * @param string|null $parent 'root' to keep only the top level, otherwise the id of the node whose children to return
     *
     * @return array<string, mixed>|null
     */
    public function getOne($id, $parent = null): ?array
    {
        if (isset($this->cachedLists[$id]) && $parent === null) {
            return $this->cachedLists[$id];
        }

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
            $data['nodes'] = StringUtilService::searchNested($data['nodes'], 'id', $parent)[0]['children'] ?? null;
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

    /**
     * Reads either shape a list body can have on disk: the historic
     * `titre_liste`/`label` pair, or the current `title`/`nodes` tree.
     *
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    public function convertDataStructure($json)
    {
        if (isset($json['titre_liste'])) {
            $newJson = ['title' => $json['titre_liste'], 'nodes' => []];
            $labels = $json['label'] ?? [];
            foreach (is_array($labels) ? $labels : [] as $id => $label) {
                $newJson['nodes'][] = ['id' => $id, 'label' => $label];
            }

            return $newJson;
        }

        return $json;
    }

    /**
     * @param string|null $parent see getOne()
     *
     * @return array<string, array<string, mixed>|null> every list, keyed by id
     */
    public function getAll($parent = null): array
    {
        $result = [];
        foreach ($this->pageManager->tagsOfType(PageType::LIST) as $listId) {
            $result[$listId] = $this->getOne($listId, $parent);
        }

        return $result;
    }

    /**
     * @param string                                $title
     * @param array<int, array<string, mixed>>|null $nodes
     * @param string|null                           $id    defaults to a wiki name derived from $title
     *
     * @return string the id the list was saved under
     */
    public function create($title, $nodes, $id = null)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $id = $id ?? $this->wikiNames->generate('List ' . $title);
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

    /**
     * @param string                                $id
     * @param string                                $title
     * @param array<int, array<string, mixed>>|null $nodes
     */
    public function update($id, $title, $nodes): void
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

    /** @param string $id */
    public function delete($id): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if ($id === '') {
            throw new \Exception('List ID not specified');
        }

        if (!$this->aclService->isAdmin() && !$this->aclService->isOwner($id)) {
            throw new \Exception('Unauthorized');
        }

        $this->pageManager->deleteOrphaned($id);

        unset($this->cachedLists[$id]);
    }

    /**
     * @param string     $idList
     * @param int|string $key
     */
    public function getLabel($idList, $key): string
    {
        $list = $this->getOne($idList);
        if (empty($list)) {
            return '';
        }
        $val = StringUtilService::searchNested($list['nodes'], 'id', $key);
        $val = array_shift($val);

        return $val['label'] ?? '';
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeHMTL(array $nodes): array
    {
        return array_map(function ($node) {
            $node['label'] = $this->htmlPurifierService->cleanHTML($node['label']);
            $node['children'] = $this->sanitizeHMTL($node['children'] ?? []);

            return $node;
        }, $nodes);
    }

    /**
     * Recursively trims string values in a multidimensional array (in-place).
     *
     * @param array<array-key, mixed> $array    The input array (will be modified by reference)
     * @param string                  $charlist Optional. The characters to trim. Defaults to whitespace.
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

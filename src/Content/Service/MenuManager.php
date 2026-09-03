<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\PageType;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Menus: navigation held as Content rather than as configuration (ticket 64 / ADR-0028).
 *
 * A menu row's body is `{title, nodes}`, exactly two levels deep. Being a row is the whole point:
 * it is versioned, revertable and carries its own ACL, none of which a config array could be.
 * Configuration says *which* menu the chrome draws; it no longer says what is in one.
 */
class MenuManager
{
    /** What the two menus configuration names are forced to, so a contributor cannot rewrite the site's navigation. */
    public const CHROME_WRITE_ACL = '@admins';

    /** @var array<string, array{title: string, nodes: list<MenuNode>}|null> */
    private array $cache = [];

    public function __construct(
        private readonly PageManager $pageManager,
        private readonly AclService $aclService,
        private readonly HibernationService $hibernationService,
        private readonly WikiNameGenerator $wikiNames,
    ) {
    }

    public function isMenu(string $tag): bool
    {
        return $this->pageManager->isType($tag, PageType::MENU);
    }

    /**
     * @return array{title: string, nodes: list<MenuNode>}|null null when there is no such menu
     */
    public function getOne(string $tag): ?array
    {
        if (array_key_exists($tag, $this->cache)) {
            return $this->cache[$tag];
        }

        $menu = null;
        if ($tag !== '' && $this->isMenu($tag)) {
            $body = $this->pageManager->getOne($tag)['body'] ?? [];
            $menu = [
                'title' => is_string($body['title'] ?? null) ? $body['title'] : $tag,
                'nodes' => self::nodesOf($body),
            ];
        }

        return $this->cache[$tag] = $menu;
    }

    /**
     * Every menu the visitor may read, as `tag => title`.
     *
     * @return array<string, string>
     */
    public function readable(): array
    {
        $menus = [];
        foreach ($this->pageManager->tagsOfType(PageType::MENU) as $tag) {
            if (!$this->aclService->hasAccess('read', $tag)) {
                continue;
            }
            $menu = $this->getOne($tag);
            if ($menu !== null) {
                $menus[$tag] = $menu['title'];
            }
        }
        asort($menus);

        return $menus;
    }

    /**
     * The flat rows an editor posts, as the two-level tree they describe.
     *
     * The editor is a list, because that is what dragging rows around is; the nesting is one flag
     * per row saying "this one belongs under the last top-level one". A child with no parent above
     * it is a top-level entry, which is what un-indenting the first row means.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<MenuNode>
     */
    public static function nodesFromRows(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $row) {
            $node = MenuNode::fromArray([
                'id' => $row['id'] ?? '',
                'label' => (string)($row['label'] ?? ''),
                'link' => (string)($row['link'] ?? ''),
                'icon' => [
                    'source' => (string)($row['icon_source'] ?? MenuNode::ICON_SPRITE),
                    'value' => (string)($row['icon_value'] ?? $row['icon'] ?? ''),
                ],
            ], false);
            if ($node === null) {
                continue;
            }

            $parent = array_key_last($nodes);
            if (!empty($row['child']) && $parent !== null) {
                $nodes[$parent] = new MenuNode(
                    id: $nodes[$parent]->id,
                    label: $nodes[$parent]->label,
                    link: $nodes[$parent]->link,
                    iconSource: $nodes[$parent]->iconSource,
                    iconValue: $nodes[$parent]->iconValue,
                    children: [...$nodes[$parent]->children, $node],
                );
                continue;
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * A menu's tree flattened back into the rows an editor draws.
     *
     * @param list<MenuNode> $nodes
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsOf(array $nodes): array
    {
        $rows = [];
        foreach ($nodes as $node) {
            $rows[] = self::rowOf($node, false);
            foreach ($node->children as $child) {
                $rows[] = self::rowOf($child, true);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowOf(MenuNode $node, bool $child): array
    {
        return [
            'id' => $node->id,
            'label' => $node->label,
            'link' => $node->link,
            'icon_source' => $node->iconSource ?? MenuNode::ICON_SPRITE,
            'icon_value' => $node->iconValue,
            'child' => $child,
        ];
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<MenuNode>
     */
    public static function nodesOf(array $body): array
    {
        $nodes = [];
        foreach (is_array($body['nodes'] ?? null) ? $body['nodes'] : [] as $node) {
            $menuNode = is_array($node) ? MenuNode::fromArray($node) : null;
            if ($menuNode !== null) {
                $nodes[] = $menuNode;
            }
        }

        return $nodes;
    }

    /**
     * @param list<MenuNode> $nodes
     *
     * @return string the tag the menu was saved under
     */
    public function create(string $title, array $nodes, ?string $tag = null, bool $chrome = false): string
    {
        $this->refuseWhenHibernated();

        $tag ??= $this->wikiNames->generate('Menu ' . $title);
        $this->pageManager->save($tag, self::bodyOf($title, $nodes), '', $chrome, null, PageType::MENU);
        $this->pageManager->cacheType($tag, PageType::MENU);
        unset($this->cache[$tag]);

        if ($chrome) {
            $this->aclService->save($tag, 'write', self::CHROME_WRITE_ACL);
        }

        return $tag;
    }

    /**
     * @param list<MenuNode> $nodes
     */
    public function update(string $tag, string $title, array $nodes): void
    {
        $this->refuseWhenHibernated();

        if ($this->pageManager->save($tag, self::bodyOf($title, $nodes), '', false, null, PageType::MENU) !== 0) {
            throw new \Exception(_t('EDIT_NO_WRITE_ACCESS'));
        }
        unset($this->cache[$tag]);
    }

    public function delete(string $tag): void
    {
        $this->refuseWhenHibernated();

        if (!$this->isMenu($tag)) {
            return;
        }
        $this->pageManager->deleteOrphaned($tag);
        unset($this->cache[$tag]);
    }

    /**
     * @param list<MenuNode> $nodes
     *
     * @return array{title: string, nodes: list<array<string, mixed>>}
     */
    private static function bodyOf(string $title, array $nodes): array
    {
        return [
            'title' => trim($title),
            'nodes' => array_map(static fn (MenuNode $node): array => $node->toArray(), $nodes),
        ];
    }

    private function refuseWhenHibernated(): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
    }
}

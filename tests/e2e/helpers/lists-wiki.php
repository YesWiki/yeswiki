<?php

/** What the list specs arrange in the wiki under test: `nest <list> <parent> <child>...` files values under another. */

use YesWiki\Content\Service\ListManager;
use YesWiki\Core\YesWikiLoader;

require_once dirname(__DIR__, 3) . '/src/YesWikiLoader.php';

$lists = YesWikiLoader::getWiki(true)->services->get(ListManager::class);
[$listId, $parentId] = [(string)($argv[2] ?? ''), (string)($argv[3] ?? '')];
$childIds = array_slice($argv, 4);

if (($argv[1] ?? '') !== 'nest' || ($list = $lists->getOne($listId)) === null) {
    fwrite(STDERR, "usage: lists-wiki.php nest <list> <parent> <child>...\n");
    exit(1);
}

$nodes = array_map(
    static fn (array $node): array => ['id' => (string)$node['id'], 'label' => (string)$node['label'], 'children' => []],
    array_values($list['nodes'] ?? [])
);
$children = array_values(array_filter($nodes, static fn (array $node): bool => in_array($node['id'], $childIds, true)));
$nodes = array_values(array_filter($nodes, static fn (array $node): bool => !in_array($node['id'], $childIds, true)));
foreach ($nodes as &$node) {
    if ($node['id'] === $parentId) {
        $node['children'] = $children;
    }
}
unset($node);

$lists->update($listId, (string)($list['title'] ?? $listId), $nodes);

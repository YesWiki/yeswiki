<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 64: a value knows what colour and icon it is, so the list carries them.
 *
 * They used to be mapped inside every action call that drew the list -- `colormapping` and
 * `iconmapping` in the palette, `color="rouge=3"` and `icon="star=3"` in the body -- so the same
 * list styled in a card list and on a map was styled twice and could disagree with itself. Whatever
 * a webmaster authored is carried onto the list it described, and the calls lose the parameters.
 */
class ValueListsCarryTheirIconAndColour extends YesWikiMigration
{
    public function run(): void
    {
        $pageManager = $this->getService(PageManager::class);
        $lists = $this->getService(ListManager::class);

        $carried = 0;
        $stripped = [];

        foreach ($this->pagesHoldingAStyledCall() as $tag) {
            $page = $pageManager->getOne($tag, null, false, true);
            if ($page === null) {
                continue;
            }
            $body = $page['body'] ?? [];
            $content = PageBody::content($body);

            $rewritten = (string)preg_replace_callback(
                '/\{\{\s*(\w+)\b([^}]*)\}\}/',
                function (array $matches) use ($lists, &$carried): string {
                    $attributes = self::readAttributes($matches[2]);
                    $carried += $this->carryOntoLists($lists, $attributes);

                    return self::withoutTheMappings($matches[0]);
                },
                $content
            );

            if ($rewritten === $content) {
                continue;
            }
            $body[PageBody::CONTENT] = $rewritten;
            $pageManager->save($tag, $body, '', true);
            $stripped[] = $tag;
        }

        if ($stripped !== []) {
            $this->getService(SearchIndexer::class)->enqueue($stripped);
        }

        $this->say(
            'ticket 64: ' . $carried . ' value(s) were given the colour or icon their action calls '
            . 'used to map, and ' . count($stripped) . ' page(s) lost the mapping parameters.'
        );
    }

    /**
     * Write what a call mapped onto the list the field it named is backed by.
     *
     * @param array<string, string> $attributes
     *
     * @return int how many values were given something
     */
    private function carryOntoLists(ListManager $lists, array $attributes): int
    {
        $carried = 0;
        foreach ([['color', 'colorfield'], ['icon', 'iconfield']] as [$what, $fieldKey]) {
            $mapping = self::readMapping($attributes[$what] ?? '');
            $field = $attributes[$fieldKey] ?? '';
            if ($mapping === [] || $field === '') {
                continue;
            }

            $listId = $this->listBehind($attributes['id'] ?? '', $field);
            if ($listId === null) {
                continue;
            }

            $list = $lists->getOne($listId);
            if ($list === null) {
                continue;
            }

            $nodes = $list['nodes'] ?? [];
            $changed = self::applyToNodes($nodes, $mapping, $what, $carried);
            if ($changed) {
                $lists->update($listId, $list['title'] ?? $listId, $nodes);
            }
        }

        return $carried;
    }

    /**
     * @param array<array-key, mixed> $nodes
     * @param array<string, string>   $mapping value => what it should carry
     */
    private static function applyToNodes(array &$nodes, array $mapping, string $what, int &$carried): bool
    {
        $changed = false;
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }
            $id = (string)($node['id'] ?? '');
            if (isset($mapping[$id]) && !isset($node[$what])) {
                $node[$what] = $what === 'icon'
                    ? ['source' => 'sprite', 'value' => $mapping[$id]]
                    : $mapping[$id];
                $carried++;
                $changed = true;
            }
            if (is_array($node['children'] ?? null) && self::applyToNodes($node['children'], $mapping, $what, $carried)) {
                $changed = true;
            }
        }

        return $changed;
    }

    /** The list a form's field takes its values from, which is where its colours belong. */
    private function listBehind(string $formId, string $field): ?string
    {
        if ($formId === '') {
            return null;
        }
        $form = $this->getService(FormManager::class)->getOne($formId);
        foreach ($form['prepared'] ?? [] as $prepared) {
            if ($prepared instanceof EnumField && $prepared->getPropertyName() === $field) {
                $list = $prepared->getLinkedObjectName();

                return $list === '' ? null : $list;
            }
        }

        return null;
    }

    /**
     * `icon="star=3, heart=4"` as `['3' => 'star', '4' => 'heart']`: the parameter was written
     * the other way round, which is one of the reasons nobody could read it.
     *
     * @return array<string, string>
     */
    private static function readMapping(string $written): array
    {
        $mapping = [];
        foreach (explode(',', $written) as $pair) {
            $parts = array_map('trim', explode('=', $pair, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $mapping[$parts[1]] = $parts[0];
            }
        }

        return $mapping;
    }

    /** The same call, with the two mapping parameters gone and everything else untouched. */
    private static function withoutTheMappings(string $call): string
    {
        return (string)preg_replace('/\s(?:color|icon)s?="[^"]*=[^"]*"/', '', $call);
    }

    /**
     * @return array<string, string>
     */
    private static function readAttributes(string $parameters): array
    {
        preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"/', $parameters, $found, PREG_SET_ORDER);

        $attributes = [];
        foreach ($found as $match) {
            $attributes[strtolower($match[1])] = $match[2];
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function pagesHoldingAStyledCall(): array
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $bodyAsText = $dbService->jsonAsText('body');

        $rows = $dbService->loadAll(
            "SELECT tag FROM {$pages} WHERE latest = 'Y' AND ({$bodyAsText} LIKE ? OR {$bodyAsText} LIKE ?)",
            ['%colorfield=%', '%iconfield=%']
        );

        return array_map(static fn (array $row): string => (string)$row['tag'], $rows);
    }
}

<?php

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\WikiNameGenerator;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 64: navigation becomes Content, and configuration names it.
 *
 * Two conversions, and both of them lose a way of writing a menu on purpose. The chrome's entries
 * leave `layout_navbar`/`layout_quick_menu` for rows of their own, and every `{{nav links=...}}` in
 * a page body becomes a menu plus `{{nav menu="..."}}`. Identical calls -- compared on what they
 * say and where they lead -- collapse into one menu, so core's twenty-one calls become about five
 * and fixing one of them fixes every page that carried it.
 */
class MenusBecomeContent extends YesWikiMigration
{
    /** The vocabulary of the call this rewrites, as this migration's own literals. */
    private const PARAMETERS_KEPT = ['class'];

    public function run(): void
    {
        $menus = $this->getService(MenuManager::class);
        $layout = $this->getService(LayoutService::class);

        $chrome = $this->convertChromeMenus($menus, $layout);
        [$created, $rewritten] = $this->convertNavCalls($menus);

        $this->say(
            'ticket 64: navigation is Content now. ' . $chrome . ' '
            . $created . ' menu(s) made from ' . $rewritten . ' {{nav}} call(s) in page bodies, '
            . 'which name a menu instead of carrying one.'
        );
    }

    /**
     * `layout_navbar` and `layout_quick_menu` hold a tag afterwards, and the rows they name are
     * forced to `write = @admins`: a contributor may own a section's tab bar, not the site's own
     * navigation (ADR-0028).
     */
    private function convertChromeMenus(MenuManager $menus, LayoutService $layout): string
    {
        $config = $this->getService(ConfigurationService::class)
            ->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $done = [];
        foreach ([
            [LayoutService::NAVBAR, 'MenuNavigation', _t('LAYOUT_NAVBAR_MENU_TITLE')],
            [LayoutService::QUICK_MENU, 'MenuAccesRapide', _t('LAYOUT_QUICK_MENU_TITLE')],
        ] as [$key, $tag, $title]) {
            $held = $config[$key] ?? null;
            if (!is_array($held)) {
                continue;
            }

            $menus->create($title, self::nodesOfConfiguredEntries($held), $tag, true);
            $config[$key] = $tag;
            $done[] = $tag;
        }

        if ($done === []) {
            return 'The chrome menus were already Content.';
        }

        foreach (LayoutService::FLAG_DEFAULTS as $flag => $value) {
            if (!isset($config[$flag])) {
                $config[$flag] = $value;
            }
        }
        $config->write();

        return 'The navbar and the quick access bar are ' . implode(' and ', $done) . '.';
    }

    /**
     * The entries a config array held, as menu nodes.
     *
     * Both shapes at once: the navbar's `children`, and the quick menu's flat `icon`/`label`/`link`.
     *
     * @param array<array-key, mixed> $entries
     *
     * @return list<MenuNode>
     */
    private static function nodesOfConfiguredEntries(array $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rows[] = self::rowOfConfiguredEntry($entry, false);
            foreach (is_array($entry['children'] ?? null) ? $entry['children'] : [] as $child) {
                if (is_array($child)) {
                    $rows[] = self::rowOfConfiguredEntry($child, true);
                }
            }
        }

        return MenuManager::nodesFromRows($rows);
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function rowOfConfiguredEntry(array $entry, bool $child): array
    {
        return [
            'label' => (string)($entry['label'] ?? ''),
            'link' => (string)($entry['link'] ?? ''),
            'icon_source' => MenuNode::ICON_SPRITE,
            'icon_value' => (string)($entry['icon'] ?? ''),
            'child' => $child,
        ];
    }

    /**
     * @return array{0: int, 1: int} how many menus were made, and how many calls now name one
     */
    private function convertNavCalls(MenuManager $menus): array
    {
        $pageManager = $this->getService(PageManager::class);
        $created = [];
        $rewrittenCalls = 0;
        $touched = [];

        foreach ($this->pagesHoldingANavCall() as $tag) {
            $page = $pageManager->getOne($tag, null, false, true);
            if ($page === null) {
                continue;
            }
            $body = $page['body'] ?? [];
            $content = PageBody::content($body);

            $rewrittenContent = (string)preg_replace_callback(
                '/\{\{\s*nav\b([^}]*)\}\}/i',
                function (array $matches) use ($menus, &$created, &$rewrittenCalls): string {
                    $attributes = self::readAttributes($matches[1]);
                    $entries = self::entriesOf($attributes);
                    if ($entries === []) {
                        return $matches[0];
                    }
                    $rewrittenCalls++;

                    return $this->callNaming(self::menuFor($menus, $entries, $created), $attributes);
                },
                $content
            );

            if ($rewrittenContent === $content) {
                continue;
            }
            $body[PageBody::CONTENT] = $rewrittenContent;
            $pageManager->save($tag, $body, '', true);
            $touched[] = $tag;
        }

        if ($touched !== []) {
            $this->getService(SearchIndexer::class)->enqueue($touched);
        }

        return [count($created), $rewrittenCalls];
    }

    /**
     * One menu per distinct set of entries, made the first time that set is seen.
     *
     * @param array<string, string>                                  $created signature => the tag its menu took
     * @param-out array<string, string>                              $created
     * @param list<array{label: string, link: string, icon: string}> $entries
     */
    private static function menuFor(MenuManager $menus, array $entries, array &$created): string
    {
        $signature = (string)json_encode($entries);
        if (isset($created[$signature])) {
            return $created[$signature];
        }

        $rows = array_map(static fn (array $entry): array => [
            'label' => $entry['label'],
            'link' => $entry['link'],
            'icon_source' => MenuNode::ICON_SPRITE,
            'icon_value' => $entry['icon'],
            'child' => false,
        ], $entries);

        $title = self::titleOf($entries);

        return $created[$signature] = $menus->create($title, MenuManager::nodesFromRows($rows));
    }

    /**
     * A menu named from what it says, because nothing else knows what it is for.
     *
     * @param list<array{label: string, link: string, icon: string}> $entries
     */
    private static function titleOf(array $entries): string
    {
        $labels = array_values(array_filter(array_column($entries, 'label')));

        return $labels === [] ? 'Menu' : implode(' / ', array_slice($labels, 0, 2));
    }

    /**
     * The call that replaces the one this migration read, keeping what still means something.
     *
     * @param array<string, string> $attributes
     */
    private function callNaming(string $menuTag, array $attributes): string
    {
        $call = '{{nav menu="' . $menuTag . '"';
        foreach (self::PARAMETERS_KEPT as $kept) {
            if (($attributes[$kept] ?? '') !== '') {
                $call .= ' ' . $kept . '="' . $attributes[$kept] . '"';
            }
        }
        foreach ($attributes as $name => $value) {
            if (str_starts_with($name, 'data-') && $value !== '') {
                $call .= ' ' . $name . '="' . $value . '"';
            }
        }
        // `icons` was a real parameter that the palette never offered; it becomes each node's own.
        if (($attributes['icons'] ?? '') !== '') {
            $call .= ' showicons="true"';
        }

        return $call . '}}';
    }

    /**
     * The entries a `{{nav}}` call carried, as the parallel lists it kept them in.
     *
     * @param array<string, string> $attributes
     *
     * @return list<array{label: string, link: string, icon: string}>
     */
    private static function entriesOf(array $attributes): array
    {
        $links = self::splitList($attributes['links'] ?? '');
        $titles = self::splitList($attributes['titles'] ?? '');
        $icons = self::splitList($attributes['icons'] ?? '');

        $entries = [];
        foreach ($titles as $index => $title) {
            $link = $links[$index] ?? '';
            if ($title === '' && $link === '') {
                continue;
            }
            $entries[] = ['label' => $title, 'link' => $link, 'icon' => $icons[$index] ?? ''];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        return trim($value) === '' ? [] : array_map('trim', explode(',', $value));
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
     * @return list<string> the tags of every latest page whose body mentions a nav call
     */
    private function pagesHoldingANavCall(): array
    {
        $dbService = $this->dbService;
        $pages = trim($dbService->prefixTable('pages'));
        $bodyAsText = $dbService->jsonAsText('body');

        $rows = $dbService->loadAll(
            "SELECT tag FROM {$pages} WHERE latest = 'Y' AND {$bodyAsText} LIKE ?",
            ['%{{nav%']
        );

        return array_map(static fn (array $row): string => (string)$row['tag'], $rows);
    }
}

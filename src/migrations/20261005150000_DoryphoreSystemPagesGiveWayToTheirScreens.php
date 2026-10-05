<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Entity\RetiredPages;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Search\Service\SearchIndexer;

/** GererSite, TableauDeBord, BazaR, MesContenus and the other Doryphore system pages go, and every link to them reaches the /admin, /dashboard or /user screen instead. */
class DoryphoreSystemPagesGiveWayToTheirScreens extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = trim($db->prefixTable('pages'));

        [$rewritten, $retiredOnlyMenus] = $this->repointLinks($db, $pages);
        $deleted = $this->deleteRetiredPages($db, $pages);
        $menus = $this->deleteUnusedMenus($db, $pages, $retiredOnlyMenus);

        $this->getService(SearchIndexer::class)->enqueue(array_values(array_unique([...$rewritten, ...$deleted, ...$menus])));
        $rewritten = array_values(array_diff($rewritten, $menus));

        $this->say(
            count($deleted) . ' Doryphore system page(s) removed' . ($deleted === [] ? '' : ' (' . implode(', ', $deleted) . ')')
            . ', links repointed at the screens doing their job on ' . count($rewritten) . ' page(s) or menu(s)'
            . ($rewritten === [] ? '' : ' (' . implode(', ', $rewritten) . ')')
            . ($menus === [] ? '.' : ', and ' . count($menus) . ' menu(s) that only led to them removed (' . implode(', ', $menus) . ').')
        );
    }

    /**
     * Rewrite the links in every revision, and note the menus whose every link was a retired page.
     *
     * @param list<string>|null $onlyTags restrict the sweep, which is what a test wants
     *
     * @return array{0: list<string>, 1: array<string, true>}
     */
    public function repointLinks(DbService $db, string $pages, ?array $onlyTags = null): array
    {
        $tags = RetiredPages::tags();
        $text = 'LOWER(' . $db->jsonAsText('body') . ')';
        $where = implode(' OR ', array_fill(0, count($tags), "{$text} LIKE ?"));
        [$only, $onlyParams] = $this->restrict($onlyTags);
        $rows = $db->loadAll(
            "SELECT id, tag, type, latest, body FROM {$pages} WHERE ({$where}){$only}",
            [...array_map(fn (string $tag): string => '%' . $tag . '%', $tags), ...$onlyParams]
        );

        $changed = [];
        $retiredOnlyMenus = [];
        foreach ($rows as $row) {
            if (RetiredPages::screenFor((string)$row['tag']) !== null) {
                continue;
            }
            $body = PageBody::decode((string)$row['body']);
            $links = ['all' => 0, 'retired' => 0];
            $new = $this->repoint($body, $links);
            if ($row['type'] === PageType::MENU && $row['latest'] === 'Y' && $links['all'] > 0 && $links['all'] === $links['retired']) {
                $retiredOnlyMenus[(string)$row['tag']] = true;
            }
            if ($new === $body) {
                continue;
            }
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($new), (string)$row['id']]);
            $changed[(string)$row['tag']] = true;
        }

        return [array_keys($changed), $retiredOnlyMenus];
    }

    /** Repoint one stored link, a `link="…"` parameter or a markdown link; anything else is returned as it was. */
    public static function repointText(string $text): string
    {
        $tags = implode('|', array_map('preg_quote', RetiredPages::tags()));
        $text = (string)preg_replace_callback(
            '/(\blink=")(' . $tags . ')(")/i',
            fn (array $m): string => $m[1] . RetiredPages::linkFor($m[2]) . $m[3],
            $text
        );

        return (string)preg_replace_callback(
            '/\]\(\s*(' . $tags . ')(\s+"[^"]*")?\s*\)/i',
            fn (array $m): string => '](' . RetiredPages::linkFor($m[1]) . ($m[2] ?? '') . ')',
            $text
        );
    }

    /**
     * @param array<array-key, mixed>       $value
     * @param array{all: int, retired: int} $links
     *
     * @return array<array-key, mixed>
     */
    private function repoint(array $value, array &$links): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->repoint($item, $links);
            } elseif (is_string($item) && $key === 'link') {
                if (trim($item) !== '') {
                    $links['all']++;
                }
                $screen = RetiredPages::linkFor($item);
                if ($screen !== null) {
                    $links['retired']++;
                    $value[$key] = $screen;
                }
            } elseif (is_string($item)) {
                $value[$key] = self::repointText($item);
            }
        }

        return $value;
    }

    /**
     * @param list<string>|null $onlyTags restrict the sweep, which is what a test wants
     *
     * @return list<string>
     */
    public function deleteRetiredPages(DbService $db, string $pages, ?array $onlyTags = null): array
    {
        $tags = RetiredPages::tags();
        [$only, $onlyParams] = $this->restrict($onlyTags);
        $rows = $db->loadAll(
            "SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) IN (" . implode(', ', array_fill(0, count($tags), '?')) . ") AND type = ?{$only}",
            [...$tags, PageType::PAGE, ...$onlyParams]
        );

        $deleted = [];
        $pageManager = $this->getService(PageManager::class);
        foreach ($rows as $row) {
            $pageManager->deleteOrphaned((string)$row['tag']);
            $deleted[] = (string)$row['tag'];
        }

        return $deleted;
    }

    /**
     * Remove the menus that only led to retired pages, once nothing draws them any more.
     *
     * @param array<string, true> $candidates
     *
     * @return list<string>
     */
    private function deleteUnusedMenus(DbService $db, string $pages, array $candidates): array
    {
        $layout = $this->getService(LayoutService::class);
        $chrome = array_map('mb_strtolower', [$layout->navbar(), $layout->quickMenu()]);
        $text = 'LOWER(' . $db->jsonAsText('body') . ')';

        $deleted = [];
        foreach (array_keys($candidates) as $menu) {
            if (in_array(mb_strtolower($menu), $chrome, true)) {
                continue;
            }
            $used = $db->loadSingle(
                "SELECT tag FROM {$pages} WHERE latest = 'Y' AND tag <> ? AND {$text} LIKE ? LIMIT 1",
                [$menu, '%' . mb_strtolower($menu) . '%']
            );
            if ($used !== null) {
                continue;
            }
            $this->getService(PageManager::class)->deleteOrphaned($menu);
            $deleted[] = $menu;
        }

        return $deleted;
    }

    /**
     * @param list<string>|null $onlyTags
     *
     * @return array{0: string, 1: list<string>} the clause narrowing a sweep to those tags, and its values
     */
    private function restrict(?array $onlyTags): array
    {
        if ($onlyTags === null) {
            return ['', []];
        }
        $onlyTags = $onlyTags ?: [''];

        return [' AND tag IN (' . implode(', ', array_fill(0, count($onlyTags), '?')) . ')', $onlyTags];
    }
}

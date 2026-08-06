<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\LayoutService;

/**
 * Ticket 30: `PageTitre`, `PageMenuHaut` and `PageRapideHaut` become `layout_*` config.
 *
 * The squelette no longer includes those three pages, so a wiki upgrading into this release
 * would come back with no title, no menu and no quick-access buttons. This reads them one
 * last time and writes what it recognises into configuration.
 *
 * ## It is lossy, and that is the whole difficulty
 *
 * What those pages hold is arbitrary wiki content. A markdown list of links and a `{{nav}}`
 * call parse cleanly into entries; a hand-written table, an `{{include}}`, raw HTML or an
 * action do not. **Nothing is deleted**: the three pages are left exactly as they are, with
 * their revisions, and every line this could not turn into an entry is quoted verbatim in
 * the administrative log so the webmaster can see what did not come across and where it
 * still lives. The alternative -- dropping silently and announcing it in a changelog -- is
 * how a wiki loses a menu nobody notices for a month.
 *
 * ## The retired per-page override
 *
 * A page could name a *different* `PageTitre` / `PageMenuHaut` / `PageRapideHaut` for itself
 * (`ThemeManager::SPECIAL_METADATA`). Once those three are global configuration that has no
 * meaning, so the override is retired for them and kept for the three items that are still
 * pages. Any page that was using it is named in the log -- it is the one case where content
 * genuinely loses a behaviour, and it must not collapse quietly.
 *
 * Idempotent: it does nothing once `layout_navbar` exists in the configuration.
 */
class LayoutBecomesConfiguration extends YesWikiMigration
{
    /** Which page fed which part of the layout. */
    private const SOURCES = [
        'PageTitre' => 'the title and logo',
        'PageMenuHaut' => 'the navbar',
        'PageRapideHaut' => 'the quick-access buttons',
    ];

    public function run()
    {
        $layout = $this->getService(LayoutService::class);
        $pageManager = $this->getService(PageManager::class);
        $log = $this->getService(AdministrativeLogService::class);

        // already configured -- a farm instance provisioned with layout_* keys, or this
        // migration run twice. Re-reading the pages would overwrite what someone has since
        // edited on /admin/layout, which is worse than doing nothing.
        if ($layout->navbar() !== [] || $layout->quickMenu() !== [] || $layout->hasOwnTitle()) {
            return;
        }

        $bodies = [];
        foreach (array_keys(self::SOURCES) as $tag) {
            $page = $pageManager->getOne($tag, null, true, true);
            $bodies[$tag] = $page === null ? '' : PageBody::content($page['body'] ?? []);
        }

        if (implode('', $bodies) === '') {
            // nothing to carry across: a fresh install, or a wiki that deleted all three
            return;
        }

        $leftovers = [];

        [$title, $logo, $titleRest] = $this->readTitle($bodies['PageTitre']);
        $leftovers['PageTitre'] = $titleRest;

        [$navbar, $navbarRest] = $this->readNavbar($bodies['PageMenuHaut']);
        $leftovers['PageMenuHaut'] = $navbarRest;

        [$quickMenu, $account, $quickRest] = $this->readQuickMenu($bodies['PageRapideHaut']);
        $leftovers['PageRapideHaut'] = $quickRest;

        $layout->save(
            ['title' => $title, 'logo' => $logo, 'brand' => $logo === '' ? 'text' : 'logo-text', 'account' => $account],
            $navbar,
            $quickMenu
        );

        $log->log(
            'migration',
            'ticket 30: the wiki chrome moved from PageTitre/PageMenuHaut/PageRapideHaut into the '
            . 'configuration, and is edited on /admin/layout now. Carried across: '
            . count($navbar) . ' navbar entries and ' . count($quickMenu) . ' quick-access buttons'
            . ($title === '' ? ', the wiki name as the title' : ", the title '{$title}'")
            . ($logo === '' ? '' : ", the logo '{$logo}'")
            . '. The three pages were left in place, untouched.'
        );

        $this->reportLeftovers($log, $leftovers);
        $this->reportRetiredOverrides($log);
    }

    /**
     * `PageTitre`: a title, and possibly a logo.
     *
     * The seeded body is `{{configuration param="yeswiki_name"}}`, which is the fallback
     * written by hand -- recognised and turned back into "no title of its own", so an
     * untouched wiki gets an empty field rather than a literal action tag as its name.
     *
     * @return array{0: string, 1: string, 2: list<string>} title, logo, unparsed lines
     */
    private function readTitle(string $body): array
    {
        $logo = '';
        $rest = [];
        $title = '';

        foreach ($this->meaningfulLines($body) as $line) {
            // an image, however it was written: markdown, an <img>, or {{attach file="…"}}
            if ($logo === '' && preg_match('/!\[[^\]]*\]\(([^)\s]+)/', $line, $found) === 1) {
                $logo = $found[1];
                continue;
            }
            if ($logo === '' && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $line, $found) === 1) {
                $logo = $found[1];
                continue;
            }
            if ($logo === '' && preg_match('/\{\{\s*attach\b[^}]*\bfile="([^"]+)"/i', $line, $found) === 1) {
                $logo = 'files/' . $found[1];
                continue;
            }
            // the seeded title: the wiki's own name, which is what an empty field means
            if (preg_match('/\{\{\s*configuration\b[^}]*\byeswiki_name\b/i', $line) === 1) {
                continue;
            }
            if ($title === '' && !str_contains($line, '{{') && !str_contains($line, '<')) {
                $title = trim($line, "# \t");
                continue;
            }
            $rest[] = $line;
        }

        return [$title, $logo, $rest];
    }

    /**
     * `PageMenuHaut`: a markdown list, one level of nesting, and/or a `{{nav}}` call.
     *
     * Indentation decides nesting rather than a fixed number of spaces: wikis indent their
     * sub-items with two, three or four, and the shallowest bullet in the page is the top
     * level whatever it is.
     *
     * @return array{0: list<array{label: string, link: string, children: list<array{label: string, link: string}>}>, 1: list<string>}
     */
    private function readNavbar(string $body): array
    {
        $lines = $this->meaningfulLines($body);

        // the shallowest bullet is the top level, so a wholly-indented list is not read as
        // one long chain of children
        $topIndent = null;
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)[-*]\s+\S/', $line, $found) === 1) {
                $indent = strlen($found[1]);
                $topIndent = $topIndent === null ? $indent : min($topIndent, $indent);
            }
        }

        $entries = [];
        $rest = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)[-*]\s+(.+)$/', $line, $found) === 1) {
                $entry = $this->readLink(trim($found[2]));
                $parent = array_key_last($entries);
                if (strlen($found[1]) > (int)$topIndent && $parent !== null) {
                    $entries[$parent]['children'][] = $entry;
                } else {
                    $entries[] = ['label' => $entry['label'], 'link' => $entry['link'], 'children' => []];
                }
                continue;
            }

            if (preg_match('/\{\{\s*nav\b([^}]*)\}\}/i', $line, $found) === 1) {
                foreach ($this->readNav($found[1]) as $entry) {
                    $entries[] = ['label' => $entry['label'], 'link' => $entry['link'], 'children' => []];
                }
                continue;
            }

            $rest[] = $line;
        }

        return [$entries, $rest];
    }

    /**
     * `PageRapideHaut`: `{{button}}` calls, and whether `{{login}}` closed them.
     *
     * @return array{0: list<array{icon: string, label: string, link: string}>, 1: bool, 2: list<string>}
     */
    private function readQuickMenu(string $body): array
    {
        $entries = [];
        $rest = [];
        // `{{login}}` is the account button, which is a checkbox on the screen now. A page
        // that never had one gets none -- but a page that had nothing at all is a page this
        // migration was not given, and hasAccountButton() defaults to true for those.
        $account = $body === '';

        foreach ($this->meaningfulLines($body) as $line) {
            if (preg_match('/\{\{\s*login\b/i', $line) === 1) {
                $account = true;
                continue;
            }
            if (preg_match('/\{\{\s*button\b([^}]*)\}\}/i', $line, $found) === 1) {
                $attributes = $this->readAttributes($found[1]);
                $entries[] = [
                    'icon' => $attributes['icon'] ?? '',
                    'label' => $attributes['text'] ?? ($attributes['title'] ?? ''),
                    'link' => $attributes['link'] ?? ($attributes['url'] ?? ''),
                ];
                continue;
            }
            $rest[] = $line;
        }

        return [$entries, $account, $rest];
    }

    /**
     * `{{nav links="A, B" titles="Un, Deux"}}` as entries.
     *
     * @return list<array{label: string, link: string}>
     */
    private function readNav(string $parameters): array
    {
        $attributes = $this->readAttributes($parameters);
        $links = array_map('trim', explode(',', $attributes['links'] ?? ''));
        $titles = array_map('trim', explode(',', $attributes['titles'] ?? ''));

        $entries = [];
        foreach ($links as $index => $link) {
            if ($link === '') {
                continue;
            }
            $entries[] = ['label' => $titles[$index] ?? $link, 'link' => $link];
        }

        return $entries;
    }

    /**
     * One list item as a label and a link.
     *
     * `[Label](Page)`, `[Label](Page "tooltip")` and `[Label](Page){.newtab}` all give the
     * same entry: the tooltip and the attribute list are markdown extras, not the address.
     * An item with no link is a label -- which is exactly the dropdown-parent case.
     *
     * @return array{label: string, link: string}
     */
    private function readLink(string $item): array
    {
        if (preg_match('/\[([^\]]*)\]\(\s*([^)\s"]+)(?:\s+"[^"]*")?\s*\)/', $item, $found) === 1) {
            return ['label' => trim($found[1]), 'link' => trim($found[2])];
        }

        // not a link: strip the emphasis and attribute syntax a bare label may carry
        return ['label' => trim((string)preg_replace('/\{[^}]*\}|[*_`]/', '', $item)), 'link' => ''];
    }

    /**
     * The `name="value"` pairs of an action call.
     *
     * @return array<string, string>
     */
    private function readAttributes(string $parameters): array
    {
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $parameters, $found, PREG_SET_ORDER);

        $attributes = [];
        foreach ($found as $pair) {
            $attributes[strtolower($pair[1])] = $pair[2];
        }

        return $attributes;
    }

    /**
     * The lines worth looking at: no blanks, and no `{# … #}` comments.
     *
     * The seeded `PageTitre` and `PageMenuHaut` both end in a comment block explaining what
     * the page is for -- prose about a page that no longer exists is not a leftover anyone
     * needs reported.
     *
     * @return list<string>
     */
    private function meaningfulLines(string $body): array
    {
        $body = (string)preg_replace('/\{#.*?#\}/s', '', $body);
        // an unterminated comment too: the seeded PageHeader has one, and a truncated
        // `{#` would otherwise turn the rest of the page into leftovers
        $body = (string)preg_replace('/\{#.*$/s', '', $body);

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            if (trim($line) !== '') {
                $lines[] = rtrim($line);
            }
        }

        return $lines;
    }

    /**
     * What could not be turned into a field, quoted.
     *
     * @param array<string, list<string>> $leftovers
     */
    private function reportLeftovers(AdministrativeLogService $log, array $leftovers): void
    {
        foreach ($leftovers as $tag => $lines) {
            if ($lines === []) {
                continue;
            }
            $log->log(
                'migration',
                "these lines of '{$tag}' could not be turned into " . self::SOURCES[$tag]
                . ' and were NOT carried into the configuration (ticket 30). They are still on the page,'
                . ' which is still there: ' . implode(' / ', array_map('trim', $lines))
            );
        }
    }

    /**
     * Pages that named a different title bar, top menu or quick menu for themselves.
     *
     * Read straight from the metadata column rather than through PageManager: this asks a
     * question about every page at once, and the answer is a handful of tags.
     */
    private function reportRetiredOverrides(AdministrativeLogService $log): void
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $metadata = $db->quoteIdentifier('metadata');

        $rows = $db->loadAll(
            "SELECT tag, {$metadata} FROM {$pages} WHERE latest = 'Y'"
            . " AND ({$metadata} LIKE '%PageTitre%' OR {$metadata} LIKE '%PageMenuHaut%'"
            . " OR {$metadata} LIKE '%PageRapideHaut%')"
        );

        $affected = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['metadata'], true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach (array_keys(self::SOURCES) as $role) {
                if (!empty($decoded[$role]) && $decoded[$role] !== $role) {
                    $affected[] = "{$row['tag']} ({$role} = {$decoded[$role]})";
                }
            }
        }

        if ($affected !== []) {
            $log->log(
                'migration',
                'these pages named their own title bar / top menu / quick menu, which is no longer '
                . 'possible now that those three are wiki-wide configuration (ticket 30). They wear the '
                . "wiki's layout from now on; the banner, side menu and footer can still be overridden "
                . 'per page: ' . implode(', ', $affected)
            );
        }
    }
}

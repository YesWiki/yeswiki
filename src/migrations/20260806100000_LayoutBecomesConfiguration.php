<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\LegacyAttachments;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LayoutService;

/** Ticket 30: `PageTitre`, `PageMenuHaut` and `PageRapideHaut` become `layout_*` config. */
class LayoutBecomesConfiguration extends YesWikiMigration
{
    /** Which page fed which part of the layout. */
    private const SOURCES = [
        'PageTitre' => 'the title and logo',
        'PageMenuHaut' => 'the navbar',
        'PageRapideHaut' => 'the quick-access buttons',
    ];

    private const DORYPHORE_LOGO_MAX_HEIGHT = 46;

    private const IMAGES = [
        '/!\[[^\]]*\]\(([^)\s]+)[^)]*\)/' => 'markdown',
        '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i' => 'html',
        '/\{\{\s*attach\b[^}]*\bfile="([^"]+)"[^}]*\}\}/i' => 'attach',
    ];

    public function run()
    {
        $layout = $this->getService(LayoutService::class);
        $pageManager = $this->getService(PageManager::class);

        if ($layout->navbar() !== '' || $layout->quickMenu() !== '' || $layout->hasOwnTitle()) {
            return;
        }

        $bodies = [];
        foreach (array_keys(self::SOURCES) as $tag) {
            $page = $pageManager->getOne($tag, null, true, true);
            $bodies[$tag] = $page === null ? '' : PageBody::content($page['body'] ?? []);
        }

        if (implode('', $bodies) === '') {
            return;
        }

        $leftovers = [];

        [$title, $logo, $titleRest] = $this->readTitle($bodies['PageTitre']);
        $leftovers['PageTitre'] = $titleRest;

        [$navbar, $navbarRest] = $this->readNavbar($bodies['PageMenuHaut']);
        $leftovers['PageMenuHaut'] = $navbarRest;

        [$quickMenu, $account, $quickRest, $quickDropdown] = $this->readQuickMenu($bodies['PageRapideHaut']);
        $leftovers['PageRapideHaut'] = $quickRest;

        $fileTag = $this->fileTagOf($logo);
        if ($fileTag === null) {
            $logo = $this->legacyUpload($logo) ?? $logo;
        }
        $logoHeight = $this->logoHeight($logo, $fileTag);
        if ($fileTag !== null) {
            $logo = $this->getService(UrlFormatter::class)->href('', 'api/files/' . rawurlencode($fileTag) . '/download');
        }
        $brand = ['title' => $title, 'logo' => $logo, 'brand' => $logo === '' ? 'text' : ($title === '' ? 'logo' : 'logo-text'), 'account' => $account];
        if ($logoHeight !== null) {
            $brand['height'] = self::navbarHeightFor($logoHeight);
        }
        if ($quickDropdown) {
            $brand['quickMenuFlags'] = ['showdropdown' => true] + $layout->quickMenuFlags();
        }

        $layout->save(
            $brand,
            $navbar,
            $quickMenu
        );

        $this->say(
            'ticket 30: the wiki chrome moved from PageTitre/PageMenuHaut/PageRapideHaut into the '
            . 'configuration, and is edited on /admin/layout now. Carried across: '
            . count($navbar) . ' navbar entries and ' . count($quickMenu) . ' quick-access buttons'
            . ($title === '' ? ', the wiki name as the title' : ", the title '{$title}'")
            . ($logo === '' ? '' : ", the logo '{$logo}'")
            . (isset($brand['height']) ? ", a {$brand['height']}px navbar so the logo keeps the height it had" : '')
            . '. The three pages were left in place, untouched.'
        );

        // What this run could not carry is part of what it did, so it is said here. What pages
        // still override is a claim about the present, so it is Render's check (ticket 53).
        $this->sayWhatWasLeftBehind($leftovers);
        $this->reportCheck('pages-override-retired-chrome');
    }

    /**
     * `PageTitre`: a title, and possibly a logo, the text beside the image being the title.
     *
     * @return array{0: string, 1: string, 2: list<string>} title, logo, unparsed lines
     */
    private function readTitle(string $body): array
    {
        $logo = '';
        $rest = [];
        $title = '';

        foreach ($this->meaningfulLines($body) as $line) {
            if (preg_match('/\{\{\s*configuration\b[^}]*\byeswiki_name\b/i', $line) === 1) {
                continue;
            }

            $image = false;
            foreach (self::IMAGES as $pattern => $source) {
                if (preg_match($pattern, $line, $found) === 1) {
                    $image = true;
                    if ($logo === '') {
                        $logo = $source === 'attach' ? 'files/' . $found[1] : $found[1];
                    }
                    $line = (string)preg_replace($pattern, '', $line);
                }
            }

            $text = $this->plainText($line);
            if ($text !== '' && !str_contains($line, '{{')) {
                if ($title === '') {
                    $title = $text;
                    continue;
                }
            }
            if ($image || $text === '' && !str_contains($line, '{{')) {
                continue;
            }
            $rest[] = $line;
        }

        return [$title, $logo, $rest];
    }

    /** The words a line shows once its markup is gone: `""YesWiki"" Pro` and `WIKI-PROG""<br />""NA` read as text. */
    private function plainText(string $line): string
    {
        $line = str_replace('""', '', $line);
        $line = (string)preg_replace('~<br\s*/?>~i', ' ', $line);
        $line = strip_tags($line);
        $line = trim($line, "# \t");

        return trim((string)preg_replace('/\s+/', ' ', $line));
    }

    /** The height a Doryphore logo of this natural height was drawn at, margot capping it at 2.9rem, plus the 12px Ectoplasme leaves around it. */
    private static function navbarHeightFor(int $naturalHeight): int
    {
        $drawn = min($naturalHeight, self::DORYPHORE_LOGO_MAX_HEIGHT);

        return max(LayoutService::NAVBAR_HEIGHT_DEFAULT, min(LayoutService::NAVBAR_HEIGHT_MAX, $drawn + 12));
    }

    /** The logo image's natural height in pixels, when it is a file this wiki holds and an image PHP can measure. */
    private function logoHeight(string $logo, ?string $fileTag): ?int
    {
        $entry = $fileTag === null ? null : $this->getService(FileManager::class)->getOne($fileTag);
        if ($entry !== null) {
            $path = FileManager::STORAGE_DIR . '/' . $entry['stored_filename'];
        } elseif (str_starts_with($logo, 'files/')) {
            $path = $logo;
        } else {
            return null;
        }
        $storage = $this->getService(Storage::class);
        if (!$storage->exists($path)) {
            return null;
        }
        $size = $storage->imageSize($path);

        return is_array($size) && $size[1] > 0 ? (int)$size[1] : null;
    }

    /** The Doryphore upload a `files/name.ext` logo meant when it stayed in files/, because some page names it in full: PageTitre's newest, flat or in its own folder. */
    private function legacyUpload(string $logo): ?string
    {
        if (!str_starts_with($logo, 'files/')) {
            return null;
        }
        $name = substr($logo, strlen('files/'));
        $storage = $this->getService(Storage::class);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $candidates = [];
        foreach (["files/PageTitre_{$base}_*", "files/PageTitre/{$base}_*"] as $pattern) {
            foreach ($storage->glob($pattern) as $path) {
                if (LegacyAttachments::recoverOriginalFilename(basename($path), 'PageTitre') === $name) {
                    $candidates[] = $path;
                }
            }
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

        return end($candidates);
    }

    /** The File Content a `files/…` logo is: already a file tag, or the name PageTitre attached it under before the attachments became Content. */
    private function fileTagOf(string $logo): ?string
    {
        if (!str_starts_with($logo, 'files/')) {
            return null;
        }
        $name = substr($logo, strlen('files/'));
        if ($this->getService(FileManager::class)->getOne($name) !== null) {
            return $name;
        }
        $legacy = $this->getService(LegacyAttachments::class);

        return LegacyAttachments::lookup($legacy->fileIndex(), 'PageTitre', $name);
    }

    /**
     * `PageMenuHaut`: a markdown list, one level of nesting, and/or a `{{nav}}` call.
     *
     * Answered as the flat rows a menu is written from -- one entry per row, `child` saying which
     * ones sit under the last top-level one. Ticket 64 made that the shape a menu is saved from,
     * and this migration hands its work to the same writer rather than keeping a shape of its own.
     *
     * @return array{0: list<array{label: string, link: string, child: bool}>, 1: list<string>}
     */
    private function readNavbar(string $body): array
    {
        $lines = $this->meaningfulLines($body);

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
                if ($entry === null) {
                    $rest[] = $line;
                    continue;
                }
                $entries[] = [
                    'label' => $entry['label'],
                    'link' => $entry['link'],
                    'child' => $entries !== [] && strlen($found[1]) > (int)$topIndent,
                ];
                continue;
            }

            if (preg_match('/\{\{\s*nav\b([^}]*)\}\}/i', $line, $found) === 1) {
                foreach ($this->readNav($found[1]) as $entry) {
                    $entries[] = ['label' => $entry['label'], 'link' => $entry['link'], 'child' => false];
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
     * @return array{0: list<array{icon: string, label: string, link: string, child: bool}>, 1: bool, 2: list<string>, 3: bool}
     */
    private function readQuickMenu(string $body): array
    {
        $entries = [];
        $rest = [];
        $inDropdown = false;
        $hasDropdown = false;

        $account = $body === '';

        foreach ($this->meaningfulLines($body) as $line) {
            if (preg_match('/\{\{\s*login\b/i', $line) === 1) {
                $account = true;
                continue;
            }
            if (preg_match('/\{\{\s*buttondropdown\b([^}]*)\}\}/i', $line, $found) === 1) {
                $attributes = $this->readAttributes($found[1]);
                $entries[] = [
                    'icon' => $attributes['icon'] ?? '',
                    'label' => ($attributes['title'] ?? '') ?: (($attributes['text'] ?? '') ?: 'Menu'),
                    'link' => '',
                    'child' => false,
                ];
                $inDropdown = true;
                $hasDropdown = true;
                continue;
            }
            if (preg_match('/\{\{\s*end\s+elem\s*=\s*"buttondropdown"\s*\}\}/i', $line) === 1) {
                $inDropdown = false;
                continue;
            }
            if (preg_match('/\{\{\s*button\b([^}]*)\}\}/i', $line, $found) === 1) {
                $attributes = $this->readAttributes($found[1]);
                $entries[] = [
                    'icon' => $attributes['icon'] ?? '',
                    'label' => $attributes['text'] ?? ($attributes['title'] ?? ''),
                    'link' => $attributes['link'] ?? ($attributes['url'] ?? ''),
                    'child' => $inDropdown,
                ];
                continue;
            }
            if ($inDropdown && preg_match('/^\s*[-*]\s+(.+)$/', $line, $found) === 1) {
                if (preg_match('/^-{3,}$/', trim($found[1])) === 1) {
                    continue;
                }
                $link = $this->readLink(trim($found[1]));
                if ($link !== null && $link['link'] !== '') {
                    $entries[] = ['icon' => '', 'label' => $link['label'], 'link' => $link['link'], 'child' => true];
                    continue;
                }
            }
            $rest[] = $line;
        }

        return [$entries, $account, $rest, $hasDropdown];
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
     * One list item as a label and a link, or null when it holds nothing a menu entry can carry.
     *
     * @return array{label: string, link: string}|null
     */
    private function readLink(string $item): ?array
    {
        if (preg_match('/\{\{[^}]*\bvisibility\s*=/i', $item) === 1) {
            return null;
        }
        if (preg_match('/\[([^\]]*)\]\(\s*([^)\s"]+)(?:\s+"[^"]*")?\s*\)/', $item, $found) === 1) {
            return ['label' => trim($found[1]), 'link' => trim($found[2])];
        }
        if (preg_match('/\[\[\s*(\S+)(?:\s+([^\]]+?))?\s*\]\]/', $item, $found) === 1) {
            return ['label' => trim($found[2] ?? '') !== '' ? trim($found[2]) : $found[1], 'link' => $found[1]];
        }
        if (preg_match('/\{\{\s*button\b([^}]*)\}\}/i', $item, $found) === 1) {
            $attributes = $this->readAttributes($found[1]);
            $label = $attributes['text'] ?? ($attributes['title'] ?? '');

            return $label === '' ? null : ['label' => $label, 'link' => $attributes['link'] ?? ($attributes['url'] ?? '')];
        }

        $label = trim((string)preg_replace('/\{\{.*?\}\}|\{[^}]*\}|[*_`{}]/', '', $item));

        return $label === '' ? null : ['label' => $label, 'link' => ''];
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
     * @return list<string>
     */
    private function meaningfulLines(string $body): array
    {
        $body = (string)preg_replace('/\{#.*?#\}/s', '', $body);

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
    private function sayWhatWasLeftBehind(array $leftovers): void
    {
        foreach ($leftovers as $tag => $lines) {
            if ($lines === []) {
                continue;
            }
            $this->say(
                "these lines of '{$tag}' could not be turned into " . self::SOURCES[$tag]
                . ' and were NOT carried into the configuration (ticket 30). They are still on the page,'
                . ' which is still there: ' . implode(' / ', array_map('trim', $lines))
            );
        }
    }
}

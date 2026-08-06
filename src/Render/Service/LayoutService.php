<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Entity\LayoutChrome;

/**
 * The wiki's chrome: what its brand is, and what is in its two menus (ticket 30).
 *
 * These lived in three wiki pages -- `PageTitre`, `PageMenuHaut`, `PageRapideHaut` -- whose
 * bodies were wiki markup re-parsed on every render. That is the reason none of them could
 * be edited by anything but a text box: a list of links stored as a markdown list is only a
 * list of links to whatever re-reads it, and `PageTitre` holding a site title is something
 * you had to be *told*, since nothing about the name says so.
 *
 * Here they are configuration: a title, a logo, and two lists of entries. The screen that
 * edits them can then be fields rather than a body, the squelette renders known markup
 * instead of transforming somebody's `<ul>` with DOMXPath, and a theme can rely on the shape.
 *
 * The other three chrome pages -- `PageHeader`, `PageMenu`, `PageFooter` -- stay pages, and
 * that is deliberate: what people put in them is arbitrary wiki content (sections, actions,
 * includes), which is exactly what a page is for. Layout links to their editors.
 *
 * Reads come from the container's parameters, like every other config value; writes go
 * through ConfigurationService, which rewrites the file and invalidates the container.
 */
class LayoutService
{
    /** Config keys. Prefixed `layout_`, so the whole of a wiki's chrome greps as one thing. */
    public const TITLE = 'layout_title';
    public const LOGO = 'layout_logo';
    public const BRAND = 'layout_brand';
    public const NAVBAR = 'layout_navbar';
    public const QUICK_MENU = 'layout_quick_menu';
    public const ACCOUNT_BUTTON = 'layout_account_button';
    public const NAVBAR_HEIGHT = 'layout_navbar_height';

    /**
     * How tall the top bar is, in pixels, and the bounds a slider offers.
     *
     * Bounded rather than free: below ~32px the bar cannot hold a button, and above ~160px
     * it is a banner, which is what `PageHeader` is for.
     *
     * The default is deliberately a shade UNDER what the bar comes out at when sized by its
     * content (measured: 49px). `min-height` at 48 therefore changes nothing on a wiki that
     * has never touched the slider, which is the only acceptable default for a setting added
     * to an existing install.
     */
    public const NAVBAR_HEIGHT_DEFAULT = 48;
    public const NAVBAR_HEIGHT_MIN = 32;
    public const NAVBAR_HEIGHT_MAX = 160;

    /**
     * How the brand is drawn: the two things it can show, and where they go.
     *
     * A wiki with a wordmark wants the image alone; one with no logo wants the name; and a
     * logo beside or above a name are different enough visually that picking one is a
     * decision rather than a fallback.
     */
    public const BRAND_MODES = ['text', 'logo', 'logo-text', 'logo-text-below'];

    /** The chrome that is still wiki content, in the order the page shows it. */
    public const PAGES = ['PageHeader', 'PageMenu', 'PageFooter'];

    public function __construct(
        protected ParameterBagInterface $params,
        protected ConfigurationService $configurationService,
        protected PageManager $pageManager,
        protected PageContext $pageContext,
    ) {
    }

    /**
     * Which page plays a chrome role on the page being rendered.
     *
     * `PageHeader` unless the current page's metadata names another one -- the per-page
     * override the theme selector has always offered. It kept being *written* while nothing
     * read it back, so a wiki that set one got the default anyway; this is the read.
     *
     * Only the three page-backed roles have one, and only a wiki name is accepted: the value
     * goes into an `{{include page="…"}}` tag, and page metadata is written by anyone who may
     * edit the page.
     */
    public function pageFor(string $role): string
    {
        if (!in_array($role, self::PAGES, true)) {
            return $role;
        }

        $tag = $this->pageContext->getTag();
        if (empty($tag)) {
            return $role;
        }

        $override = $this->pageManager->getMetadata($tag)[$role] ?? null;

        return (is_string($override) && preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/u', $override) === 1)
            ? $override
            : $role;
    }

    /**
     * The name in the navbar -- the wiki's own name unless the layout overrides it.
     *
     * Falling back rather than duplicating: `yeswiki_name` is already the name in the browser
     * tab, and a wiki whose header says something different is the exception, not the setup
     * step. The seeded `PageTitre` was literally `{{configuration param="yeswiki_name"}}`,
     * which is this fallback written by hand on every wiki.
     */
    public function title(): string
    {
        $title = $this->string(self::TITLE);

        return $title !== '' ? $title : $this->string('yeswiki_name');
    }

    /** Whether the title is the wiki's own name, which is what an empty field means. */
    public function hasOwnTitle(): bool
    {
        return $this->string(self::TITLE) !== '';
    }

    /** The raw field, so the screen shows an empty box rather than the fallback it would save. */
    public function ownTitle(): string
    {
        return $this->string(self::TITLE);
    }

    public function logo(): string
    {
        return $this->string(self::LOGO);
    }

    /**
     * Never a mode the templates do not draw, and never `logo*` without a logo: a wiki that
     * picked "logo only" and then removed the image would otherwise have no brand at all.
     */
    public function brandMode(): string
    {
        $mode = $this->string(self::BRAND);
        if (!in_array($mode, self::BRAND_MODES, true)) {
            $mode = 'text';
        }

        return ($this->logo() === '' && $mode !== 'text') ? 'text' : $mode;
    }

    /**
     * The navbar, one level of children deep.
     *
     * One level because that is what the markup draws (`.yw-dropdown__menu`) and what the
     * seeded menu uses; a third level would render as a dropdown inside a dropdown, which no
     * theme here styles.
     *
     * @return list<array{label: string, link: string, children: list<array{label: string, link: string}>}>
     */
    public function navbar(): array
    {
        $entries = [];
        foreach ($this->arrayOf(self::NAVBAR) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $children = [];
            foreach (is_array($entry['children'] ?? null) ? $entry['children'] : [] as $child) {
                if (is_array($child) && $this->label($child) !== '') {
                    $children[] = ['label' => $this->label($child), 'link' => $this->link($child)];
                }
            }
            // an entry with children and no link of its own is the ordinary dropdown case:
            // the label opens the menu rather than going anywhere
            if ($this->label($entry) !== '') {
                $entries[] = [
                    'label' => $this->label($entry),
                    'link' => $this->link($entry),
                    'children' => $children,
                ];
            }
        }

        return $entries;
    }

    /**
     * The buttons at the right of the navbar: an icon, a label, a link.
     *
     * @return list<array{icon: string, label: string, link: string}>
     */
    public function quickMenu(): array
    {
        $entries = [];
        foreach ($this->arrayOf(self::QUICK_MENU) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $icon = is_string($entry['icon'] ?? null) ? trim($entry['icon']) : '';
            if ($icon === '' && $this->label($entry) === '') {
                continue;
            }
            $entries[] = ['icon' => $icon, 'label' => $this->label($entry), 'link' => $this->link($entry)];
        }

        return $entries;
    }

    /**
     * Whether the account button closes the quick menu -- what `{{login}}` did on the page.
     *
     * Defaults to on: a wiki that has never saved this screen still has to let people in.
     */
    public function hasAccountButton(): bool
    {
        $value = $this->params->has(self::ACCOUNT_BUTTON) ? $this->params->get(self::ACCOUNT_BUTTON) : null;

        // null counts as never-set, not as off: a config file carrying the key with no value
        // -- hand-edited, half-written, provisioned from a template -- would otherwise leave
        // a wiki with no way in, which is the one failure this default exists to prevent
        return $value === null || filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * How tall the top bar is, in pixels.
     *
     * Clamped rather than trusted: this number goes into a CSS custom property that beats
     * every stylesheet (see TemplateEngine::layoutRootStyle), so a stray 0 or 5000 out of a
     * hand-edited config would be a wiki with no navigation and no way to see that it has none.
     */
    public function navbarHeight(): int
    {
        $value = $this->params->has(self::NAVBAR_HEIGHT) ? $this->params->get(self::NAVBAR_HEIGHT) : null;
        if (!is_numeric($value)) {
            return self::NAVBAR_HEIGHT_DEFAULT;
        }

        return max(self::NAVBAR_HEIGHT_MIN, min(self::NAVBAR_HEIGHT_MAX, (int)$value));
    }

    /** The chrome this wiki is configured to wear. */
    public function current(): LayoutChrome
    {
        return new LayoutChrome(
            $this->title(),
            $this->logo(),
            $this->brandMode(),
            $this->navbar(),
            $this->quickMenu(),
            $this->hasAccountButton(),
            $this->navbarHeight()
        );
    }

    /**
     * The chrome a posted form describes -- what Save would write, without writing it.
     *
     * The same object the squelette renders from, which is what makes the preview on
     * `/admin/layout` show the real thing rather than a drawing of it. Falls back to the
     * configured title so an empty title box previews as `yeswiki_name`, exactly as it will
     * render once saved.
     *
     * @param array{title?: string, logo?: string, brand?: string, account?: bool, height?: mixed}          $brand
     * @param list<array{label: string, link: string, children?: list<array{label: string, link: string}>}> $navbar
     * @param list<array{icon: string, label: string, link: string}>                                        $quickMenu
     */
    public function fromForm(array $brand, array $navbar, array $quickMenu): LayoutChrome
    {
        $title = trim((string)($brand['title'] ?? ''));
        $mode = (string)($brand['brand'] ?? 'text');
        $logo = trim((string)($brand['logo'] ?? ''));
        if (!in_array($mode, self::BRAND_MODES, true) || ($logo === '' && $mode !== 'text')) {
            $mode = 'text';
        }
        $height = $brand['height'] ?? null;

        return new LayoutChrome(
            $title !== '' ? $title : $this->string('yeswiki_name'),
            $logo,
            $mode,
            $this->cleanNavbar($navbar),
            $this->cleanQuickMenu($quickMenu),
            (bool)($brand['account'] ?? false),
            is_numeric($height)
                ? max(self::NAVBAR_HEIGHT_MIN, min(self::NAVBAR_HEIGHT_MAX, (int)$height))
                : self::NAVBAR_HEIGHT_DEFAULT
        );
    }

    /**
     * Write the whole of the layout, in one config write.
     *
     * Everything is re-stated on every save rather than patched: the screen posts the whole
     * form, so a key missing from it means "removed", which a merge would silently undo.
     *
     * @param array{title?: string, logo?: string, brand?: string, account?: bool, height?: mixed}          $brand
     * @param list<array{label: string, link: string, children?: list<array{label: string, link: string}>}> $navbar
     * @param list<array{icon: string, label: string, link: string}>                                        $quickMenu
     */
    public function save(array $brand, array $navbar, array $quickMenu): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        // the same reading the preview did, so what was previewed is what is written
        $chrome = $this->fromForm($brand, $navbar, $quickMenu);

        // through ArrayAccess rather than `$config->layout_title`: the property form goes
        // through __get/__set, which is the same store but is invisible to static analysis
        $config[self::TITLE] = trim((string)($brand['title'] ?? ''));
        $config[self::LOGO] = $chrome->logo;
        $config[self::BRAND] = $chrome->brandMode;
        $config[self::ACCOUNT_BUTTON] = $chrome->accountButton;
        $config[self::NAVBAR_HEIGHT] = $chrome->navbarHeight;
        $config[self::NAVBAR] = $chrome->navbar;
        $config[self::QUICK_MENU] = $chrome->quickMenu;

        $config->write();
    }

    /**
     * Whether saving would work, asked before the screen offers the fields rather than
     * discovered on submit -- an instance whose config file is read-only can be told why.
     */
    public function isConfigWritable(): bool
    {
        return is_writable(ConfigurationFileProvider::getConfigFileFromEnv());
    }

    /**
     * @param list<array{label: string, link: string, children?: list<array{label: string, link: string}>}> $navbar
     *
     * @return list<array{label: string, link: string, children: list<array{label: string, link: string}>}>
     */
    private function cleanNavbar(array $navbar): array
    {
        $clean = [];
        foreach ($navbar as $entry) {
            if ($this->label($entry) === '') {
                // an empty row is how a row is deleted without JavaScript
                continue;
            }
            $children = [];
            foreach ($entry['children'] ?? [] as $child) {
                if ($this->label($child) !== '') {
                    $children[] = ['label' => $this->label($child), 'link' => $this->link($child)];
                }
            }
            $clean[] = ['label' => $this->label($entry), 'link' => $this->link($entry), 'children' => $children];
        }

        return $clean;
    }

    /**
     * @param list<array{icon: string, label: string, link: string}> $quickMenu
     *
     * @return list<array{icon: string, label: string, link: string}>
     */
    private function cleanQuickMenu(array $quickMenu): array
    {
        $clean = [];
        foreach ($quickMenu as $entry) {
            $icon = trim($entry['icon']);
            if ($icon === '' && $this->label($entry) === '') {
                continue;
            }
            $clean[] = ['icon' => $icon, 'label' => $this->label($entry), 'link' => $this->link($entry)];
        }

        return $clean;
    }

    /** @param array<string, mixed> $entry */
    private function label(array $entry): string
    {
        return is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
    }

    /** @param array<string, mixed> $entry */
    private function link(array $entry): string
    {
        return is_string($entry['link'] ?? null) ? trim($entry['link']) : '';
    }

    private function string(string $key): string
    {
        if (!$this->params->has($key)) {
            return '';
        }
        $value = $this->params->get($key);

        return is_string($value) ? trim($value) : '';
    }

    /** @return array<int|string, mixed> */
    private function arrayOf(string $key): array
    {
        if (!$this->params->has($key)) {
            return [];
        }
        $value = $this->params->get($key);

        return is_array($value) ? $value : [];
    }
}

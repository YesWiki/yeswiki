<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Entity\LayoutChrome;

/** The wiki's chrome: what its brand is, and what is in its two menus (ticket 30). */
class LayoutService
{
    /** Config keys. */
    public const TITLE = 'layout_title';
    public const LOGO = 'layout_logo';
    public const BRAND = 'layout_brand';
    public const NAVBAR = 'layout_navbar';
    public const QUICK_MENU = 'layout_quick_menu';
    public const ACCOUNT_BUTTON = 'layout_account_button';
    public const NAVBAR_HEIGHT = 'layout_navbar_height';

    /** How tall the top bar is, in pixels, and the bounds a slider offers. */
    public const NAVBAR_HEIGHT_DEFAULT = 48;
    public const NAVBAR_HEIGHT_MIN = 32;
    public const NAVBAR_HEIGHT_MAX = 160;

    /** How the brand is drawn: the two things it can show, and where they go. */
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

    /** Which page plays a chrome role on the page being rendered. */
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

    /** The name in the navbar -- the wiki's own name unless the layout overrides it. */
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
     * Never a mode the templates do not draw, and never `logo*` without a logo: a wiki that picked "logo only" and then removed the image would otherwise have no brand at all.
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

    /** Whether the account button closes the quick menu -- what `{{login}}` did on the page. */
    public function hasAccountButton(): bool
    {
        $value = $this->params->has(self::ACCOUNT_BUTTON) ? $this->params->get(self::ACCOUNT_BUTTON) : null;

        return $value === null || filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** How tall the top bar is, in pixels. */
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
     * @param array{title?: string, logo?: string, brand?: string, account?: bool, height?: mixed}          $brand
     * @param list<array{label: string, link: string, children?: list<array{label: string, link: string}>}> $navbar
     * @param list<array{icon: string, label: string, link: string}>                                        $quickMenu
     */
    public function save(array $brand, array $navbar, array $quickMenu): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $chrome = $this->fromForm($brand, $navbar, $quickMenu);

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
     * Whether saving would work, asked before the screen offers the fields rather than discovered on submit -- an instance whose config file is read-only can be told why.
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

    /**
     * @param array<string, mixed> $entry
     */
    private function label(array $entry): string
    {
        return is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
    }

    /**
     * @param array<string, mixed> $entry
     */
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

    /**
     * @return array<int|string, mixed>
     */
    private function arrayOf(string $key): array
    {
        if (!$this->params->has($key)) {
            return [];
        }
        $value = $this->params->get($key);

        return is_array($value) ? $value : [];
    }
}

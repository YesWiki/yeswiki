<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
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
    /**
     * Which menu each placement draws, and how it draws it (ticket 64 / ADR-0028).
     *
     * These used to hold the entries themselves. A config array has no revisions, no ACL of its
     * own, and no way for `{{nav}}` to reach it, so the entries are a `menu` row now and these name
     * one. The flags belong to the placement rather than to the menu, which is what lets one menu
     * be icons in the bar and labels in a page.
     */
    public const NAVBAR = 'layout_navbar';
    public const QUICK_MENU = 'layout_quick_menu';
    public const NAVBAR_ICONS = 'layout_navbar_icons';
    public const NAVBAR_LABELS = 'layout_navbar_labels';
    public const NAVBAR_DROPDOWN = 'layout_navbar_dropdown';
    public const QUICK_MENU_ICONS = 'layout_quick_menu_icons';
    public const QUICK_MENU_LABELS = 'layout_quick_menu_labels';
    public const QUICK_MENU_DROPDOWN = 'layout_quick_menu_dropdown';

    /**
     * What each placement draws when nothing has been said: the navbar reads as words, the quick
     * access bar as glyphs, and both of them were doing exactly that before they had a choice.
     *
     * @var array<string, bool>
     */
    public const FLAG_DEFAULTS = [
        self::NAVBAR_ICONS => false,
        self::NAVBAR_LABELS => true,
        self::NAVBAR_DROPDOWN => true,
        self::QUICK_MENU_ICONS => true,
        self::QUICK_MENU_LABELS => false,
        self::QUICK_MENU_DROPDOWN => false,
    ];
    public const ACCOUNT_BUTTON = 'layout_account_button';
    public const NAVBAR_HEIGHT = 'layout_navbar_height';
    public const NAVBAR_POSITION = 'layout_navbar_position';
    public const HEADER_POSITION = 'layout_header_position';

    /** How tall the top bar is, in pixels, and the bounds a slider offers. */
    public const NAVBAR_HEIGHT_DEFAULT = 48;
    public const NAVBAR_HEIGHT_MIN = 32;
    public const NAVBAR_HEIGHT_MAX = 160;

    /** How the brand is drawn: the two things it can show, and where they go. */
    public const BRAND_MODES = ['text', 'logo', 'logo-text', 'logo-text-below'];

    /** Where the main menu sits. The first value is what an unset wiki gets. */
    public const NAVBAR_POSITIONS = ['top', 'left', 'right'];

    /** Where the banner sits relative to the main menu. */
    public const HEADER_POSITIONS = ['after', 'before'];

    /** The chrome that is still wiki content, in the order the page shows it. */
    public const PAGES = ['PageHeader', 'PageFooter'];

    public function __construct(
        protected ParameterBagInterface $params,
        protected ConfigurationService $configurationService,
        protected PageManager $pageManager,
        protected MenuManager $menuManager,
        protected PageContext $pageContext,
        protected Storage $storage,
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

    /** The tag of the menu the navbar draws, empty when this wiki has none. */
    public function navbar(): string
    {
        return $this->string(self::NAVBAR);
    }

    /** The tag of the menu the quick access bar draws. */
    public function quickMenu(): string
    {
        return $this->string(self::QUICK_MENU);
    }

    /**
     * How a placement draws its menu: icons, labels, dropdowns.
     *
     * @return array{showicons: bool, showlabels: bool, showdropdown: bool}
     */
    public function navbarFlags(): array
    {
        return [
            'showicons' => $this->flag(self::NAVBAR_ICONS),
            'showlabels' => $this->flag(self::NAVBAR_LABELS),
            'showdropdown' => $this->flag(self::NAVBAR_DROPDOWN),
        ];
    }

    /**
     * @return array{showicons: bool, showlabels: bool, showdropdown: bool}
     */
    public function quickMenuFlags(): array
    {
        return [
            'showicons' => $this->flag(self::QUICK_MENU_ICONS),
            'showlabels' => $this->flag(self::QUICK_MENU_LABELS),
            'showdropdown' => $this->flag(self::QUICK_MENU_DROPDOWN),
        ];
    }

    /** One display flag, defaulting to what that placement has always drawn. */
    public function flag(string $key): bool
    {
        $value = $this->params->has($key) ? $this->params->get($key) : null;

        return $value === null || $value === '' ? (self::FLAG_DEFAULTS[$key] ?? false) : filter_var($value, FILTER_VALIDATE_BOOL);
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

    /** Where the main menu sits: a top bar, or a column down one side. */
    public function navbarPosition(): string
    {
        return $this->oneOf($this->string(self::NAVBAR_POSITION), self::NAVBAR_POSITIONS);
    }

    /** Whether the banner is drawn before the main menu or after it. */
    public function headerPosition(): string
    {
        return $this->oneOf($this->string(self::HEADER_POSITION), self::HEADER_POSITIONS);
    }

    /** The wiki markup one of the chrome pages holds, empty when it has never been written. */
    public function pageContent(string $tag): string
    {
        $this->assertChromePage($tag);
        $page = $this->pageManager->getOne($tag);

        return $page === null ? '' : PageBody::content($page['body'] ?? []);
    }

    /**
     * Write one of the chrome pages, keeping whatever else its body carries.
     *
     * @throws \Exception when the wiki refuses the write
     */
    public function savePage(string $tag, string $content): void
    {
        $this->assertChromePage($tag);

        $body = $this->pageManager->getOne($tag)['body'] ?? [];
        $body[PageBody::CONTENT] = rtrim(str_replace("\r", '', $content));

        if ($this->pageManager->save($tag, $body) !== 0) {
            throw new \Exception(_t('EDIT_NO_WRITE_ACCESS'));
        }
    }

    /** The chrome this wiki is configured to wear. */
    public function current(): LayoutChrome
    {
        return new LayoutChrome(
            $this->title(),
            $this->logo(),
            $this->brandMode(),
            $this->menuNodes($this->navbar()),
            $this->menuNodes($this->quickMenu()),
            $this->navbarFlags(),
            $this->quickMenuFlags(),
            $this->hasAccountButton(),
            $this->navbarHeight()
        );
    }

    /**
     * The chrome a posted form describes -- what Save would write, without writing it.
     *
     * @param array{title?: string, logo?: string, brand?: string, account?: bool, height?: mixed, navbarPosition?: string, headerPosition?: string, navbarFlags?: array<string, bool>, quickMenuFlags?: array<string, bool>} $brand
     * @param list<array<string, mixed>>                                                                                                                                                                                     $navbar
     * @param list<array<string, mixed>>                                                                                                                                                                                     $quickMenu
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
            MenuManager::nodesFromRows($navbar),
            MenuManager::nodesFromRows($quickMenu),
            $this->flagsFrom($brand['navbarFlags'] ?? null, self::NAVBAR_ICONS, self::NAVBAR_LABELS, self::NAVBAR_DROPDOWN),
            $this->flagsFrom($brand['quickMenuFlags'] ?? null, self::QUICK_MENU_ICONS, self::QUICK_MENU_LABELS, self::QUICK_MENU_DROPDOWN),
            (bool)($brand['account'] ?? false),
            is_numeric($height)
                ? max(self::NAVBAR_HEIGHT_MIN, min(self::NAVBAR_HEIGHT_MAX, (int)$height))
                : self::NAVBAR_HEIGHT_DEFAULT
        );
    }

    /**
     * Write the whole of the layout: the brand and the flags into configuration, the entries into
     * the two menus configuration names.
     *
     * The menus are Content, so this is two kinds of write rather than one, and they are ordered:
     * the rows are saved first, and configuration is only pointed at a menu that exists.
     *
     * @param array{title?: string, logo?: string, brand?: string, account?: bool, height?: mixed, navbarPosition?: string, headerPosition?: string, navbarFlags?: array<string, bool>, quickMenuFlags?: array<string, bool>} $brand
     * @param list<array<string, mixed>>                                                                                                                                                                                     $navbar
     * @param list<array<string, mixed>>                                                                                                                                                                                     $quickMenu
     */
    public function save(array $brand, array $navbar, array $quickMenu): void
    {
        $chrome = $this->fromForm($brand, $navbar, $quickMenu);

        $navbarTag = $this->writeChromeMenu($this->navbar(), _t('LAYOUT_NAVBAR_MENU_TITLE'), $chrome->navbar);
        $quickTag = $this->writeChromeMenu($this->quickMenu(), _t('LAYOUT_QUICK_MENU_TITLE'), $chrome->quickMenu);

        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $config[self::TITLE] = trim((string)($brand['title'] ?? ''));
        $config[self::LOGO] = $chrome->logo;
        $config[self::BRAND] = $chrome->brandMode;
        $config[self::ACCOUNT_BUTTON] = $chrome->accountButton;
        $config[self::NAVBAR_HEIGHT] = $chrome->navbarHeight;
        $config[self::NAVBAR_POSITION] = $this->oneOf((string)($brand['navbarPosition'] ?? ''), self::NAVBAR_POSITIONS);
        $config[self::HEADER_POSITION] = $this->oneOf((string)($brand['headerPosition'] ?? ''), self::HEADER_POSITIONS);
        $config[self::NAVBAR] = $navbarTag;
        $config[self::QUICK_MENU] = $quickTag;
        $config[self::NAVBAR_ICONS] = $chrome->navbarFlags['showicons'];
        $config[self::NAVBAR_LABELS] = $chrome->navbarFlags['showlabels'];
        $config[self::NAVBAR_DROPDOWN] = $chrome->navbarFlags['showdropdown'];
        $config[self::QUICK_MENU_ICONS] = $chrome->quickMenuFlags['showicons'];
        $config[self::QUICK_MENU_LABELS] = $chrome->quickMenuFlags['showlabels'];
        $config[self::QUICK_MENU_DROPDOWN] = $chrome->quickMenuFlags['showdropdown'];

        $config->write();
    }

    /**
     * The nodes of the menu a placement names, empty when it names none or the row has gone.
     *
     * @return list<MenuNode>
     */
    public function menuNodes(string $tag): array
    {
        return $this->menuManager->getOne($tag)['nodes'] ?? [];
    }

    /**
     * Write one of the two chrome menus, making the row the first time a wiki saves its layout.
     *
     * @param list<MenuNode> $nodes
     *
     * @return string the tag configuration should name
     */
    private function writeChromeMenu(string $tag, string $title, array $nodes): string
    {
        if ($tag !== '' && $this->menuManager->isMenu($tag)) {
            $this->menuManager->update($tag, $title, $nodes);

            return $tag;
        }

        return $this->menuManager->create($title, $nodes, $tag !== '' ? $tag : null, true);
    }

    /**
     * @param array<string, bool>|null $posted
     *
     * @return array{showicons: bool, showlabels: bool, showdropdown: bool}
     */
    private function flagsFrom(?array $posted, string $iconsKey, string $labelsKey, string $dropdownKey): array
    {
        if ($posted === null) {
            return ['showicons' => $this->flag($iconsKey), 'showlabels' => $this->flag($labelsKey), 'showdropdown' => $this->flag($dropdownKey)];
        }

        return [
            'showicons' => (bool)($posted['showicons'] ?? false),
            'showlabels' => (bool)($posted['showlabels'] ?? false),
            'showdropdown' => (bool)($posted['showdropdown'] ?? false),
        ];
    }

    /**
     * Whether saving would work, asked before the screen offers the fields rather than discovered on submit -- an instance whose config file is read-only can be told why.
     */
    public function isConfigWritable(): bool
    {
        return $this->storage->isWritable(ConfigurationFileProvider::getConfigFileFromEnv());
    }





    private function assertChromePage(string $tag): void
    {
        if (!in_array($tag, self::PAGES, true)) {
            throw new \InvalidArgumentException("{$tag} is not one of this wiki's chrome pages");
        }
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private function string(string $key): string
    {
        if (!$this->params->has($key)) {
            return '';
        }
        $value = $this->params->get($key);

        return is_string($value) ? trim($value) : '';
    }

}

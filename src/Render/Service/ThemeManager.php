<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\StringUtilService;

class ThemeManager implements EventSubscriberInterface
{
    public const CUSTOM_CSS_PRESETS_PATH = 'custom/css-presets';
    public const CUSTOM_CSS_PRESETS_PREFIX = 'custom/';
    public const CUSTOM_FONT_PATH = 'custom/fonts';

    /** What a downloaded family's `@font-face` rules are kept in, beside its files. */
    public const FONT_FACES_FILE = 'faces.css';

    /** What a page may say about its own chrome, beside the theme it wears. */
    public const SPECIAL_METADATA = [
        'PageFooter',
        'PageHeader',
        'favorite_preset',
    ];
    /** What Google is told we are, so it answers with woff2. */
    public const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    protected string $errorMessage;

    /** @var array<string, string> theme, squelette, style, preset and background_image */
    protected array $favorites;

    /** @var string|null the squelette's source, once loadTheme() has read it */
    protected ?string $fileContent;
    protected bool $fileLoaded;
    protected PageManager $pageManager;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;
    protected ?string $squelette;

    /** @var array<string, array<string, mixed>> the themes found, by name */
    protected array $templates;
    protected ?string $theme;
    protected TemplateEngine $twig;
    protected bool $useFallbackTheme;
    protected TemplateHelperService $utils;
    protected Storage $storage;
    protected ContainerInterface $container;

    public static function getSubscribedEvents()
    {
        return [
            'page.created' => 'saveMetadataIfNeeded',
        ];
    }

    public function __construct(
        ContainerInterface $container,
        TemplateEngine $twig,
        PageManager $pageManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        TemplateHelperService $utils,
        Storage $storage,
        private readonly ProgramFiles $programFiles,
    ) {
        $this->container = $container;
        $this->errorMessage = '';
        $this->favorites = [
            'theme' => '',
            'squelette' => '',
            'style' => '',
            'preset' => ($params->has('favorite_preset') && is_string($params->get('favorite_preset'))
                && !empty($params->get('favorite_preset'))) ? $params->get('favorite_preset') : '',
            'background_image' => '',
        ];
        $this->fileContent = null;
        $this->fileLoaded = false;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->squelette = null;
        $this->templates = [];
        $this->theme = null;
        $this->twig = $twig;
        $this->useFallbackTheme = false;
        $this->utils = $utils;
        $this->storage = $storage;
    }

    /**
     * function imported from tools/templates/libs/templates.functions.php
     * to load templates and generate an error if needed.
     *
     * @param array<string, mixed>|null $metadata metadata for the current Page
     *
     * @return array<string, array<string, mixed>>|null the templates found, by theme name
     */
    public function loadTemplates($metadata = []): ?array
    {
        if ($this->params->has('hide_action_template') && $this->params->get('hide_action_template') == '1') {
            $this->setFavorite('theme', $this->getConfigAsStringOrDefault('favorite_theme', THEME_PAR_DEFAUT));
            $this->setFavorite('style', $this->getConfigAsStringOrDefault('favorite_style', CSS_PAR_DEFAUT));
            $this->setFavorite('squelette', $this->getConfigAsStringOrDefault('favorite_squelette', SQUELETTE_PAR_DEFAUT));
            $this->setFavorite('background_image', $this->getConfigAsStringOrDefault('favorite_background_image', BACKGROUND_IMAGE_PAR_DEFAUT));
            $this->setFavorite('preset', $this->getConfigAsStringOrDefault('favorite_preset', ''));
        } else {
            $requested = [];
            $keysToVerify = ['theme', 'squelette', 'style', 'preset'];
            $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
            foreach ($keysToVerify as $val) {
                $requested[$val] = null;
                $requestVal = $request->get($val);
                if (!empty($requestVal)) {
                    $path = str_replace('custom/', '', $requestVal);
                    if (preg_match('/\//', $path, $matches)) {
                        exit('ERROR: Suspicious path traversal attempt.');
                    }
                    switch ($val) {
                        case 'theme':
                            $name = basename($requestVal);
                            $customThemePath = $this->storage->directoryExists('custom/themes/' . $name) ? $name : '';
                            $classicThemePath = basename((string)realpath(YESWIKI_PROGRAM_DIR . '/themes/' . $requestVal));
                            $requested[$val] = !empty($customThemePath) ? $customThemePath : $classicThemePath;
                            break;

                        case 'squelette':
                            $requestVal = self::normalizeSqueletteName($requestVal);
                            $customPath = basename($requestVal);
                            $classicPath = basename((string)realpath(YESWIKI_PROGRAM_DIR . '/themes/' . $requested['theme'] . '/squelettes/' . $requestVal));
                            $requested[$val] = null;
                            if ($this->storage->exists('custom/themes/' . $requested['theme'] . '/squelettes/' . $customPath)) {
                                $requested[$val] = $customPath;
                            } elseif (file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . $requested['theme'] . '/squelettes/' . $classicPath)) {
                                $requested[$val] = $classicPath;
                            }
                            if (!preg_match('/\.twig$/i', $requested[$val] ?? '', $matches)) {
                                $requested[$val] = null;
                            }

                            break;

                        default:
                            $customPath = basename($requestVal);
                            $classicPath = basename((string)realpath(YESWIKI_PROGRAM_DIR . '/themes/' . $requested['theme'] . '/' . $val . 's/' . $requestVal));
                            $requested[$val] = null;
                            if ($this->storage->exists('custom/themes/' . $requested['theme'] . '/' . $val . 's/' . $customPath)) {
                                $requested[$val] = $customPath;
                            } elseif (file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . $requested['theme'] . '/' . $val . 's/' . $classicPath)) {
                                $requested[$val] = $classicPath;
                            }

                            break;
                    }
                }
            }
            if (!empty($requested['theme']) && !empty($requested['style']) && !empty($requested['squelette']) && preg_match('/\.twig$/i', $requested['squelette'], $matches)
            ) {
                $this->setFavorite('theme', $requested['theme']);
                $this->setFavorite('style', $requested['style']);
                $this->setFavorite('squelette', $requested['squelette']);

                if (!empty($requested['preset'])
                        && (
                            (
                                ($isCustom = (substr($requested['preset'], 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX))
                                && $this->storage->fileExists(self::CUSTOM_CSS_PRESETS_PATH . '/' . substr($requested['preset'], strlen(self::CUSTOM_CSS_PRESETS_PREFIX)))
                            )
                            || (
                                !$isCustom
                                && (
                                    $this->storage->fileExists('custom/themes/' . $requested['theme'] . '/presets/' . $requested['preset'])
                                    || is_file(YESWIKI_PROGRAM_DIR . '/themes/' . $requested['theme'] . '/presets/' . $requested['preset'])
                                )
                            )
                        )
                ) {
                    $this->setFavorite('preset', $requested['preset']);
                }

                $bgimg = $request->get('bgimg');
                if (!empty($bgimg) && $this->storage->fileExists('files/backgrounds/' . $bgimg)) {
                    $this->setFavorite('background_image', $bgimg);
                } else {
                    $this->setFavorite('background_image', BACKGROUND_IMAGE_PAR_DEFAUT);
                }
            } else {
                if (isset($metadata['theme']) && isset($metadata['style']) && isset($metadata['squelette'])) {
                    $this->setFavorite('theme', $metadata['theme']);
                    $this->setFavorite('style', $metadata['style']);
                    $this->setFavorite('squelette', $metadata['squelette']);
                    if (!empty($metadata['favorite_preset'])) {
                        $this->setFavorite('preset', $metadata['favorite_preset']);
                    }
                    if (isset($metadata['bgimg'])) {
                        $this->setFavorite('background_image', $metadata['bgimg']);
                    } else {
                        $this->setFavorite('background_image', '');
                    }
                } else {
                    if (empty($this->favorites['theme'])) {
                        $this->setFavorite('theme', $this->getConfigAsStringOrDefault('favorite_theme', THEME_PAR_DEFAUT));
                    }
                    if (empty($this->favorites['style'])) {
                        $this->setFavorite('style', $this->getConfigAsStringOrDefault('favorite_style', CSS_PAR_DEFAUT));
                    }
                    if (empty($this->favorites['squelette'])) {
                        $this->setFavorite('squelette', $this->getConfigAsStringOrDefault('favorite_squelette', SQUELETTE_PAR_DEFAUT));
                    }
                    if (empty($this->favorites['background_image'])) {
                        $this->setFavorite('background_image', $this->getConfigAsStringOrDefault('favorite_background_image', BACKGROUND_IMAGE_PAR_DEFAUT));
                    }
                    if (empty($this->favorites['preset'])) {
                        $this->setFavorite('preset', $this->getConfigAsStringOrDefault('favorite_preset', ''));
                    }
                }
            }
        }

        if (
            (!$this->storage->exists('custom/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette'])
                and !file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette']))
            || (!$this->storage->exists('custom/themes/' . $this->favorites['theme'] . '/styles/' . $this->favorites['style'])
                && !file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . $this->favorites['theme'] . '/styles/' . $this->favorites['style']))
        ) {
            if (
                $this->favorites['theme'] != THEME_PAR_DEFAUT
                || !file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . THEME_PAR_DEFAUT . '/squelettes/' . $this->favorites['squelette'])
                || !file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . THEME_PAR_DEFAUT . '/styles/' . $this->favorites['style'])
            ) {
                if (
                    file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . THEME_PAR_DEFAUT . '/squelettes/' . SQUELETTE_PAR_DEFAUT)
                    && file_exists(YESWIKI_PROGRAM_DIR . '/themes/' . THEME_PAR_DEFAUT . '/styles/' . CSS_PAR_DEFAUT)
                ) {
                    $this->container->get(ThemeResolutionError::class)->themeNotFound(
                        (string)$this->favorites['theme'],
                        (string)$this->favorites['style'],
                        (string)$this->favorites['squelette']
                    );
                    $this->setFavorite('theme', THEME_PAR_DEFAUT);
                    $this->setFavorite('style', CSS_PAR_DEFAUT);
                    $this->setFavorite('squelette', SQUELETTE_PAR_DEFAUT);
                    $this->setFavorite('background_image', BACKGROUND_IMAGE_PAR_DEFAUT);
                } else {
                    return [];
                }
            }
            $this->useFallbackTheme = true;
        }
        if (!empty($this->favorites['preset'])
                && (
                    ($isCutom = substr($this->favorites['preset'], 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX)
                    && !$this->storage->exists(self::CUSTOM_CSS_PRESETS_PATH . '/'
                        . substr($this->favorites['preset'], strlen(self::CUSTOM_CSS_PRESETS_PREFIX)))
                )
        ) {
            unset($this->favorites['preset']);
        }

        $this->templates = [];

        if (is_dir(YESWIKI_PROGRAM_DIR . '/themes')) {
            $this->templates = array_merge($this->templates, $this->utils->searchTemplateFiles('themes', false));
        }
        if ($this->storage->directoryExists('custom/themes')) {
            $this->templates = array_replace_recursive($this->templates, $this->utils->searchTemplateFiles('custom/themes', true));
        }
        ksort($this->templates);

        return $this->templates;
    }

    public function loadTheme(): bool
    {
        $theme = $this->getFavoriteTheme();
        $theme = empty($theme) ? THEME_PAR_DEFAUT : $theme;

        $squelette = $this->getFavoriteSquelette();
        $squelette = empty($squelette) ? SQUELETTE_PAR_DEFAUT : $squelette;

        $fileAlreadyLoaded = $this->fileLoaded
            && ($this->theme == $theme)
            && ($this->squelette == $squelette);
        if ($fileAlreadyLoaded) {
            return true;
        }
        $this->theme = $theme;
        $this->squelette = $squelette;

        $themePath = 'themes/' . $this->theme;
        $filePath = $themePath . '/squelettes/' . $this->squelette;

        if (!((!$this->useFallbackTheme && $this->storage->exists('custom/' . $themePath)) || file_exists(YESWIKI_PROGRAM_DIR . '/' . $themePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_THEME_FOLDER') . $this->theme . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }

        if (!((!$this->useFallbackTheme && $this->storage->exists('custom/' . $filePath)) || file_exists(YESWIKI_PROGRAM_DIR . '/' . $filePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_SQUELETTE_FILE') . $this->squelette . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }
        $fromInstance = !$this->useFallbackTheme && $this->storage->exists('custom/' . $filePath);

        // Two trees, two services, and the path stays relative to whichever one answers: a wiki's
        // own copy is Instance data and may be in a bucket, the shipped one is code and is not.
        $fileContent = $fromInstance ? $this->storage->read('custom/' . $filePath) : $this->programFiles->read($filePath);
        $filePath = $fromInstance ? 'custom/' . $filePath : $this->programFiles->path($filePath);
        if ($fileContent === '') {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_ERROR_GETTING_FILE') . $filePath,
            ]);

            return false;
        }
        $this->fileContent = $fileContent;
        $this->fileLoaded = true;

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Everything outside the `body` block that a page's metadata can change (ticket 16).
     *
     * @return list<string>
     */
    public function layoutIdentity(): array
    {
        return [
            $this->getFavoriteTheme(),
            $this->getFavoriteSquelette(),
            $this->getFavoriteStyle(),
            $this->getFavoritePreset(),
            $this->getFavoriteBackgroundImage(),
            (string)($this->container->get(LanguageService::class)->preferredLanguage() ?? ''),
            $this->container->get(LayoutService::class)->navbarPosition(),
            $this->container->get(LayoutService::class)->headerPosition(),
            (string)$this->container->get(LayoutService::class)->navbarHeight(),
            // Ticket 64: how a placement draws its menu is a Layout setting, so leaving it out
            // would swap a page under stale chrome on a boosted navigation.
            ...array_map(
                fn (string $flag): string => $this->container->get(LayoutService::class)->flag($flag) ? '1' : '0',
                array_keys(LayoutService::FLAG_DEFAULTS)
            ),
        ];
    }

    /** The whole page: the squelette rendered around $pageContent, with its `head` block rendered **last**. */
    public function renderPage(string $pageContent): string
    {
        if (!$this->fileLoaded && !$this->loadTheme()) {
            return '';
        }
        $this->container->get(CoreAssets::class)->register();

        $boosted = $this->container->get(BoostedNavigation::class);
        $boosted->markPageRendered();

        $template = $this->twig->createSquelette((string)$this->fileContent);

        $body = $template->renderBlock('body', [
            'page_content' => Flash::display() . $pageContent,
            'htmx_navigation' => $boosted->isEnabled(),
            'layout_fingerprint' => $boosted->fingerprint(),
            'layout_fingerprint_header' => BoostedNavigation::FINGERPRINT_HEADER,
        ]);

        $debug = $this->container->get(DebugReport::class);

        if ($boosted->isBoosted()) {
            return $this->twig->render('@core/_boosted-title.twig', ['title' => $this->pageTitle()])
                . $debug->appendTo($body)
                . $this->container->get(AssetRegistry::class)->drain()->toOutOfBandHtml();
        }

        return $debug->appendTo($template->renderBlock('head') . $body);
    }

    /** The document title, rendered the same way the head block does it. */
    private function pageTitle(): string
    {
        $runner = $this->container->get(ActionRunner::class);

        return trim($runner->action('configuration param="wakka_name"') . ' : ' . $runner->action('pagetitle'));
    }

    /**
     * The document head alone, for surfaces that supply their own body -- the iframe handlers, which want the wiki's `<head>` without any of the theme's chrome.
     */
    public function renderHead(): string
    {
        if (!$this->fileLoaded && !$this->loadTheme()) {
            return '';
        }
        $this->container->get(CoreAssets::class)->register();

        return $this->twig->createSquelette((string)$this->fileContent)->renderBlock('head');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    public function getFavoriteTheme(): string
    {
        return $this->favorites['theme'];
    }

    public function getFavoriteSquelette(): string
    {
        return $this->favorites['squelette'];
    }

    public function getFavoriteStyle(): string
    {
        return $this->favorites['style'];
    }

    public function getFavoritePreset(): string
    {
        return $this->favorites['preset'] ?? '';
    }

    public function getFavoriteBackgroundImage(): string
    {
        return $this->favorites['background_image'];
    }

    /**
     * @param mixed $newVal what the caller asked for; anything but a non-empty string stores ''
     */
    protected function setFavorite(string $key, $newVal): void
    {
        if ($key === 'squelette' && is_string($newVal)) {
            $newVal = self::normalizeSqueletteName($newVal);
        }
        $this->favorites[$key] = (empty($newVal) || !is_string($newVal))
            ? ''
            : $newVal;
    }

    /**
     * Squelettes are Twig since the tpl.html engine died; historical `.tpl.html`
     * names survive in page metadata, wakka.config.php and old theme-switcher URLs.
     */
    public static function normalizeSqueletteName(string $name): string
    {
        return preg_replace('/\.tpl\.html$/i', '.twig', $name) ?? $name;
    }

    /**
     * Canonical stored form of a squelette name: legacy suffix normalized, `.twig`
     * appended when the name has no suffix (theme-picker selects send bare names).
     */
    public static function squeletteFileName(string $name): string
    {
        $name = self::normalizeSqueletteName($name);

        return str_ends_with($name, '.twig') ? $name : $name . '.twig';
    }

    public function getUseFallbackTheme(): bool
    {
        return $this->useFallbackTheme;
    }

    protected function getConfigAsStringOrDefault(string $key, string $default): string
    {
        return ($this->params->has($key) && !empty($this->params->get($key))
            && is_string($this->params->get($key)))
            ? $this->params->get($key)
            : $default;
    }

    /**
     * get custom css-presets.
     *
     * @return array<string, string> $template = [$filename=>$css]
     */
    public function getCustomCSSPresets(): array
    {
        $path = self::CUSTOM_CSS_PRESETS_PATH;
        $tab = [];
        foreach ($this->storage->glob($path . '/*.css') as $filepath) {
            $filename = pathinfo($filepath)['filename'] . '.css';
            $css = $this->storage->read($filepath);
            if (!empty($css)) {
                $tab[$filename] = $css;
            }
        }

        return $tab;
    }

    /**
     * delete a css custom preset.
     *
     * @return array{status: bool, message: string}
     */
    public function deleteCustomCSSPreset(string $filename): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $path = self::CUSTOM_CSS_PRESETS_PATH;
        if (!$this->container->get(AclService::class)->isAdmin()) {
            return ['status' => false, 'message' => 'User is not admin'];
        }
        if (!$this->storage->exists($path . '/' . $filename)) {
            return ['status' => false, 'message' => 'File ' . $filename . ' is not existing !'];
        }

        try {
            $this->storage->delete($path . '/' . $filename);
        } catch (StorageException) {
            return ['status' => false, 'message' => 'Not possible to delete ' . $filename];
        }

        return ['status' => true, 'message' => ''];
    }

    /**
     * Write an instance preset, verbatim, and install any webfont its type asks for.
     *
     * @param list<string> $fontFamilies the families to install, usually body and headings
     *
     * @return array{status: bool, message: string, errorCode: int|null}
     */
    public function writeCustomCSSPreset(string $filename, string $css, array $fontFamilies = []): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->container->get(AuthenticationService::class)->getLoggedUser()) {
            return ['status' => false, 'message' => 'Not connected user', 'errorCode' => 0];
        }

        $path = self::CUSTOM_CSS_PRESETS_PATH;
        $filePath = $path . '/' . $filename;
        if ($this->storage->exists($filePath) && !$this->container->get(AclService::class)->isAdmin()) {
            return ['status' => false, 'message' => 'File already existing but user not admin', 'errorCode' => 2];
        }

        $fontCss = '';
        foreach (array_unique(array_filter($fontFamilies)) as $family) {
            $installed = $this->installAndGetCSSForFont($family);
            if (!empty($installed)) {
                $fontCss .= "\n$installed";
            }
        }

        try {
            $this->storage->write($filePath, $fontCss === '' ? $css : $css . "\n$fontCss\n");
        } catch (StorageException) {
            return ['status' => false, 'message' => $filename . ' not created', 'errorCode' => 4];
        }

        return ['status' => true, 'message' => $filename . ' created/updated', 'errorCode' => null];
    }

    /**
     * get presets data.
     *
     * @return array{themePresets: array<string, string>, selectedPresetName: string|null, customCSSPresets: array<string, string>, selectedCustomPresetName: string|null}
     */
    public function getPresetsData(): ?array
    {
        $themePresets = $this->getTemplates()[$this->getFavoriteTheme()]['presets'] ?? [];
        $customCSSPresets = $this->getCustomCSSPresets();
        $favoritePreset = $this->getFavoritePreset();
        $selectedPresetName = null;
        $selectedCustomPresetName = null;
        if (!empty($favoritePreset)) {
            $presetName = $favoritePreset;
            if (str_starts_with($presetName, self::CUSTOM_CSS_PRESETS_PREFIX)) {
                $presetName = substr($presetName, strlen(self::CUSTOM_CSS_PRESETS_PREFIX));
                if (array_key_exists($presetName, $customCSSPresets)) {
                    $selectedCustomPresetName = $presetName;
                }
            } elseif (array_key_exists($presetName, $themePresets)) {
                $selectedPresetName = $presetName;
            }
        }

        return [
            'themePresets' => $themePresets,
            'selectedPresetName' => $selectedPresetName,
            'customCSSPresets' => $customCSSPresets,
            'selectedCustomPresetName' => $selectedCustomPresetName,
        ];
    }

    /**
     * install font and get css.
     *
     * @return string $css
     */
    private function installAndGetCSSForFont(string $fontFamily): string
    {
        if (PresetService::isSystemStack($fontFamily)) {
            return '';
        }

        $fontFamily = $this->cleanFont($fontFamily);
        if (empty($fontFamily)) {
            return '';
        }

        $stored = $this->fontFaces($fontFamily);
        if ($stored !== '') {
            return "\n" . trim($stored);
        }

        $newCss = $this->getFontFiles($fontFamily);
        if (empty($newCss)) {
            return '';
        }
        $this->writeFontFaces($fontFamily, $newCss);

        return "\n$newCss";
    }

    /** Fetch a webfont's files into `custom/fonts/`, from Google or from another YesWiki. */
    /** Fetch a webfont from Google so a Preset can name it. */
    public function installFont(string $family): bool
    {
        $css = $this->getFontFiles($family);
        if ($css === '') {
            return false;
        }
        $this->writeFontFaces($family, $css);

        return true;
    }

    /** Keep a family's `@font-face` rules beside its files. */
    public function writeFontFaces(string $family, string $css): void
    {
        $directory = self::CUSTOM_FONT_PATH . '/' . StringUtilService::asFilename($this->cleanFont($family));
        if ($this->storage->directoryExists($directory)) {
            $this->storage->write($directory . '/' . self::FONT_FACES_FILE, trim($css) . "\n");
        }
    }

    /** A family's stored rules, or '' if it was installed before they were kept. */
    public function fontFaces(string $family): string
    {
        $file = self::CUSTOM_FONT_PATH . '/' . StringUtilService::asFilename($this->cleanFont($family))
            . '/' . self::FONT_FACES_FILE;

        return $this->storage->fileExists($file) ? $this->storage->read($file) : '';
    }

    /**
     * Copy one file another wiki described, keeping the name it is known by there.
     *
     * @param array{family?: mixed, style?: mixed, weight?: mixed, subset?: mixed, unicodeRange?: mixed, url?: mixed} $font
     */
    public function importRemoteFontFile(array $font): ?string
    {
        $url = (string)($font['url'] ?? '');
        if (!preg_match('~^https?://~i', $url) || !str_ends_with(strtolower($url), '.woff2')) {
            return null;
        }

        $local = $this->importFontFile(
            (string)($font['family'] ?? ''),
            (string)($font['style'] ?? 'normal'),
            (string)($font['weight'] ?? '400'),
            (string)($font['subset'] ?? ''),
            $url
        );

        return $local === $url ? null : self::fontFaceRule(
            (string)($font['family'] ?? ''),
            (string)($font['style'] ?? 'normal'),
            (string)($font['weight'] ?? '400'),
            (string)($font['unicodeRange'] ?? ''),
            $local
        );
    }

    /** One `@font-face` block, in the shape Google's own answer has. */
    public static function fontFaceRule(
        string $family,
        string $style,
        string $weight,
        string $unicodeRange,
        string $src
    ): string {
        $rule = "@font-face {\n"
            . "  font-family: '" . str_replace("'", '', $family) . "';\n"
            . '  font-style: ' . ($style !== '' ? $style : 'normal') . ";\n"
            . '  font-weight: ' . ($weight !== '' ? $weight : '400') . ";\n"
            . "  font-display: swap;\n"
            . '  src: url(' . $src . ") format('woff2');\n";
        if ($unicodeRange !== '') {
            $rule .= '  unicode-range: ' . $unicodeRange . ";\n";
        }

        return $rule . "}\n";
    }

    /** GET a URL, or null if it did not answer with a body. */
    public function fetchUrl(string $url): ?string
    {
        return $this->fetch($url);
    }

    /** GET a URL, or null if it did not answer with a body. */
    private function fetch(string $url, string $userAgent = ''): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($userAgent !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/css,*/*;q=0.1']);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $failed = curl_errno($ch);
        curl_close($ch);

        return (!$failed && $status < 400 && is_string($body) && $body !== '') ? $body : null;
    }

    /**
     * Fetch a webfont's woff2 files and return the `@font-face` rules that point at them.
     *
     * @return string the rules, with `src` pointing at the copies now under custom/fonts/
     */
    protected function getFontFiles(string $fontFamily): string
    {
        $family = $this->convertFamilyToUrl($fontFamily);
        if (empty($family)) {
            return '';
        }

        $css = $this->fetch(
            'https://fonts.googleapis.com/css2?family=' . $family
            . ':ital,wght@' . rawurlencode('0,400;0,700;1,400;1,700') . '&display=swap',
            self::BROWSER_USER_AGENT
        );

        return $css === null ? '' : $this->localiseFontFaces($css, $this->cleanFont($fontFamily));
    }

    /** Download each `src` and rewrite it to the local copy. */
    protected function localiseFontFaces(string $css, string $family): string
    {
        $subset = '';

        return (string)preg_replace_callback(
            '~/\*\s*([a-z0-9-]+)\s*\*/|@font-face\s*\{[^}]*\}~i',
            function (array $match) use (&$subset, $family): string {
                if (!str_contains($match[0], '@font-face')) {
                    $subset = $match[1] ?? '';

                    return $match[0];
                }

                $block = $match[0];
                preg_match('/font-style:\s*([a-z]+)/i', $block, $style);
                preg_match('/font-weight:\s*([0-9]+)/i', $block, $weight);
                preg_match('~url\(([^)]+)\)~', $block, $url);
                if (empty($url[1])) {
                    return $block;
                }

                $local = $this->importFontFile(
                    $family,
                    $style[1] ?? 'normal',
                    $weight[1] ?? '400',
                    $subset,
                    trim($url[1], "'\"")
                );

                return str_replace($url[1], $local, $block);
            },
            $css
        ) ?: $css;
    }

    protected function cleanFont(string $fontFamily): string
    {
        $fontFamily = explode(',', $fontFamily)[0];

        return str_replace(
            ['\''],
            [''],
            $fontFamily
        );
    }

    protected function convertFamilyToUrl(string $fontFamily): string
    {
        $fontFamily = $this->cleanFont($fontFamily);

        return str_replace(
            [' '],
            ['+'],
            $fontFamily
        );
    }

    /** Copy one woff2 into `custom/fonts/<family>/` and return the path a rule should use. */
    protected function importFontFile(string $family, string $style, string $weight, string $subset, string $url): string
    {
        $folder = StringUtilService::asFilename($family);
        $directory = self::CUSTOM_FONT_PATH . '/' . $folder;

        $name = StringUtilService::asFilename($family . '-' . $style . '-' . $weight . '-' . $subset) . '.woff2';
        $bytes = $this->fetch($url, self::BROWSER_USER_AGENT);
        if ($bytes === null) {
            return $url;
        }

        try {
            $this->storage->write($directory . '/' . $name, $bytes);
        } catch (StorageException) {
            return $url;
        }

        return '../../' . self::CUSTOM_FONT_PATH . '/' . $folder . '/' . $name;
    }

    /**
     * save metadata for new page if needed.
     */
    public function saveMetadataIfNeeded(Event $event): void
    {
        $data = $event->getData();
        $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
        $post = $request->request;
        $query = $request->query;
        if (!empty($data['data']['tag'])
            && !empty($post->get('newpage'))
            && $post->has('theme')) {
            $tag = $data['data']['tag'];
            $previousMetadata = $this->pageManager->getMetadata($tag);

            $wikiParam = $query->get('wiki');
            $tagIsCurrentPage = (
                !empty($wikiParam)
                && is_string($wikiParam)
                && explode('/', $wikiParam, 2)[0] === $tag
            ) || explode('/', array_key_first($query->all()), 2)[0] === $tag;

            if (empty($previousMetadata)
                && $tagIsCurrentPage) {
                $metadata = [
                    'theme' => $post->get('theme'),
                    'style' => $post->get('style') ?? CSS_PAR_DEFAUT,
                    'squelette' => $post->get('squelette') ?? SQUELETTE_PAR_DEFAUT,
                    'bgimg' => $post->get('bgimg') ?? null,
                ];
                foreach (ThemeManager::SPECIAL_METADATA as $metadataName) {
                    if (!empty($post->get($metadataName))) {
                        $metadata[$metadataName] = $post->get($metadataName);
                    }
                }
                $this->pageManager->setMetadata($tag, $metadata);
            }
        }
    }
}

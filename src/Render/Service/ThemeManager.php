<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\HibernationService;

class ThemeManager implements EventSubscriberInterface
{
    public const CUSTOM_CSS_PRESETS_PATH = 'custom/css-presets';
    public const CUSTOM_CSS_PRESETS_PREFIX = 'custom/';
    public const CUSTOM_FONT_PATH = 'custom/fonts';

    /**
     * What a downloaded family's `@font-face` rules are kept in, beside its files.
     *
     * The rules used to exist in exactly one place: inside a Preset, written when that preset
     * was saved. Which meant a family could be fully downloaded and still be invisible to
     * every browser -- nothing declared it -- so choosing it in the rail previewed nothing at
     * all, and the admin screen had no way to draw a font it had just fetched.
     *
     * Keeping them with the files makes a family self-describing: `unicode-range` and the
     * weight of each file are Google's own answer, recorded once, rather than something to
     * be guessed back out of a file name later.
     */
    public const FONT_FACES_FILE = 'faces.css';

    /**
     * What a page may say about its own chrome, beside the theme it wears.
     *
     * Three page names and a preset. `PageTitre`, `PageMenuHaut` and `PageRapideHaut` were
     * here too until ticket 30 made them configuration: "wear another page's title bar" has
     * no meaning once the title bar is a title, a logo and two lists of links. The override
     * survives for the three items that are still pages, and LayoutService::pageFor() is
     * what reads it back.
     */
    public const SPECIAL_METADATA = [
        'PageFooter',
        'PageHeader',
        'PageMenu',
        'favorite_preset',
    ];
    /**
     * What Google is told we are, so it answers with woff2.
     *
     * `fonts.googleapis.com` serves a different format per User-Agent. This used to be four
     * of them -- IE 3.01 for `eot`, Firefox 3.6 for `woff`, a modern one for `woff2`, none
     * for `ttf` -- fetched in turn. Every browser this release supports takes woff2, so one
     * ordinary Chrome string is the whole table now.
     */
    public const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    protected $errorMessage;
    protected $favorites;
    protected $fileContent;
    protected $fileLoaded;
    protected $pageManager;
    protected $params;
    protected $hibernationService;
    protected $squelette;
    protected $templates;
    protected $theme;
    protected $twig;
    protected $useFallbackTheme;
    protected $utils;
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
        Storage $storage
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

    /* function imported from tooles/templates/libs/templates.functions.php
     * to load templates and generate an error if needed
     *
     * @param $metadata metadata fr the current Page
     * @return array of templates
     */
    public function loadTemplates($metadata = []): ?array
    {
        // Premier cas le template par défaut est forcé : on ajoute ce qui est présent dans le fichier de configuration, ou le theme par defaut précisé ci dessus
        if ($this->params->has('hide_action_template') && $this->params->get('hide_action_template') == '1') {
            $this->setFavorite('theme', $this->getConfigAsStringOrDefault('favorite_theme', THEME_PAR_DEFAUT));
            $this->setFavorite('style', $this->getConfigAsStringOrDefault('favorite_style', CSS_PAR_DEFAUT));
            $this->setFavorite('squelette', $this->getConfigAsStringOrDefault('favorite_squelette', SQUELETTE_PAR_DEFAUT));
            $this->setFavorite('background_image', $this->getConfigAsStringOrDefault('favorite_background_image', BACKGROUND_IMAGE_PAR_DEFAUT));
            $this->setFavorite('preset', $this->getConfigAsStringOrDefault('favorite_preset', ''));
        } else {
            // Sinon, on récupère premièrement les valeurs passées en REQUEST, ou deuxièmement les métasdonnées présentes pour la page, ou troisièmement les valeurs du fichier de configuration
            $requested = [];
            $keysToVerify = ['theme', 'squelette', 'style', 'preset'];
            $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
            foreach ($keysToVerify as $val) {
                $requested[$val] = null;
                $requestVal = $request->get($val);
                if (!empty($requestVal)) {
                    $path = str_replace('custom/', '', $requestVal); // exception for preset paths that may contain custom/<presetname>.css
                    if (preg_match('/\//', $path, $matches)) {
                        exit('ERROR: Suspicious path traversal attempt.');
                    }
                    switch ($val) {
                        case 'theme':
                            $name = basename($requestVal);
                            $customThemePath = $this->storage->directoryExists('custom/themes/' . $name) ? $name : '';
                            $classicThemePath = basename((string)realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requestVal));
                            $requested[$val] = !empty($customThemePath) ? $customThemePath : $classicThemePath;
                            break;

                        case 'squelette':
                            $requestVal = self::normalizeSqueletteName($requestVal);
                            $customPath = basename($requestVal);
                            $classicPath = basename((string)realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/squelettes/' . $requestVal));
                            $requested[$val] = null;
                            if ($this->storage->exists('custom/themes/' . $requested['theme'] . '/squelettes/' . $customPath)) {
                                $requested[$val] = $customPath;
                            } elseif (file_exists(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/squelettes/' . $classicPath)) {
                                $requested[$val] = $classicPath;
                            }
                            if (!preg_match('/\.twig$/i', $requested[$val] ?? '', $matches)) {
                                $requested[$val] = null;
                            }

                            break;

                        default:
                            // ugly append of "s" to get the path of styleS, presetS and squeletteS
                            $customPath = basename($requestVal);
                            $classicPath = basename((string)realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/' . $val . 's/' . $requestVal));
                            $requested[$val] = null;
                            if ($this->storage->exists('custom/themes/' . $requested['theme'] . '/' . $val . 's/' . $customPath)) {
                                $requested[$val] = $customPath;
                            } elseif (file_exists(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/' . $val . 's/' . $classicPath)) {
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

                // presets
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
                                    || is_file(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/presets/' . $requested['preset'])
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
                // si les metas sont présentes on les utilise
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

        // Test existence du template, on utilise le template par defaut sinon==============================
        if (
            (!$this->storage->exists('custom/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette'])
                and !file_exists(YESWIKI_SOURCE_DIR . '/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette']))
            || (!$this->storage->exists('custom/themes/' . $this->favorites['theme'] . '/styles/' . $this->favorites['style'])
                && !file_exists(YESWIKI_SOURCE_DIR . '/themes/' . $this->favorites['theme'] . '/styles/' . $this->favorites['style']))
        ) {
            if (
                $this->favorites['theme'] != THEME_PAR_DEFAUT
                || (
                    $this->favorites['theme'] == THEME_PAR_DEFAUT && (!file_exists(YESWIKI_SOURCE_DIR . '/themes/' . THEME_PAR_DEFAUT . '/squelettes/' . $this->favorites['squelette'])
                        or !file_exists(YESWIKI_SOURCE_DIR . '/themes/' . THEME_PAR_DEFAUT . '/styles/' . $this->favorites['style']))
                )
            ) {
                if (
                    file_exists(YESWIKI_SOURCE_DIR . '/themes/' . THEME_PAR_DEFAUT . '/squelettes/' . SQUELETTE_PAR_DEFAUT)
                    && file_exists(YESWIKI_SOURCE_DIR . '/themes/' . THEME_PAR_DEFAUT . '/styles/' . CSS_PAR_DEFAUT)
                ) {
                    $GLOBALS['template-error']['type'] = 'theme-not-found';
                    $GLOBALS['template-error']['theme'] = $this->favorites['theme'];
                    $GLOBALS['template-error']['style'] = $this->favorites['style'];
                    $GLOBALS['template-error']['squelette'] = $this->favorites['squelette'];
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
        // test l'existence du preset
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

        // themes folder (used by {{update}})
        if (is_dir(YESWIKI_SOURCE_DIR . '/themes')) {
            $this->templates = array_merge($this->templates, $this->utils->searchTemplateFiles(YESWIKI_SOURCE_DIR . '/themes', false));
        }
        // custom themes folder
        if ($this->storage->directoryExists('custom/themes')) {
            $this->templates = array_replace_recursive($this->templates, $this->utils->searchTemplateFiles('custom/themes', true));
        }
        ksort($this->templates);

        return $this->templates;
    }

    public function loadTheme(): bool
    {
        // get theme
        $theme = $this->getFavoriteTheme();
        $theme = empty($theme) ? THEME_PAR_DEFAUT : $theme;

        // get squelette
        $squelette = $this->getFavoriteSquelette();
        $squelette = empty($squelette) ? SQUELETTE_PAR_DEFAUT : $squelette;

        // do not load the file if already loaded
        $fileAlreadyLoaded = $this->fileLoaded
            && ($this->theme == $theme)
            && ($this->squelette == $squelette);
        if ($fileAlreadyLoaded) {
            return true;
        }
        $this->theme = $theme;
        $this->squelette = $squelette;

        // test folder - custom/ belongs to the instance, plain themes/ to the source tree
        $themePath = 'themes/' . $this->theme;
        $filePath = $themePath . '/squelettes/' . $this->squelette;

        if (!((!$this->useFallbackTheme && $this->storage->exists('custom/' . $themePath)) || file_exists(YESWIKI_SOURCE_DIR . '/' . $themePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_THEME_FOLDER') . $this->theme . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }

        if (!((!$this->useFallbackTheme && $this->storage->exists('custom/' . $filePath)) || file_exists(YESWIKI_SOURCE_DIR . '/' . $filePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_SQUELETTE_FILE') . $this->squelette . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }
        $fromInstance = !$this->useFallbackTheme && $this->storage->exists('custom/' . $filePath);
        $filePath = $fromInstance ? 'custom/' . $filePath : YESWIKI_SOURCE_DIR . '/' . $filePath;

        $fileContent = $fromInstance ? $this->storage->read($filePath) : file_get_contents($filePath);
        if ($fileContent === false) {
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
     * A boosted navigation swaps the body only, so two pages that differ in any of these
     * cannot be swapped into one another -- the chrome, the stylesheets and `<html lang>` would
     * all be wrong. BoostedNavigation hashes this into the fingerprint the client echoes back.
     *
     * @return list<string>
     */
    public function layoutIdentity(): array
    {
        // no loadTemplates() call: YesWikiRuntime::loadTemplates() already resolved the
        // favourites for *this* page during boot, from its metadata. Re-running it with no
        // metadata would reset them to the config defaults and hash the wrong skeleton.
        return [
            $this->getFavoriteTheme(),
            $this->getFavoriteSquelette(),
            $this->getFavoriteStyle(),
            $this->getFavoritePreset(),
            $this->getFavoriteBackgroundImage(),
            (string)($GLOBALS['prefered_language'] ?? ''),
        ];
    }

    /**
     * The whole page: the squelette rendered around $pageContent, with its `head` block
     * rendered **last**.
     *
     * Ticket 15. The squelette used to be split on a plain-text `{WIKINI_PAGE}` marker and
     * its two halves rendered either side of the page body, which is why `<head>` could only
     * ever carry the assets registered *before* the page rendered -- i.e. almost none of them.
     * Everything else was flushed at `</body>`, stylesheets included, so a bazar list was
     * painted before its own stylesheet arrived.
     *
     * Rendering `body` first and `head` afterwards inverts that: by the time `<head>` is
     * rendered, every action, every field and every included page has declared what it needs.
     * The layout keeps calling whatever actions it likes -- that freedom is why this is two
     * Twig blocks rather than a fixed set of pre-rendered slots.
     */
    public function renderPage(string $pageContent): string
    {
        if (!$this->fileLoaded && !$this->loadTheme()) {
            return '';
        }
        // no-op on any ordinary page view, where LegacyPageController already registered them
        // before the handler ran. Here as a floor: a caller that forgot would otherwise render
        // a page with no theme stylesheet at all, and assets in a suboptimal cascade order is
        // a far smaller problem than that.
        $this->container->get(CoreAssets::class)->register();

        $boosted = $this->container->get(BoostedNavigation::class);
        $boosted->markPageRendered();

        $template = $this->twig->createSquelette((string)$this->fileContent);

        // flash messages land just before the page content, as they did when they were
        // appended to the header half
        $body = $template->renderBlock('body', [
            'page_content' => Flash::display() . $pageContent,
            'htmx_navigation' => $boosted->isEnabled(),
            'layout_fingerprint' => $boosted->fingerprint(),
            'layout_fingerprint_header' => BoostedNavigation::FINGERPRINT_HEADER,
        ]);

        // in debug mode, what the request cost goes at the foot of whatever is returned --
        // including a boosted fragment, which replaces the body and would otherwise take
        // the previous page's readout away with it and put nothing back
        $debug = $this->container->get(DebugReport::class);

        if ($boosted->isBoosted()) {
            // Ticket 16: the body block *is* the fragment. The title rides along as a
            // top-level element -- htmx applies a fragment's own <title> to the document --
            // and the assets go out of band into <head>, because htmx strips a literal <head>
            // from a fragment response.
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
     * The document head alone, for surfaces that supply their own body -- the iframe handlers,
     * which want the wiki's `<head>` without any of the theme's chrome.
     *
     * Call it *after* the content is built: like renderPage(), it emits the declared assets,
     * and those are only complete once whatever needs them has rendered.
     */
    public function renderHead(): string
    {
        if (!$this->fileLoaded && !$this->loadTheme()) {
            return '';
        }
        $this->container->get(CoreAssets::class)->register();

        return $this->twig->createSquelette((string)$this->fileContent)->renderBlock('head');
    }

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

    protected function setFavorite(string $key, $newVal)
    {
        if ($key === 'squelette' && is_string($newVal)) {
            // stored page metadata and old configs still say e.g. '1col.tpl.html'
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
     * @return array $template = [$filename=>$css]
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
     * @return array ['status' => bool, 'message' => '...']
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
     * The CSS is PresetService's: a Preset is a complete token set in two Colour schemes,
     * which is a shape this service has no business rebuilding from a list of keys. What is
     * left here is what belongs to the instance's filesystem -- the hibernation and admin
     * checks, the directory, and fetching a webfont the preset names so the wiki serves it
     * itself rather than asking Google on every page view.
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
     * Which presets exist and which one the wiki wears. What a preset *contains* is
     * PresetService's answer -- it is a complete token set in two Colour schemes now, not
     * nine values that could be spread over an element's data- attributes.
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
        // A stack of fonts the reader already has is not a webfont, and there is nothing to
        // fetch for it -- asking anyway costs a curl timeout per user-agent string before
        // Google answers with nothing. See PresetService::FONT_STACKS.
        if (PresetService::isSystemStack($fontFamily)) {
            return '';
        }

        $fontFamily = $this->cleanFont($fontFamily);
        if (empty($fontFamily)) {
            return '';
        }

        // Already downloaded: its rules were recorded with its files, and re-fetching them
        // would put a Google round trip in front of every save of every preset naming it --
        // including the offline instance whose fonts were copied in from another wiki.
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

    /**
     * Fetch a webfont's files into `custom/fonts/`, from Google or from another YesWiki.
     *
     * Returns whether anything landed. The files are what matters -- the `@font-face` rules
     * that point at them are written into a Preset when it is saved
     * (installAndGetCSSForFont), so this only has to make the font *available* for the rail
     * to offer and for a preset to name.
     *
     * From another wiki, the files are copied straight across: a YesWiki serves them from
     * `custom/fonts/<family>/`, under names it produced itself, so the same convention reads
     * them back. That path exists for the instance that cannot reach Google at all -- and for
     * not asking Google twice for something you already fetched onto your other wiki.
     */
    /**
     * Fetch a webfont from Google so a Preset can name it. Returns whether anything landed.
     *
     * Only the files matter here -- the `@font-face` rules pointing at them are written into
     * a Preset when it is saved (installAndGetCSSForFont), so this exists to make a family
     * *available* before anything names it.
     */
    public function installFont(string $family): bool
    {
        $css = $this->getFontFiles($family);
        if ($css === '') {
            return false;
        }
        $this->writeFontFaces($family, $css);

        return true;
    }

    /** Keep a family's `@font-face` rules beside its files. See FONT_FACES_FILE. */
    public function writeFontFaces(string $family, string $css): void
    {
        $directory = self::CUSTOM_FONT_PATH . '/' . sanitizeFilename($this->cleanFont($family));
        if ($this->storage->directoryExists($directory)) {
            $this->storage->write($directory . '/' . self::FONT_FACES_FILE, trim($css) . "\n");
        }
    }

    /** A family's stored rules, or '' if it was installed before they were kept. */
    public function fontFaces(string $family): string
    {
        $file = self::CUSTOM_FONT_PATH . '/' . sanitizeFilename($this->cleanFont($family))
            . '/' . self::FONT_FACES_FILE;

        return $this->storage->fileExists($file) ? $this->storage->read($file) : '';
    }

    /**
     * Copy one file another wiki described, keeping the name it is known by there.
     *
     * The descriptor comes from that wiki's `/api/presets/fonts`, which reads it out of its
     * own preset -- so style, weight and subset are facts rather than a convention guessed
     * from a file name, and every weight of the family comes across rather than the one an
     * old fetcher happened to have.
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

        // importFontFile hands back the URL unchanged when it could not write
        return $local === $url ? null : self::fontFaceRule(
            (string)($font['family'] ?? ''),
            (string)($font['style'] ?? 'normal'),
            (string)($font['weight'] ?? '400'),
            (string)($font['unicodeRange'] ?? ''),
            $local
        );
    }

    /**
     * One `@font-face` block, in the shape Google's own answer has.
     *
     * Used for the copied-from-another-wiki path, where there is no CSS to localise: that
     * wiki answered with the descriptors instead, and they have to come back out as rules a
     * browser can read. Same text either way, so a family reads the same however it arrived.
     */
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

    /** GET a URL, or null if it did not answer with a body. Public for the preset importer. */
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
     * **woff2 only, and every weight the family has.** This used to ask Google's old `css`
     * endpoint four times over, spoofing IE 3.01, Firefox 3.6 and a modern Firefox in turn to
     * be served `eot`, `woff`, `woff2` and `ttf` -- four round trips and four copies on disk,
     * three of them formats no browser this release supports will ever ask for. Worse, that
     * endpoint answers with the regular face and nothing else, so a wiki using Nunito had no
     * bold at all and every bold heading was a shape the browser smeared on its own.
     *
     * `css2` with an ordinary browser User-Agent answers woff2 only, and takes an axis: this
     * asks for regular and bold, upright and italic. Verified against the live API -- a family
     * that has none of the extras still answers 200 with what it does have, so there is
     * nothing to fall back to and no error to handle.
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

    /**
     * Download each `src` and rewrite it to the local copy.
     *
     * The subset each block covers is the comment above it (`/* latin-ext *``/`), which is the
     * only place Google names it -- and it has to reach the file name, because the blocks are
     * otherwise identical and would overwrite each other.
     */
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

    /**
     * Copy one woff2 into `custom/fonts/<family>/` and return the path a rule should use.
     *
     * The name carries style, weight and subset because that is what distinguishes the files:
     * a family arrives as a dozen blocks that differ only in those three, and a name built
     * from the family alone would leave one file and eleven overwrites.
     *
     * On failure the remote URL is returned unchanged, so the rule still works -- the font is
     * then served by Google rather than by this wiki, which is worse but not broken.
     */
    protected function importFontFile(string $family, string $style, string $weight, string $subset, string $url): string
    {
        $folder = sanitizeFilename($family);
        $directory = self::CUSTOM_FONT_PATH . '/' . $folder;

        $name = sanitizeFilename($family . '-' . $style . '-' . $weight . '-' . $subset) . '.woff2';
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
    public function saveMetadataIfNeeded(Event $event)
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

            if (empty($previousMetadata) // only if no previous metadata
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

<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
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
    public const SPECIAL_METADATA = [
        'PageFooter',
        'PageHeader',
        'PageTitre',
        'PageRapideHaut',
        'PageMenuHaut',
        'PageMenu',
        'favorite_preset',
    ];
    public const USER_AGENTS = [
        'eot' => 'Mozilla/2.0 (compatible; MSIE 3.01; Windows 98)',
        'woff' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; fr; rv:1.9.2) Gecko/20100115 Firefox/3.6',
        'woff2' => 'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0',
        'truetype' => '',
    ];

    private const POST_DATA_KEYS = [
        'primary-color',
        'secondary-color-1',
        'secondary-color-2',
        'neutral-color',
        'neutral-soft-color',
        'neutral-light-color',
        'main-text-fontsize',
        'main-text-fontfamily',
        'main-title-fontfamily',
    ];

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
        TemplateHelperService $utils
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
                            $customThemePath = basename(realpath(getcwd() . '/custom/themes/' . $requestVal));
                            $classicThemePath = basename(realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requestVal));
                            $requested[$val] = !empty($customThemePath) ? $customThemePath : $classicThemePath;
                            break;

                        case 'squelette':
                            $requestVal = self::normalizeSqueletteName($requestVal);
                            $customPath = basename(realpath(getcwd() . '/custom/themes/' . $requested['theme'] . '/squelettes/' . $requestVal));
                            $classicPath = basename(realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/squelettes/' . $requestVal));
                            $requested[$val] = null;
                            if (!empty($customPath) && file_exists(getcwd() . '/custom/themes/' . $requested['theme'] . '/squelettes/' . $customPath)) {
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
                            $customPath = basename(realpath(getcwd() . '/custom/themes/' . $requested['theme'] . '/' . $val . 's/' . $requestVal));
                            $classicPath = basename(realpath(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/' . $val . 's/' . $requestVal));
                            $requested[$val] = null;
                            if (!empty($customPath) && file_exists(getcwd() . '/custom/themes/' . $requested['theme'] . '/' . $val . 's/' . $customPath)) {
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
                                && is_file(self::CUSTOM_CSS_PRESETS_PATH . '/' . substr($requested['preset'], strlen(self::CUSTOM_CSS_PRESETS_PREFIX)))
                            )
                            || (
                                !$isCustom
                                && (
                                    is_file('custom/themes/' . $requested['theme'] . '/presets/' . $requested['preset'])
                                    || is_file(YESWIKI_SOURCE_DIR . '/themes/' . $requested['theme'] . '/presets/' . $requested['preset'])
                                )
                            )
                        )
                ) {
                    $this->setFavorite('preset', $requested['preset']);
                }

                $bgimg = $request->get('bgimg');
                if (!empty($bgimg) && is_file('files/backgrounds/' . $bgimg)) {
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
            (!file_exists('custom/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette'])
                and !file_exists(YESWIKI_SOURCE_DIR . '/themes/' . $this->favorites['theme'] . '/squelettes/' . $this->favorites['squelette']))
            || (!file_exists('custom/themes/' . $this->favorites['theme'] . '/styles/' . $this->favorites['style'])
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
                    && !file_exists(self::CUSTOM_CSS_PRESETS_PATH . DIRECTORY_SEPARATOR
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
        if (is_dir('custom/themes')) {
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

        if (!((!$this->useFallbackTheme && file_exists('custom/' . $themePath)) || file_exists(YESWIKI_SOURCE_DIR . '/' . $themePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_THEME_FOLDER') . $this->theme . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }

        if (!((!$this->useFallbackTheme && file_exists('custom/' . $filePath)) || file_exists(YESWIKI_SOURCE_DIR . '/' . $filePath))) {
            $this->errorMessage = $this->twig->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_SQUELETTE_FILE') . $this->squelette . _t('THEME_MANAGER_NOT_FOUND'),
            ]);

            return false;
        }
        $filePath = (!$this->useFallbackTheme && file_exists('custom/' . $filePath)) ? 'custom/' . $filePath : YESWIKI_SOURCE_DIR . '/' . $filePath;

        $fileContent = file_get_contents($filePath);
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

        if ($boosted->isBoosted()) {
            // Ticket 16: the body block *is* the fragment. The title rides along as a
            // top-level element -- htmx applies a fragment's own <title> to the document --
            // and the assets go out of band into <head>, because htmx strips a literal <head>
            // from a fragment response.
            return $this->twig->render('@core/_boosted-title.twig', ['title' => $this->pageTitle()])
                . $body
                . $this->container->get(AssetRegistry::class)->drain()->toOutOfBandHtml();
        }

        return $template->renderBlock('head') . $body;
    }

    /** The document title, rendered the same way the head block does it. */
    private function pageTitle(): string
    {
        $runner = $this->container->get(ActionRunner::class);

        return trim($runner->action('configuration param="wakka_name"') . ' : ' . $runner->action('titrepage'));
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
        $cssFiles = glob($path . DIRECTORY_SEPARATOR . '*.css');
        foreach ($cssFiles as $filepath) {
            $filename = pathinfo($filepath)['filename'] . '.css';
            $css = file_get_contents($filepath);
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
        if (!file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            return ['status' => false, 'message' => 'File ' . $filename . ' is not existing !'];
        }
        unlink($path . DIRECTORY_SEPARATOR . $filename);
        if (!file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            return ['status' => true, 'message' => ''];
        }

        return ['status' => false, 'message' => 'Not possible to delete ' . $filename];
    }

    /**
     * add a css custom preset (only admins can change a file).
     *
     * @return array ['status' => bool, 'message' => '...','errorCode'=>0]
     *               errorCode : 0 : not connected user
     *               1 : bad post data
     *               2 : file already existing but user not admin
     *               3 : custom/css-presets not existing and not possible to create it
     *               4 : file not created
     */
    public function addCustomCSSPreset(string $filename, array $post): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->container->get(AuthenticationService::class)->getLoggedUser()) {
            return ['status' => false, 'message' => 'Not connected user', 'errorCode' => 0];
        }

        if (!$this->checkPOSTToAddCustomCSSPreset($post)) {
            return ['status' => false, 'message' => 'Bad post data', 'errorCode' => 1];
        }
        $path = self::CUSTOM_CSS_PRESETS_PATH;

        $fileContent = ":root {\r\n";
        foreach (self::POST_DATA_KEYS as $key) {
            $fileContent .= '  --' . $key . ': ' . $post[$key] . ";\r\n";
        }
        $fileContent .= "}\r\n";

        if (file_exists($path . DIRECTORY_SEPARATOR . $filename) && !$this->container->get(AclService::class)->isAdmin()) {
            return ['status' => false, 'message' => 'File already existing but user not admin', 'errorCode' => 2];
        }
        // check if folder exists
        if (!is_dir($path)) {
            if (!mkdir($path)) {
                return ['status' => false, 'message' => $path . ' not existing and not possible to create it', 'errorCode' => 3];
            }
        }
        // create or update
        file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $fileContent);
        $data = (file_exists($path . DIRECTORY_SEPARATOR . $filename))
            ? ['status' => true, 'message' => $filename . ' created/updated', 'errorCode' => null]
            : ['status' => false, 'message' => $filename . ' not created', 'errorCode' => 4];

        $filePath = self::CUSTOM_CSS_PRESETS_PATH . DIRECTORY_SEPARATOR . $filename;
        if ($data['status'] && file_exists($filePath)) {
            // append font data

            $mainTextFontFamily = (empty($post['main-text-fontfamily']) || !is_string($post['main-text-fontfamily'])) ? '' : $post['main-text-fontfamily'];
            $fontString = empty($mainTextFontFamily) ? '' : $this->installAndGetCSSForFont($mainTextFontFamily);

            $mainTitleFontFamily = (empty($post['main-title-fontfamily']) || !is_string($post['main-title-fontfamily'])) ? '' : $post['main-title-fontfamily'];
            if ($mainTitleFontFamily != $mainTextFontFamily) {
                $newFontString = empty($mainTitleFontFamily) ? '' : $this->installAndGetCSSForFont($mainTitleFontFamily);
                if (!empty($newFontString)) {
                    $fontString .= "\n$newFontString";
                }
            }

            if (!empty($fontString)) {
                file_put_contents($filePath, "\n$fontString\n", FILE_APPEND);
            }
        }

        return $data;
    }

    /**
     * check post to add custom CSS Preset.
     */
    private function checkPOSTToAddCustomCSSPreset(array $post): bool
    {
        foreach (self::POST_DATA_KEYS as $key) {
            if (empty($post[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * get presets data.
     *
     * @return array
     *               [
     *               'themePresets' => [],
     *               'dataHtmlForPresets' => [],
     *               'selectedPresetName' => ''/null,
     *               'customCSSPresets' => [],
     *               'dataHtmlForCustomCSSPresets' => [],
     *               'selectedCustomPresetName' => ''/null,
     *               'currentCSSValues' => [],
     *               ]
     */
    public function getPresetsData(): ?array
    {
        $themePresets = $this->getTemplates()[$this->getFavoriteTheme()]['presets'] ?? [];
        $dataHtmlForPresets = array_map(function ($value) {
            return $this->extractDataFromPreset($value);
        }, $themePresets);
        $customCSSPresets = $this->getCustomCSSPresets();
        $dataHtmlForCustomCSSPresets = array_map(function ($value) {
            return $this->extractDataFromPreset($value);
        }, $customCSSPresets);
        $favoritePreset = $this->getFavoritePreset();
        if (!empty($favoritePreset)) {
            $presetName = $favoritePreset;
            if (substr($presetName, 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX) {
                $presetName = substr($presetName, strlen(self::CUSTOM_CSS_PRESETS_PREFIX));
                if (in_array($presetName, array_keys($customCSSPresets))) {
                    $currentCSSValues = $this->extractPropValuesFromPreset($customCSSPresets[$presetName]);
                    $selectedCustomPresetName = $presetName;
                }
            } else {
                if (in_array($presetName, array_keys($themePresets))) {
                    $currentCSSValues = $this->extractPropValuesFromPreset($themePresets[$presetName]);
                    $selectedPresetName = $presetName;
                }
            }
        }

        return [
            'themePresets' => $themePresets,
            'dataHtmlForPresets' => $dataHtmlForPresets,
            'selectedPresetName' => $selectedPresetName ?? null,
            'customCSSPresets' => $customCSSPresets,
            'dataHtmlForCustomCSSPresets' => $dataHtmlForCustomCSSPresets,
            'selectedCustomPresetName' => $selectedCustomPresetName ?? null,
            'currentCSSValues' => $currentCSSValues ?? [],
        ];
    }

    /**
     * extract data from preset.
     *
     * @return string data to put in html
     */
    private function extractDataFromPreset(string $presetContent): string
    {
        $data = '';
        $values = $this->extractPropValuesFromPreset($presetContent);
        foreach ($values as $prop => $value) {
            $data .= ' data-' . $prop . '="' . str_replace('"', '\'', $value) . '"';
        }
        if (
            !empty($data)
            && !empty($values['primary-color'])
            && !empty($values['main-text-fontsize']
            && !empty($values['main-text-fontfamily']))
        ) {
            $data .= ' style="';
            $data .= 'color:' . $values['primary-color'] . ';';
            $data .= 'font-family:' . str_replace('"', '\'', $values['main-text-fontfamily']) . ';';
            $data .= 'font-size:' . $values['main-text-fontsize'] . ';';
            $data .= '"';
        }

        return $data;
    }

    /**
     * extract properties values from preset contents.
     */
    private function extractPropValuesFromPreset(string $presetContent): array
    {
        // extract root part
        $matches = [];
        $results = [];
        $error = false;
        if (preg_match('/^:root\s*{((?:.|\n)*)}\s*[^{]*/', $presetContent, $matches)) {
            $vars = $matches[1];

            if (preg_match_all('/\s*--([0-9a-z\-]*):\s*([^;]*);\s*/', $vars, $matches)) {
                foreach ($matches[0] as $index => $val) {
                    $newmatch = [];
                    if (preg_match('/[a-z\-]*color[a-z0-9\-]*/', $matches[1][$index], $newmatch)) {
                        if (!preg_match('/^#[A-Fa-f0-9]*$/', $matches[2][$index], $newmatch)) {
                            $error = true;
                        }
                    }
                    $results[$matches[1][$index]] = $matches[2][$index];
                }
            }
        }

        return $error ? [] : $results;
    }

    /**
     * install font and get css.
     *
     * @return string $css
     */
    private function installAndGetCSSForFont(string $fontFamily): string
    {
        $css = '';
        $fontFamily = $this->cleanFont($fontFamily);
        if (!empty($fontFamily)) {
            $newCss = $this->getFontFiles($fontFamily);
            if (!empty($newCss)) {
                $css .= "\n$newCss";
            }
        }

        return $css;
    }

    protected function getFontFiles(string $fontFamily): string
    {
        $css = '';

        $fontFamilyForUrl = $this->convertFamilyToUrl($fontFamily);
        if (!empty($fontFamilyForUrl)) {
            $data = [];
            foreach (self::USER_AGENTS as $name => $value) {
                $data[$name] = $this->getFontDescription($fontFamilyForUrl, $name);
                if (empty($data[$name])) {
                    unset($data[$name]);
                }
            }
            $css = $this->formatCSS($data);
        }

        return $css;
    }

    protected function getFontDescription(string $fontFamily, string $userAgent): array
    {
        $data = [];
        $ch = curl_init("https://fonts.googleapis.com/css?family=$fontFamily&subset=latin-ext");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $headers = ['Accept: text/css,*/*;q=0.1'];
        if (!empty(self::USER_AGENTS[$userAgent])) {
            $headers[] = 'User-Agent: ' . self::USER_AGENTS[$userAgent];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $errorNb = curl_errno($ch);
        curl_close($ch);
        if (!$errorNb) {
            $data = $this->parseCSS($result);
        }

        return $data;
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

    protected function parseCSS(string $css): array
    {
        $data = [];
        $this->parseFontFace($css, '', $data);
        $this->parseFontFace($css, 'latin', $data);
        $this->parseFontFace($css, 'latin-ext', $data);

        return $data;
    }

    protected function parseFontFace(string $css, string $subset, array &$data)
    {
        $formattedSubSet = empty($subset) ? '' : "\/\*\s*" . preg_quote($subset, '/') . "\s*\*\/\s*";
        if (preg_match("/$formattedSubSet@font-face \{([^}]*)src: url\((https:\/\/fonts\.gstatic\.com\/[A-Za-z0-9_\-.\/]+)\)(?: format\('([A-Za-z0-9 \-_]+)'\))?([^}]*)\}/", $css, $match)) {
            $format = empty($match[3]) ? 'eot' : $match[3];
            $data[$subset] = [
                'url' => [
                    $format => $match[2],
                ],
            ];
            if (preg_match("/font-family: '([A-Za-z0-9 ]+)';/", $match[1], $familyMatch)) {
                $data[$subset]['family'] = $familyMatch[1];
            }
            if (preg_match('/font-style: ([A-Za-z0-9 ]+);/', $match[1], $styleMatch)) {
                $data[$subset]['style'] = $styleMatch[1];
            }
            if (preg_match('/font-weight: ([A-Za-z0-9 ]+);/', $match[1], $weightMatch)) {
                $data[$subset]['weight'] = $weightMatch[1];
            }
            if (preg_match("/unicode-range: ([A-Za-z0-9 \+,\-]+);/", $match[4], $rangeMatch)) {
                $data[$subset]['unicode-range'] = $rangeMatch[1];
            }
        }
    }

    protected function formatCSS(array $data): string
    {
        $css = '';
        $formattedData = [];
        foreach ($data as $userAgent => $values) {
            foreach ($values as $charset => $raw) {
                if (!empty($raw['family']) && !empty($raw['style']) && !empty($raw['weight'])) {
                    $key = "{$raw['family']}-{$raw['style']}-{$raw['weight']}";
                    if (!isset($formattedData[$key])) {
                        $formattedData[$key] = [
                            'family' => $raw['family'],
                            'style' => $raw['style'],
                            'weight' => $raw['weight'],
                            'charsets' => [],
                        ];
                    }
                    foreach ($raw['url'] as $format => $url) {
                        if (!isset($formattedData[$key]['charsets'][$charset])) {
                            $formattedData[$key]['charsets'][$charset] = [
                                'url' => [],
                            ];
                        }
                        if (isset($raw['unicode-range'])
                            && !isset($formattedData[$key]['charsets'][$charset]['unicode-range'])) {
                            $formattedData[$key]['charsets'][$charset]['unicode-range'] = $raw['unicode-range'];
                        }
                        if (!isset($formattedData[$key]['charsets'][$charset]['url'][$format])) {
                            $formattedData[$key]['charsets'][$charset]['url'][$format] = $url;
                        }
                    }
                }
            }
        }
        if (!empty($formattedData)) {
            foreach ($formattedData as $raw) {
                foreach ($raw['charsets'] as $charset => $val) {
                    $eotUrl = $val['url']['eot'] ?? '';
                    $woff2Url = $val['url']['woff2'] ?? '';
                    $woffUrl = $val['url']['woff'] ?? '';
                    $truetypeUrl = $val['url']['truetype'] ?? '';
                    if (!empty($eotUrl)) {
                        $eotUrl = $this->importFontFile(
                            $raw['family'],
                            $raw['style'],
                            $raw['weight'],
                            $charset,
                            'eot',
                            $eotUrl
                        );
                        $eotUrl = "\n  src: url('$eotUrl');";
                    }
                    foreach (['woff2', 'woff', 'truetype'] as $name) {
                        $varName = "{$name}Url";
                        $var = ${$varName};
                        if (!empty($var)) {
                            $var = $this->importFontFile(
                                $raw['family'],
                                $raw['style'],
                                $raw['weight'],
                                $charset,
                                $name,
                                $var
                            );
                            ${$varName} = ",\n        url('$var') format('$name')";
                        }
                    }
                    $unicodeRange = $val['unicode-range'] ?? '';
                    if (!empty($unicodeRange)) {
                        $unicodeRange = "\n  unicode-range: $unicodeRange;";
                    }

                    if (!empty($charset)) {
                        $css .=
                        <<<CSS

                        /* $charset */

                        CSS;
                    }

                    $css .=
                    <<<CSS
                    @font-face {
                      font-family: '{$raw['family']}';
                      font-style: {$raw['style']};
                      font-weight: {$raw['weight']};$eotUrl
                      src: local('')$woff2Url$woffUrl$truetypeUrl;$unicodeRange
                    }
                    CSS;
                }
            }
        }

        return $css;
    }

    protected function importFontFile(string $family, string $style, string $weight, string $charset, string $format, string $url): string
    {
        $folderSystemName = sanitizeFilename($family);
        if (!is_dir(self::CUSTOM_FONT_PATH . "/$folderSystemName")) {
            mkdir(self::CUSTOM_FONT_PATH . "/$folderSystemName", 0777, true);
        }

        switch ($format) {
            case 'eot':
                $ext = '.eot';
                break;
            case 'woff2':
                $ext = '.woff2';
                break;
            case 'woff':
                $ext = '.woff';
                break;
            case 'truetype':
                $ext = '.ttf';
                break;

            default:
                $ext = '';
                break;
        }
        $fileName = sanitizeFilename("$family-$style-$weight-$charset") . $ext;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $result = curl_exec($ch);
        $errorNb = curl_errno($ch);
        curl_close($ch);
        if (!$errorNb && !empty($result)) {
            if (file_put_contents(self::CUSTOM_FONT_PATH . "/$folderSystemName/$fileName", $result)
                && file_exists(self::CUSTOM_FONT_PATH . "/$folderSystemName/$fileName")) {
                return '../../' . self::CUSTOM_FONT_PATH . "/$folderSystemName/$fileName";
            }
        }

        return $url;
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

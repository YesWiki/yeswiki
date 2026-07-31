<?php

namespace YesWiki\Render\Service;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Declares the assets every page needs: core, theme, and the instance's own custom/ files.
 *
 * Ticket 15 moved this out of `{{linkstyle}}` and `{{linkjavascript}}`. Those actions each did
 * two jobs -- register, then flush -- and the flush died with them once the skeleton grew a
 * single emission point. The registration could not stay in the layout either: the head is now
 * rendered *last*, so anything the layout registers would arrive after the assets were emitted.
 *
 * Running it from the render pipeline instead makes "core assets come first" a property of the
 * pipeline rather than a convention every squelette has to uphold -- and a theme can no longer
 * break a page by omitting a call it did not know was load-bearing.
 *
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
class CoreAssets
{
    private bool $registered = false;

    public function __construct(
        private readonly AssetRegistry $assets,
        private readonly ThemeManager $themeManager,
        private readonly PageManager $pageManager,
        private readonly RuntimeConfig $config,
        private readonly PageContext $pageContext,
        private readonly CsrfTokenManager $csrfTokenManager,
    ) {
    }

    /**
     * Declared in the order they must load. Idempotent: several surfaces render a page in one
     * request (a handler inside a handler), and the second call must not re-declare anything.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->registerStyles();
        $this->registerScripts();
    }

    private function registerStyles(): void
    {
        // ticket 16: Bootstrap CSS is not loaded anymore -- yw-core.css (base styles
        // + the yw-* design system, ADR-0004) is the only core-provided styling
        $this->assets->addCssFile('styles/yw-core.css');

        $theme = $this->themeManager->getFavoriteTheme();
        $favoriteStyle = $this->themeManager->getFavoriteStyle();

        $favoritePreset = $this->themeManager->getFavoritePreset();
        $presetsActivated = !empty($this->themeManager->getTemplates()[$theme]['presets']) && !empty($favoritePreset);
        $presetIsCustom = false;
        $presetFile = '';
        if ($presetsActivated) {
            $customPrefix = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX;
            $presetIsCustom = str_starts_with($favoritePreset, $customPrefix);
            $presetFile = $presetIsCustom
                ? ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . substr($favoritePreset, strlen($customPrefix))
                : 'themes/' . $theme . '/presets/' . $favoritePreset;
        }

        // custom/ belongs to the instance and shadows the source tree
        $styleFile = 'themes/' . $theme . '/styles/' . $favoriteStyle;
        if (file_exists('custom/' . $styleFile)) {
            $styleFile = 'custom/' . $styleFile;
        }
        if ($presetsActivated && !$presetIsCustom && file_exists('custom/' . $presetFile)) {
            $presetFile = 'custom/' . $presetFile;
        }

        if ($favoriteStyle !== 'none' && str_ends_with($favoriteStyle, '.css')) {
            $this->assets->addCssFile($styleFile, '', '', 'id="mainstyle"');
        }
        if ($favoriteStyle !== 'none' && $presetsActivated && str_ends_with($favoritePreset, '.css')) {
            $this->assets->addCssFile($presetFile);
        }

        // css files in the instance's custom styles directory
        foreach ($this->filesIn('custom/styles', '.css') as $file) {
            $this->assets->addCssFile($file);
        }

        // relocated from the per-tool linkstyle__.php hooks as each tool became core:
        // the tag cloud and rss icon (ticket 10), attached-file display (ticket 17), and
        // bazar entries / calendar / map display (ticket 24)
        $this->assets->addCssFile('styles/actions/tags-nuage.css');
        $this->assets->addCssFile('styles/actions/attach.css');
        $this->assets->addCssFile('styles/bazar/bazar.css');

        $this->registerBackgroundImage();

        // if it exists and is not empty, the 'PageCss' wiki page's content is added to the
        // styles. Inlined rather than linked from the /raw handler: raw serves text/plain,
        // and browsers in standards mode reject a stylesheet link that is not text/css.
        $pageCss = $this->pageManager->getOne('PageCss');
        if ($pageCss && !empty($pageCss['body'])) {
            $this->assets->addCss(PageBody::content($pageCss['body']));
        }
    }

    private function registerBackgroundImage(): void
    {
        $image = $this->themeManager->getFavoriteBackgroundImage();
        if (empty($image)) {
            return;
        }

        $extension = strtolower(substr($image, -4, 4));
        if ($extension === '.jpg') {
            $this->assets->addCss(<<<CSS
                body {
                    background-image: url("files/backgrounds/$image");
                    background-repeat:no-repeat;
                    height:100%;
                    -webkit-background-size:cover;
                    -moz-background-size:cover;
                    -o-background-size:cover;
                    background-size:cover;
                    background-attachment:fixed;
                    background-clip:border-box;
                    background-origin:padding-box;
                    background-position:center center;
                }
                CSS);
        } elseif ($extension === '.png') {
            $this->assets->addCss(<<<CSS
                body {
                    background-image: url("files/backgrounds/$image");
                }
                CSS);
        }
    }

    private function registerScripts(): void
    {
        // ticket 14: the one initialisation convention, and the browser half of declared
        // assets. Both are `first` -- emitted before every deferred script and every module,
        // because ~25 initialisers call ywInit()/ywInitEach() at their top level and inline
        // page markup calls _t()/wiki.url() at parse time.
        $this->assets->addJsFile('javascripts/yw-init.js', true);
        // the props object before yeswiki-base-no-defer.js, as it was when both were echoed
        // by {{linkjavascript}}: _t() reads wiki.lang
        $this->assets->addJs($this->wikiGlobalScript(), false, true);
        $this->assets->addJsFile('javascripts/yeswiki-base-no-defer.js', true);

        // the theme's own scripts, alphabetically, as the directory scan always did
        $themeJsDir = 'themes/' . $this->themeManager->getFavoriteTheme() . '/javascripts';
        if (!$this->themeManager->getUseFallbackTheme() && is_dir('custom/' . $themeJsDir)) {
            $themeJsDir = 'custom/' . $themeJsDir;
        }
        $themeScripts = $this->filesIn($themeJsDir, '.js');
        sort($themeScripts);
        $themeShipsYesWikiJs = false;
        foreach ($themeScripts as $script) {
            $this->assets->addJsFile($script);
            if (str_contains(basename($script), 'yeswiki.') || str_contains(basename($script), 'yw.')) {
                $themeShipsYesWikiJs = true;
            }
        }
        if (!$themeShipsYesWikiJs) {
            $this->assets->addJsFile('javascripts/yeswiki-base.js');
        }

        // htmx + yw-* core design system (ADR-0004/0005): loaded on every page so core
        // interactions can rely on them without each surface opting in itself
        $this->assets->addJsFile('javascripts/vendor/htmx/htmx.min.js');
        $this->assets->addJsFile('javascripts/yw-assets.js');
        $this->assets->addJsFile('javascripts/yw-core.js');
        $this->assets->addJsFile('javascripts/yw-datatable.js');
        $this->assets->addJsFile('javascripts/yw-autocomplete.js');

        foreach ($this->filesIn('custom/javascripts', '.js') as $file) {
            $this->assets->addJsFile($file);
        }
    }

    /**
     * The `wiki` global every script reads. Inline and `first`, so it is defined before the
     * deferred scripts that use it; ticket 16 will swap `pageTag` and the CSRF token
     * out-of-band on a boosted navigation rather than re-rendering the page.
     */
    private function wikiGlobalScript(): string
    {
        $props = [
            'locale' => $GLOBALS['prefered_language'],
            'timezone' => date_default_timezone_get(),
            'baseUrl' => $this->config['base_url'],
            'pageTag' => $this->pageContext->getTag(),
            'isDebugEnabled' => ($this->config->getValue('debug') ? 'true' : 'false'),
            'antiCsrfToken' => $this->csrfTokenManager->getToken('main')->getValue(),
        ];
        $minSearchKeywordLength = isset($this->config['min_search_keyword_length'])
            ? (int)$this->config['min_search_keyword_length']
            : MIN_SEARCH_KEYWORD_LENGTH;

        return 'var wiki = {'
            . "...((typeof wiki !== 'undefined') ? wiki : null),"
            . '...' . json_encode($props) . ','
            . '...{lang: {'
            . "...((typeof wiki !== 'undefined') ? (wiki.lang ?? null) : null),"
            . '...' . json_encode($GLOBALS['translations_js'] ?? null)
            . '}},'
            . '...{minSearchKeywordLength: ' . $minSearchKeywordLength . '}'
            . '};';
    }

    /** @return list<string> paths of $extension files directly in $dir, or [] when it is absent */
    private function filesIn(string $dir, string $extension): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $found = [];
        foreach ((array)scandir($dir) as $file) {
            if (is_string($file) && str_ends_with($file, $extension)) {
                $found[] = $dir . '/' . $file;
            }
        }

        return $found;
    }
}

<?php

namespace YesWiki\Render\Service;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RequestScopedState;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Declares the assets every page needs: core, theme, and the instance's own custom/ files.
 *
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
class CoreAssets implements RequestScopedState
{
    private bool $registered = false;

    public function __construct(
        private readonly AssetRegistry $assets,
        private readonly ThemeManager $themeManager,
        private readonly CustomCssService $customCss,
        private readonly RuntimeConfig $config,
        private readonly PageContext $pageContext,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly LanguageService $languages,
        private readonly Storage $storage,
        private readonly ProgramFiles $programFiles,
    ) {
    }

    /** Declared in the order they must load. */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->csrfTokenManager->getToken('main');

        $this->registerColourScheme();
        $this->registerStyles();
        $this->registerScripts();
    }

    /** The viewer's Colour scheme, applied before anything is painted (ADR-0020). */
    private function registerColourScheme(): void
    {
        $this->assets->addJs(<<<'JS'
            (function () {
              var KEY = 'yw-scheme';
              var root = document.documentElement;
              function read() {
                // localStorage throws rather than returning null in a locked-down browser
                // (private mode, third-party-cookie blocking in a frame), and a wiki that
                // cannot remember a preference must still render
                try {
                  return window.localStorage.getItem(KEY) || 'system';
                } catch (e) {
                  return 'system';
                }
              }
              function apply(scheme) {
                if (scheme === 'light' || scheme === 'dark') {
                  root.setAttribute('data-theme', scheme);
                } else {
                  root.removeAttribute('data-theme');
                }
              }
              apply(read());
              window.ywScheme = {
                current: read,
                set: function (scheme) {
                  try {
                    if (scheme === 'system') window.localStorage.removeItem(KEY);
                    else window.localStorage.setItem(KEY, scheme);
                  } catch (e) {
                    // the choice is lost on the next page; the current one still changes
                  }
                  apply(scheme);
                  document.dispatchEvent(new CustomEvent('yw:scheme', { detail: { scheme: scheme } }));
                }
              };
            })();
            JS, false, true);
    }

    private function registerStyles(): void
    {
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

        $styleFile = 'themes/' . $theme . '/styles/' . $favoriteStyle;
        if ($this->storage->exists('custom/' . $styleFile)) {
            $styleFile = 'custom/' . $styleFile;
        }
        if ($presetsActivated && !$presetIsCustom && $this->storage->exists('custom/' . $presetFile)) {
            $presetFile = 'custom/' . $presetFile;
        }

        if ($favoriteStyle !== 'none' && str_ends_with($favoriteStyle, '.css')) {
            $this->assets->addCssFile($styleFile, '', '', 'id="mainstyle"');
        }
        if ($favoriteStyle !== 'none' && $presetsActivated && str_ends_with($favoritePreset, '.css')) {
            $this->assets->addCssFile($presetFile, '', '', 'id="wikipreset"');
        }

        $customCss = $this->customCss->path();
        foreach ($this->filesInInstance(CustomCssService::DIRECTORY, '.css') as $file) {
            if ($file !== $customCss) {
                $this->assets->addCssFile($file);
            }
        }

        $this->registerBackgroundImage();

        if ($this->customCss->exists()) {
            $this->assets->addCssFile($customCss);
        }
    }

    private function registerBackgroundImage(): void
    {
        $image = $this->themeManager->getFavoriteBackgroundImage();
        if (empty($image)) {
            return;
        }

        $url = $this->storage->url('files/backgrounds/' . $image);
        $extension = strtolower(substr($image, -4, 4));
        if ($extension === '.jpg') {
            $this->assets->addCss(<<<CSS
                body {
                    background-image: url("$url");
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
                    background-image: url("$url");
                }
                CSS);
        }
    }

    private function registerScripts(): void
    {
        $this->assets->addJsFile('javascripts/yw-init.js', true);
        $this->assets->addJsFile('javascripts/yeswiki-base-no-defer.js', true);

        // A wiki's own copy of a theme's scripts wins over the shipped one, and the two live in
        // different filesystems: `custom/` is the Instance's and may be a bucket, `themes/` is the
        // Program's and never is. Which one this is has to be decided before anything is listed.
        $themeJsDir = 'themes/' . $this->themeManager->getFavoriteTheme() . '/javascripts';
        $themeScripts = !$this->themeManager->getUseFallbackTheme() && $this->storage->directoryExists('custom/' . $themeJsDir)
            ? $this->filesInInstance('custom/' . $themeJsDir, '.js')
            : $this->filesInProgram($themeJsDir, '.js');
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

        $this->assets->addJsFile('javascripts/vendor/htmx/htmx.min.js');
        $this->assets->addJsFile('javascripts/yw-assets.js');
        $this->assets->addJsFile('javascripts/yw-core.js');

        $this->assets->addJsFile('javascripts/yw-navigation.js');
        $this->assets->addJsFile('javascripts/yw-datatable.js');
        $this->assets->addJsFile('javascripts/yw-autocomplete.js');

        foreach ($this->filesInInstance('custom/javascripts', '.js') as $file) {
            $this->assets->addJsFile($file);
        }
    }

    /**
     * The `wiki` global every script reads, rendered at the top of the skeleton's body block rather than declared as a head asset.
     */
    public function pageStateScript(): string
    {
        $props = [
            'locale' => $this->languages->preferredLanguage(),
            'timezone' => date_default_timezone_get(),
            'baseUrl' => $this->config['base_url'],
            'pageTag' => $this->pageContext->getTag(),
            'isDebugEnabled' => ($this->config->getValue('debug') ? 'true' : 'false'),
            'antiCsrfToken' => $this->csrfTokenManager->getToken('main')->getValue(),

            'imageUpload' => [
                'format' => (string)($this->config['image-upload-format'] ?? 'image/webp'),
                'maxWidth' => (int)($this->config['image-upload-max-width'] ?? 3840),
                'maxHeight' => (int)($this->config['image-upload-max-height'] ?? 2160),
                'quality' => (float)($this->config['image-upload-quality'] ?? 0.82),
                'maxSize' => (int)($this->config['image-upload-max-size'] ?? 0),
            ],
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

    /**
     * @return list<string> paths of $extension files directly in a wiki's own $dir
     */
    private function filesInInstance(string $dir, string $extension): array
    {
        return array_values(array_filter(
            $this->storage->files($dir),
            static fn (string $path) => str_ends_with($path, $extension)
        ));
    }

    /**
     * @return list<string> paths of $extension files directly in a shipped $dir
     */
    private function filesInProgram(string $dir, string $extension): array
    {
        return array_values(array_filter(
            $this->programFiles->files($dir),
            static fn (string $path) => str_ends_with($path, $extension)
        ));
    }

    public function startNewRequest(): void
    {
        $this->registered = false;
    }
}

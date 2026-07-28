<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Wiki;

class AssetsManager
{
    // Backward compatibility : in case some extensions were using javascript code previously in
    // tools/templates (and which have been moved elsewhere), we handle it
    protected const BACKWARD_PATH_MAPPING = [
        'tools/templates/libs/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.js' => 'javascripts/vendor/leaflet/leaflet.min.js',
        'tools/bazar/libs/vendor/leaflet/leaflet-providers.js' => 'javascripts/vendor/leaflet-providers/leaflet-providers.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.css' => 'styles/vendor/leaflet/leaflet.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/MarkerCluster.css' => 'styles/vendor/leaflet-markercluster/leaflet-markercluster.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/leaflet.markercluster.js' => 'javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.css' => 'styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.js' => 'javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js',
        'tools/bazar/libs/vendor/leaflet/ajax/dist/leaflet.ajax.min.js' => 'javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js',
        'tools/bazar/libs/vendor/leaflet/spiderfier/oms.min.js' => 'javascripts/vendor/leaflet-spiderfier/oms.min.js',
        'tools/bazar/libs/vendor/moment.min.js' => 'javascripts/vendor/moment/moment-with-locales.min.js',
        'tools/templates/libs/vendor/iframeResizer.contentWindow.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js',
        'tools/templates/libs/vendor/iframeResizer.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.min.js',
        // ticket 12 (templates absorbed into core)
        'tools/templates/libs/vendor/marked/marked.min.js' => 'javascripts/vendor/marked/marked.min.js',
        'tools/templates/libs/vendor/wow.min.js' => 'javascripts/vendor/wow.min.js',
        'tools/templates/libs/vendor/izmir/izmir.min.css' => 'styles/vendor/izmir/izmir.min.css',
        'tools/templates/presentation/styles/animate.css' => 'styles/animate.css',
        'tools/templates/presentation/styles/install.css' => 'styles/install.css',
        'tools/templates/presentation/styles/preset-sidenav.css' => 'styles/preset-sidenav.css',
        'tools/templates/javascripts/change-theme.js' => 'javascripts/change-theme.js',
        'tools/templates/javascripts/template-edit.js' => 'javascripts/template-edit.js',
        'tools/templates/presentation/javascripts/preset-sidenav.js' => 'javascripts/preset-sidenav.js',
        'tools/templates/javascripts/reload-gerer-droits.js' => 'javascripts/reload-gerer-droits.js',
    ];

    protected const PRODUCTION_PATH_MAPPING = [
        'javascripts/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.min.js',
    ];

    protected $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function AddCSS($style)
    {
        if (!isset($GLOBALS['css'])) {
            $GLOBALS['css'] = '';
        }
        if (!empty($style) && !strpos($GLOBALS['css'], '<style>' . "\n" . $style . '</style>')) {
            $GLOBALS['css'] .= '  <style>' . "\n" . $style . '</style>' . "\n";
        }
    }

    public function AddCSSFile($file, $conditionstart = '', $conditionend = '', $attrs = '')
    {
        if (!isset($GLOBALS['css'])) {
            $GLOBALS['css'] = '';
        }

        $code = $this->LinkCSSFile($file, $conditionstart, $conditionend, $attrs);

        if ($code && !strpos($GLOBALS['css'], $code)) {
            $GLOBALS['css'] .= $code;
        }
    }

    // this one can be used to directly include a css file within HTML with "echo $this->LinkCSSFile()"
    // so we can better control the order of inclusion
    public function LinkCSSFile($file, $conditionstart = '', $conditionend = '', $attrs = '')
    {
        $file = $this->mapFilePath($file);
        $isUrl = strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0;

        if (!$isUrl && $this->publishToCache()) {
            $published = AssetPublisher::publishedUrl($file, $this->assetsVersion());
            if ($published === null) {
                return '';
            }

            // no ?v= : the published path already embeds the version
            return <<<HTML
                $conditionstart
                <link rel="stylesheet" href="{$this->wiki->getBaseUrl()}/{$published}" $attrs>
                $conditionend
            HTML;
        }

        // NB: the source-tree check covers files absent from the instance docroot - the
        // URL still works there thanks to AssetPublisher's direct-path interception
        if ($isUrl || !empty($file) && (file_exists($file) || file_exists(YESWIKI_SOURCE_DIR . '/' . $file))) {
            $href = $isUrl ? $file : "{$this->wiki->getBaseUrl()}/{$file}";
            $revision = $this->wiki->GetConfigValue('yeswiki_release', null);

            return <<<HTML
                $conditionstart
                <link rel="stylesheet" href="{$href}?v={$revision}" $attrs>
                $conditionend
            HTML;
        }

        return '';
    }

    public function AddJavascript($script, $module = false)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }
        if (!empty($script) && !strpos($GLOBALS['js'], $script . '</script>')) {
            $GLOBALS['js'] .= '  <script' . ($module ? ' type="module"' : '') . '>' . "\n" . $script . '</script>' . "\n";
        }
    }

    public function AddJavascriptFile($file, $first = false, $module = false)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }

        $revision = $this->wiki->GetConfigValue('yeswiki_release', null);
        $initChar = (strpos($file, '?') !== false) ? '&' : '?';
        $rev = ($revision) ? $initChar . 'v=' . $revision : '';

        $file = $this->mapFilePath($file);

        $isUrl = strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0;
        $publishedSrc = null;
        if (!$isUrl && $this->publishToCache()) {
            $published = AssetPublisher::publishedUrl($file, $this->assetsVersion());
            if ($published === null) {
                return;
            }
            // no ?v= : the published path already embeds the version
            $publishedSrc = "{$this->wiki->getBaseUrl()}/$published";
        }

        if ($publishedSrc !== null || (!empty($file) && file_exists($file))) {
            // include local files
            $code = $publishedSrc !== null
                ? "<script src='$publishedSrc'"
                : "<script src='{$this->wiki->getBaseUrl()}/$file$rev'";
            if (!str_contains($GLOBALS['js'], $code) || $first) {
                if (!$first) {
                    $code .= ' defer';
                }
                if ($module) {
                    $code .= " type='module'";
                }
                $code .= '></script>' . "\n";
                if ($first) {
                    $GLOBALS['js'] = $code . $GLOBALS['js'];
                } else {
                    $GLOBALS['js'] .= $code;
                }
            }
        } elseif (strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0) {
            // include external files
            $code = "<script defer src='$file.$rev'></script>";
            if (!str_contains($GLOBALS['js'], $code)) {
                $GLOBALS['js'] .= $code . "\n";
            }
        }
    }

    /**
     * Farm instances (shared sources outside the instance docroot) get their css/js published
     * into the instance's cache/assets/{version}/ folder and referenced from there, so they
     * need no symlinks/aliases to the YesWiki sources (see AssetPublisher). Standalone
     * installs serve the source files directly.
     */
    private function publishToCache(): bool
    {
        return defined('YESWIKI_SOURCE_DIR') && defined('YESWIKI_INSTANCE_DIR')
            && YESWIKI_SOURCE_DIR !== YESWIKI_INSTANCE_DIR;
    }

    private function assetsVersion(): string
    {
        $release = $this->wiki->GetConfigValue('yeswiki_release');

        return AssetPublisher::sanitizeVersion($release !== '' ? $release : 'dev');
    }

    private function mapFilePath($file)
    {
        // Handle backwar compatibility
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) {
            $file = self::BACKWARD_PATH_MAPPING[$file];
        }

        // Handle production environement
        if (!$this->wiki->GetConfigValue('debug')) {
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) {
                $file = self::PRODUCTION_PATH_MAPPING[$file];
            }
        }

        return $file;
    }
}

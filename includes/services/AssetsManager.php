<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;

class AssetsManager
{
    // Backward compatibility : in case some extensions were using javascript code previously in
    // tools/templates (and which have been moved elsewhere), we handle it
    protected const BACKWARD_PATH_MAPPING = [
        'tools/templates/libs/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.js',
        'tools/templates/libs/vendor/spectrum-colorpicker/spectrum.min.js' => 'javascripts/vendor/spectrum-colorpicker2/spectrum.min.js',
        'tools/templates/libs/vendor/spectrum-colorpicker/spectrum.min.css' => 'styles/vendor/spectrum-colorpicker2/spectrum.min.css',
        'tools/bazar/libs/vendor/leaflet/leaflet.js' => 'javascripts/vendor/leaflet/leaflet.min.js',
        'tools/bazar/libs/vendor/leaflet/leaflet-providers.js' => 'javascripts/vendor/leaflet-providers/leaflet-providers.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.css' => 'styles/vendor/leaflet/leaflet.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/MarkerCluster.css' => 'styles/vendor/leaflet-markercluster/leaflet.markercluster.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/leaflet.markercluster.js' => 'javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.css' => 'styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.js' => 'javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js',
        'tools/bazar/presentation/javascripts/form-builder.min.js' => 'javascripts/vendor/formBuilder/form-builder.min.js',
        'tools/bazar/libs/vendor/jquery-ui-sortable/jquery-ui.min.js' => 'javascripts/vendor/jquery-ui-sortable/jquery-ui.min.js',
        'tools/templates/libs/vendor/datatables/jquery.dataTables.min.js' => 'javascripts/vendor/datatables-full/jquery.dataTables.min.js',
        'tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css' => 'styles/vendor/datatables-full/dataTables.bootstrap.min.css',
        'tools/bazar/libs/vendor/fullcalendar/fullcalendar.min.css' => 'styles/vendor/fullcalendar-jquery-v3.10.0/fullcalendar.min.css',
        'tools/bazar/libs/vendor/fullcalendar/fullcalendar.min.js' => 'javascripts/vendor/fullcalendar-jquery-v3.10.0/fullcalendar.min.js',
        'tools/bazar/libs/vendor/fullcalendar/locale-all.js' => 'javascripts/vendor/fullcalendar-jquery-v3.10.0/locale-all.min.js',
        'tools/bazar/libs/vendor/moment.min.js' => 'javascripts/vendor/moment/moment-with-locales.min.js',
        'tools/templates/libs/vendor/iframeResizer.contentWindow.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js',
        'tools/templates/libs/vendor/iframeResizer.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.min.js',
    ];

    protected const PRODUCTION_PATH_MAPPING = [
        'javascripts/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.min.js',
    ];

    protected const MODULE_GRAPH_CACHE = 'cache/es-module-graph.json';
    protected const IMPORT_PATTERN = '/(?:^|[;\s])(?:import\s+(?:[\w*{}\s,$]+\s+from\s+)?|export\s+[\w*{}\s,$]+\s+from\s+|import\s*\(\s*|import\.meta\.resolve\(\s*)[\'"]([^\'"]+)[\'"]/m';

    protected $wiki;
    protected array $moduleEntries = [];
    protected array $inlineModules = [];
    private ?array $moduleGraph = null;
    private bool $moduleGraphChanged = false;

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

        if ($isUrl || !empty($file) && file_exists($file)) {
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
            if ($module) {
                $this->inlineModules[] = $script;
            }
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

        if (!empty($file) && file_exists($file)) {
            if ($module) {
                $this->moduleEntries[] = $file;
            }
            $code = "<script src='{$this->versionedUrl($file)}'";
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
            $code = "<script defer src='$file$rev'></script>";
            if (!str_contains($GLOBALS['js'], $code)) {
                $GLOBALS['js'] .= $code . "\n";
            }
        }
    }

    /**
     * The import map giving each module that the page's modules import the same versioned URL as a script tag would,
     * so a browser never runs a module from one version against an import cached from another.
     */
    public function importMap(): string
    {
        $pending = $this->moduleEntries;
        $imported = [];
        foreach ($this->inlineModules as $script) {
            foreach ($this->importsOf($script, '.') as $dependency) {
                $imported[$dependency] = true;
                $pending[] = $dependency;
            }
        }
        while (($file = array_pop($pending)) !== null) {
            foreach ($this->importsOfFile($file) as $dependency) {
                if (!isset($imported[$dependency])) {
                    $imported[$dependency] = true;
                    $pending[] = $dependency;
                }
            }
        }
        if ($this->moduleGraphChanged) {
            $this->moduleGraph = array_filter($this->moduleGraph, 'is_file', ARRAY_FILTER_USE_KEY);
            @file_put_contents(self::MODULE_GRAPH_CACHE, json_encode(['pattern' => self::IMPORT_PATTERN, 'modules' => $this->moduleGraph]));
            $this->moduleGraphChanged = false;
        }
        if (empty($imported)) {
            return '';
        }
        $imports = [];
        foreach (array_keys($imported) as $file) {
            if (is_file($file)) {
                $imports["{$this->wiki->getBaseUrl()}/$file"] = $this->versionedUrl($file);
            }
        }
        ksort($imports);

        return '<script type="importmap">' . json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    }

    /** The URL of a local file, versioned by its modification time so it changes exactly when the file does. */
    public function versionedUrl(string $file): string
    {
        return "{$this->wiki->getBaseUrl()}/$file" . (str_contains($file, '?') ? '&' : '?') . 'v=' . @filemtime(strtok($file, '?'));
    }

    /** The local files a local module imports, read again only when the module changed since it was cached. */
    protected function importsOfFile(string $file): array
    {
        if ($this->moduleGraph === null) {
            $cached = is_file(self::MODULE_GRAPH_CACHE) ? json_decode((string)file_get_contents(self::MODULE_GRAPH_CACHE), true) : null;
            $this->moduleGraph = ($cached['pattern'] ?? null) === self::IMPORT_PATTERN ? $cached['modules'] : [];
        }
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return [];
        }
        if (($this->moduleGraph[$file]['mtime'] ?? null) !== $mtime) {
            $this->moduleGraph[$file] = ['mtime' => $mtime, 'imports' => $this->importsOf((string)file_get_contents($file), dirname($file))];
            $this->moduleGraphChanged = true;
        }

        return $this->moduleGraph[$file]['imports'];
    }

    /** The local files a module's source imports or resolves, with literal specifiers, relative to the wiki root. */
    protected function importsOf(string $source, string $directory): array
    {
        preg_match_all(self::IMPORT_PATTERN, $source, $matches);
        $basePath = rtrim((string)parse_url($this->wiki->getBaseUrl(), PHP_URL_PATH), '/');
        $imports = [];
        foreach ($matches[1] as $specifier) {
            if (str_starts_with($specifier, './') || str_starts_with($specifier, '../')) {
                $path = $directory . '/' . $specifier;
            } elseif (str_starts_with($specifier, '/') && !str_starts_with($specifier, '//')) {
                $path = substr($specifier, strlen($basePath));
            } else {
                continue;
            }
            $resolved = [];
            foreach (explode('/', strtok($path, '?#')) as $segment) {
                if ($segment === '..') {
                    array_pop($resolved);
                } elseif ($segment !== '.' && $segment !== '') {
                    $resolved[] = $segment;
                }
            }
            $imports[] = implode('/', $resolved);
        }

        return array_values(array_unique($imports));
    }

    private function mapFilePath($file)
    {
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) {
            $file = self::BACKWARD_PATH_MAPPING[$file];
        }

        if ($this->wiki->GetConfigValue('debug') != 'yes') {
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) {
                $file = self::PRODUCTION_PATH_MAPPING[$file];
            }
        }

        return $file;
    }
}

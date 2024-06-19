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

        return;
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

        return;
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
        } else {
            return '';
        }
    }

    public function AddJavascript($script)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }
        if (!empty($script) && !strpos($GLOBALS['js'], '<script>' . "\n" . $script . '</script>')) {
            $GLOBALS['js'] .= '  <script>' . "\n" . $script . '</script>' . "\n";
        }

        return;
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
            // include local files
            $code = "<script src='{$this->wiki->getBaseUrl()}/$file$rev'";
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

        return;
    }

    private function mapFilePath($file)
    {
        // Handle backwar compatibility
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) {
            $file = self::BACKWARD_PATH_MAPPING[$file];
        }

        // Handle production environement
        if ($this->wiki->GetConfigValue('debug') != 'yes') {
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) {
                $file = self::PRODUCTION_PATH_MAPPING[$file];
            }
        }

        return $file;
    }
}

<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;

class AssetsManager
{
    // Backward compatibility : in case some extensions were using javascript code previously in
    // tools/templates (and which have been moved elsewhere), we handle it
    protected const BACKWARD_PATH_MAPPING = [
        'tools/templates/libs/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.js',
        'tools/templates/libs/vendor/spectrum-colorpicker/spectrum.min.js' => 'javascripts/vendor/spectrum-colorpicker2/spectrum.js',
        'tools/templates/libs/vendor/spectrum-colorpicker/spectrum.min.css' => 'styles/vendor/spectrum-colorpicker2/spectrum.css',
        'tools/bazar/libs/vendor/leaflet/leaflet.js' => 'javascripts/vendor/leaflet/leaflet.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.css' => 'styles/vendor/leaflet/leaflet.css',
    ];

    protected const PRODUCTION_PATH_MAPPING = [
        'javascripts/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.min.js',
    ];

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }
    
    public function AddCSS($style)
    {
        if (!isset($GLOBALS['css'])) {
            $GLOBALS['css'] = '';
        }
        if (!empty($style) && !strpos($GLOBALS['css'], '<style>'."\n".$style.'</style>')) {
            $GLOBALS['css'] .= '  <style>'."\n".$style.'</style>'."\n";
        }
        return;
    }

    public function AddCSSFile($file, $conditionstart = '', $conditionend = '')
    {
        if (!isset($GLOBALS['css'])) $GLOBALS['css'] = '';

        $file = $this->mapFilePath($file);

        if (!empty($file) && file_exists($file)) {
            if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$this->wiki->getBaseUrl().'/'.$file.'">')) {
                $GLOBALS['css'] .= '  '.$conditionstart."\n"
                .'  <link rel="stylesheet" href="'.$this->wiki->getBaseUrl().'/'.$file.'">'
                ."\n".'  '.$conditionend."\n";
            }
        } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
            if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$file.'">')) {
                $GLOBALS['css'] .= '  '.$conditionstart."\n"
                    .'  <link rel="stylesheet" href="'.$file.'">'."\n"
                    .'  '.$conditionend."\n";
            }
        }
        return;
    }

    public function AddJavascript($script)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }
        if (!empty($script) && !strpos($GLOBALS['js'], '<script>'."\n".$script.'</script>')) {
            $GLOBALS['js'] .= '  <script>'."\n".$script.'</script>'."\n";
        }
        return;
    }

    public function AddJavascriptFile($file, $first = false, $module = false)
    {
        if (!isset($GLOBALS['js'])) $GLOBALS['js'] = '';

        $revision = $this->wiki->GetConfigValue('yeswiki_release', null);
        $initChar =  (strpos($file, '?') !== false) ? '&' : '?';
        $rev = ($revision) ? $initChar.'v='.$revision : '';
        
        $file = $this->mapFilePath($file);

        if (!empty($file) && file_exists($file)) {
            // include local files
            $code = "<script src='{$this->wiki->getBaseUrl()}/$file$rev'";
            if (!str_contains($GLOBALS['js'], $code) || $first) {
                if (!$first) $code .= " defer";
                if ($module) $code .= " type='module'";
                $code .= '></script>'."\n";
                if ($first) {
                    $GLOBALS['js'] = $code . $GLOBALS['js'];
                } else {
                    $GLOBALS['js'] .= $code;
                }
            }
        } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
            // include external files
            $code = "<script defer src='$file.$rev'></script>";
            if (!str_contains($GLOBALS['js'], $code)) {
                $GLOBALS['js'] .= $code."\n";
            }
        }
        return;
    }

    private function mapFilePath($file)
    {
        // Handle backwar compatibility
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) $file = self::BACKWARD_PATH_MAPPING[$file];

        // Handle production environement
        if ($this->wiki->GetConfigValue('debug') != 'yes') {            
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) $file = self::PRODUCTION_PATH_MAPPING[$file];
        }
        
        return $file;
    }
}
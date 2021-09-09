<?php

use YesWiki\Core\Service\ThemeManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// feuilles de styles css
$styles = "\n".'  <!-- CSS files -->'."\n";

// si pas le mot yeswiki. ou yw. dans les css, on charge les styles par defaut de yeswiki
if (!strstr($this->config['favorite_style'], 'yw.')) {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/styles/yeswiki-base.css" />'."\n";
}

// si pas le mot bootstrap. ou bs. dans les css, on charge les styles bootstrap par defaut
if (!strstr($this->config['favorite_style'], 'bootstrap.') && !strstr($this->config['favorite_style'], 'bs.')) {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/styles/vendor/bootstrap/css/bootstrap.min.css" />'."\n";
}

// presets activated and path ?
$presetsActivated = !empty($this->config['templates'][$this->config['favorite_theme']]['presets']) && !empty($this->config['favorite_preset']);
if ($presetsActivated) {
    $custom_prefix = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX;
    $presetIsCustom = (substr($this->config['favorite_preset'], 0, strlen($custom_prefix)) == $custom_prefix);
    if (!$presetIsCustom) {
        $presetFile = 'themes/'.$this->config['favorite_theme'].'/presets/'.$this->config['favorite_preset'];
    } else {
        $presetFile = ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . substr($this->config['favorite_preset'], strlen($custom_prefix));
    }
}

// on regarde dans quel dossier se trouve le theme
$styleFile = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
if (empty($this->config['use_fallback_theme'])) {
    if (file_exists('custom/'.$styleFile)) {
        $styleFile = 'custom/'.$styleFile;
    }
    if ($presetsActivated && !$presetIsCustom && file_exists('custom/'.$presetFile)) {
        $presetFile = 'custom/'.$presetFile;
    }
}

// on ajoute le style css selectionne du theme
if ($this->config['favorite_style']!='none') {
    if (substr($this->config['favorite_style'], -4, 4) == '.css') {
        $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$styleFile.'" id="mainstyle" />'."\n";
    }
}

// on ajoute le preset css selectionne du theme
if (($this->config['favorite_style']!='none')
        && $presetsActivated
        && substr($this->config['favorite_preset'], -4, 4) == '.css') {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$presetFile.'"/>'."\n";
}

// on ajoute les icones de fontawesome
if (empty($this->config['fontawesome']) || $this->config['fontawesome'] != '0') {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/styles/vendor/fontawesome/css/all.min.css" />'."\n";
}

// si l'action propose d'autres css a ajouter, on les ajoute
$othercss = $this->GetParameter('othercss');
if (!empty($othercss)) {
    $tabcss = explode(',', $othercss);
    foreach ($tabcss as $cssfile) {
        $style = 'themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile;
        if (file_exists('custom/'.$style)) {
            $style = 'custom/'.$style;
        }
        $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$style.'" />'."\n";
    }
}

// PageCSS moved to actions/linkstyle__.php to be added at the end

// add css files which are included in the custom styles directory
$customCssPath = 'custom/styles';
$customCssDir = is_dir($customCssPath) ? opendir($customCssPath) : false;
while ($customCssDir && ($file = readdir($customCssDir)) !== false) {
    if (substr($file, -4, 4) == '.css') {
        $styles .= '  <link rel="stylesheet" href="' . $this->getBaseUrl() . '/' . $customCssPath . '/' . $file .'" />' . "\n";
    }
}

// on ajoute aux css le background personnalise
if (isset($this->config['favorite_background_image']) && $this->config['favorite_background_image']!='') {
    $imgextension = strtolower(substr($this->config['favorite_background_image'], -4, 4));
    if ($imgextension=='.jpg') {
        $styles .= '	<style>
		body {
			background-image: url("files/backgrounds/'.$this->config['favorite_background_image'].'");
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
	</style>'."\n";
    } elseif ($imgextension=='.png') {
        $styles .= '	<style>
		body {
			background-image: url("files/backgrounds/'.$this->config['favorite_background_image'].'");
		}
	</style>'."\n";
    }
}
    
echo $styles;

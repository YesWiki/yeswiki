<?php

use YesWiki\Core\Service\ThemeManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$themeManager = $this->services->get(ThemeManager::class);
$favoriteStyle = $themeManager->getFavoriteStyle();
// si pas le mot bootstrap. ou bs. dans les css, on charge les styles bootstrap par defaut
if (!strstr($favoriteStyle, 'bootstrap.') && !strstr($favoriteStyle, 'bs.')) {
    echo $this->LinkCSSFile('styles/vendor/bootstrap/css/bootstrap.min.css');
}

// styles par defaut de yeswiki
echo $this->LinkCSSFile('styles/yeswiki-base.css');

// presets activated and path ?
$favoritePreset = $themeManager->getFavoritePreset();
$presetsActivated = !empty(($themeManager->getTemplates())[$themeManager->getFavoriteTheme()]['presets']) && !empty($favoritePreset);
if ($presetsActivated) {
    $custom_prefix = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX;
    $presetIsCustom = (substr($favoritePreset, 0, strlen($custom_prefix)) == $custom_prefix);
    if (!$presetIsCustom) {
        $presetFile = 'themes/' . $themeManager->getFavoriteTheme() . '/presets/' . $favoritePreset;
    } else {
        $presetFile = ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . substr($favoritePreset, strlen($custom_prefix));
    }
}

// on regarde dans quel dossier se trouve le theme
$styleFile = 'themes/' . $themeManager->getFavoriteTheme() . '/styles/' . $favoriteStyle;
if (file_exists('custom/' . $styleFile)) {
    $styleFile = 'custom/' . $styleFile;
}
if ($presetsActivated && !$presetIsCustom && file_exists('custom/' . $presetFile)) {
    $presetFile = 'custom/' . $presetFile;
}

// on ajoute le style css selectionne du theme
if ($favoriteStyle != 'none') {
    if (substr($favoriteStyle, -4, 4) == '.css') {
        echo $this->LinkCSSFile($styleFile, '', '', 'id="mainstyle"');
    }
}

// on ajoute le preset css selectionne du theme
if (($favoriteStyle != 'none')
        && $presetsActivated
        && substr($favoritePreset, -4, 4) == '.css') {
    echo $this->LinkCSSFile($presetFile);
}

// on ajoute les icones de fontawesome
if (empty($this->config['fontawesome']) || $this->config['fontawesome'] != '0') {
    echo $this->LinkCSSFile('styles/vendor/fontawesome/css/all.min.css');
}

// si l'action propose d'autres css a ajouter, on les ajoute
$othercss = $this->GetParameter('othercss');
if (!empty($othercss)) {
    $tabcss = explode(',', $othercss);
    foreach ($tabcss as $cssfile) {
        $style = 'themes/' . $themeManager->getFavoriteTheme() . '/styles/' . $cssfile;
        if (file_exists('custom/' . $style)) {
            $style = 'custom/' . $style;
        }
        $this->AddCSSFile($style);
    }
}

// add css files which are included in the custom styles directory
$customCssPath = 'custom/styles';
$customCssDir = is_dir($customCssPath) ? opendir($customCssPath) : false;
while ($customCssDir && ($file = readdir($customCssDir)) !== false) {
    if (substr($file, -4, 4) == '.css') {
        $this->AddCSSFile($customCssPath . '/' . $file);
    }
}

$favoriteBackgroundImage = $themeManager->getFavoriteBackgroundImage();
// on ajoute aux css le background personnalise
if (!empty($favoriteBackgroundImage)) {
    $imgextension = strtolower(substr($favoriteBackgroundImage, -4, 4));
    if ($imgextension == '.jpg') {
        $this->AddCSS(<<<CSS
            body {
                background-image: url("files/backgrounds/$favoriteBackgroundImage");
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
    } elseif ($imgextension == '.png') {
        $this->AddCSS(<<<CSS
            body {
                background-image: url("files/backgrounds/$favoriteBackgroundImage");
            }
        CSS);
    }
}

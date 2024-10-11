<?php

use YesWiki\Core\Service\ThemeManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$themeManager = $this->services->get(ThemeManager::class);

$param = $this->GetParameter('param');
if (!empty($param)) {
    switch ($param) {
        case 'wakka_version':
        case 'wikini_version':
        case 'yeswiki_version':
        case 'yeswiki_release':
        case 'root_page':
        case 'wakka_name':
        case 'base_url':
        case 'navigation_links':
        case 'meta_keywords':
        case 'meta_description':
        case 'favorite_theme':
        case 'favorite_style':
        case 'favorite_squelette':
        case 'default_language':
        case 'charset':
            echo htmlentities($this->config[$param], ENT_QUOTES, YW_CHARSET);
            break;
        case 'lang':
            echo $GLOBALS['prefered_language'];
            break;
        case 'theme_path':
            $theme = $themeManager->getFavoriteTheme();
            echo ((is_dir('custom/themes/' . $theme))) ?
                "custom/themes/$theme/" :
                "themes/$theme/";
            break;
        default:
            break;
    }
}

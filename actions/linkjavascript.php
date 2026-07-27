<?php

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\Service\ThemeManager;

$themeManager = $this->services->get(ThemeManager::class);
$yeswiki_javascripts = "\n" . '  <!-- javascripts -->' . "\n";

// ticket 16: jQuery and Bootstrap JS are no longer loaded globally; ticket 26
// removed the last jQuery island (the old form-builder admin page) — core ships
// no jQuery at all.

// on récupère le bon chemin pour le theme
if ($themeManager->getUseFallbackTheme()) {
    $repertoire = 'themes/' . $themeManager->getFavoriteTheme() . '/javascripts';
} else {
    $jsDir = 'themes/' . $themeManager->getFavoriteTheme() . '/javascripts';
    if (is_dir('custom/' . $jsDir)) {
        $repertoire = 'custom/' . $jsDir;
    } else {
        $repertoire = $jsDir;
    }
}

// on scanne les javascripts du theme
$yeswikijs = false;
$dir = (is_dir($repertoire) ? opendir($repertoire) : false);
while ($dir && ($file = readdir($dir)) !== false) {
    if (substr($file, -3, 3) == '.js') {
        $scripts[] = $repertoire . '/' . $file;
        if (strstr($file, 'yeswiki.') || strstr($file, 'yw.')) {
            // le theme contient deja le js de yeswiki
            $yeswikijs = true;
        }
    }
}
if (is_dir($repertoire)) {
    closedir($dir);
}

// on trie les javascripts du theme par ordre alphabéthique et on les insere
if (isset($scripts) && is_array($scripts)) {
    asort($scripts);
    foreach ($scripts as $val) {
        $this->addJavascriptFile($val);
    }
}

// s'il n'y a pas le javascript de yeswiki dans le theme, on le rajoute
if (!$yeswikijs) {
    $this->addJavascriptFile('javascripts/yeswiki-base.js');
}

// htmx + yw-* core design system (ADR-0004/0005): loaded on every page so
// core interactions can rely on them without each surface opting in itself
$this->addJavascriptFile('javascripts/vendor/htmx/htmx.min.js');
$this->addJavascriptFile('javascripts/yw-core.js');
$this->addJavascriptFile('javascripts/yw-datatable.js');
$this->addJavascriptFile('javascripts/yw-autocomplete.js');

// ajoute la méthode pour les traductions js
$this->addJavascriptFile('javascripts/yeswiki-base-no-defer.js', true);

// add javascript files which are included in the custom javascript directory
$customJsPath = 'custom/javascripts';
$customJsDir = is_dir($customJsPath) ? opendir($customJsPath) : false;
while ($customJsDir && ($file = readdir($customJsDir)) !== false) {
    if (substr($file, -3, 3) == '.js') {
        $this->addJavascriptFile($customJsPath . '/' . $file);
    }
}

// si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
$yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

// on vide la variable globale pour le javascript
$GLOBALS['js'] = '';

$wikiprops = [
    'locale' => $GLOBALS['prefered_language'],
    'timezone' => date_default_timezone_get(),
    'baseUrl' => $this->config['base_url'],
    'pageTag' => $this->getPageTag(),
    'isDebugEnabled' => ($this->GetConfigValue('debug') ? 'true' : 'false'),
    'antiCsrfToken' => $this->services->get(CsrfTokenManager::class)->getToken('main')->getValue(),
];

// Globale wiki variable
echo "<script>
    var wiki = {
        ...((typeof wiki !== 'undefined') ? wiki : null),
        ..." . json_encode($wikiprops) . ",
        ...{
            lang: {
                ...((typeof wiki !== 'undefined') ? (wiki.lang ?? null) : null),
                ..." . json_encode($GLOBALS['translations_js'] ?? null) . '
            }
        },
        ...{
        	minSearchKeywordLength : ' . (isset($this->config['min_search_keyword_length']) ? intval($this->config['min_search_keyword_length']) : MIN_SEARCH_KEYWORD_LENGTH) . '
        }
    };
</script>';

// on affiche
echo $yeswiki_javascripts;

// This GLOBALS is populated from AddCSS and AddCSSFile, but already flush in <HEAD> by actions/linkstyle__.php
// we add it at the end to catch other calls to ADDCSSFile ou AddCSS
if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
    echo $GLOBALS['css'];
}

<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$yeswiki_javascripts = "\n" . '  <!-- javascripts -->' . "\n";

if (isset($this->config['use_jquery_cdn']) && $this->config['use_jquery_cdn'] == "1") {
    $this->addJavascriptFile('https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js', true);
} else {
    $this->addJavascriptFile('tools/templates/libs/vendor/jquery-2.2.4.min.js', true);
}

// on récupère le bon chemin pour le theme
if (is_dir('themes/' . $this->config['favorite_theme'] . '/javascripts')) {
    $repertoire = 'themes/' . $this->config['favorite_theme'] . '/javascripts';
} else {
    $repertoire = 'tools/templates/themes/' . $this->config['favorite_theme'] . '/javascripts';
}

// on scanne les javascripts du theme
$bootstrapjs = false;
$yeswikijs = false;
$dir = (is_dir($repertoire) ? opendir($repertoire) : false);
while ($dir && ($file = readdir($dir)) !== false) {
    if (substr($file, -3, 3) == '.js') { 
        $scripts[] =  $repertoire . '/' . $file;
        if (strstr($file, 'bootstrap.min.') || strstr($file, 'bs.')) {
            // le theme contient deja le js de bootstrap
            $bootstrapjs = true;
        }
        if (strstr($file, 'yeswiki.') || strstr($file, 'yw.')) {
            // le theme contient deja le js de yeswiki
            $yeswikijs = true;
        }

    }
}
if (is_dir($repertoire)) {
    closedir($dir);
}

// s'il n'y a pas le javascript de bootstrap dans le theme, on le rajoute
if (!$bootstrapjs) {
    $this->addJavascriptFile('tools/templates/libs/vendor/bootstrap.min.js');
}

// on trie les javascripts du theme par ordre alphabéthique et on les insere
if (isset($scripts) && is_array($scripts)) {
    asort($scripts);
    foreach ($scripts as $key => $val) {
        $this->addJavascriptFile($val);
    }
}

// s'il n'y a pas le javascript de yeswiki dans le theme, on le rajoute
if (!$yeswikijs) {
    $this->addJavascriptFile('tools/templates/libs/yeswiki-base.js');
}

// si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
$yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

// on vide la variable globale pour le javascript
$GLOBALS['js'] = '';


// TODO: CSS a ajouter ailleurs?
if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
    $yeswiki_javascripts .=  $GLOBALS['css'];
    $GLOBALS['css'] = '';
}

// on affiche
echo $yeswiki_javascripts;

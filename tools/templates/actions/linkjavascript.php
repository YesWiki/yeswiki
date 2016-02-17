<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$yeswiki_javascripts = "\n" . '    <!-- javascripts -->' . "\n";

if (isset($this->config['use_jquery_cdn']) && $this->config['use_jquery_cdn'] == "1") {
    $yeswiki_javascripts .= '	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>' . "\n" . '	<script>window.jQuery || document.write(\'<script src="tools/templates/libs/vendor/jquery-1.11.2.min.js"><\/script>\')</script>' . "\n";
} else {
    $yeswiki_javascripts .= '  <script src="tools/templates/libs/vendor/jquery-1.11.3.min.js"></script>' . "\n";
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
        $scripts[] = '  <script src="' . $repertoire . '/' . $file . '"></script>' . "\n";
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

$yeswiki_javascripts_dir = '';

// on trie les javascripts par ordre alphabéthique
if (isset($scripts) && is_array($scripts)) {
    asort($scripts);
    foreach ($scripts as $key => $val) {
        $yeswiki_javascripts_dir.= $val;
    }
}

// s'il n'y a pas le javascript de bootstrap dans le theme, on le rajoute
if (!$bootstrapjs) {
    $yeswiki_javascripts .= '    <script src="tools/templates/libs/vendor/bootstrap.min.js"></script>' . "\n";
}

// on ajoute les javascripts du theme
$yeswiki_javascripts .= $yeswiki_javascripts_dir;

// s'il n'y a pas le javascript de yeswiki dans le theme, on le rajoute
if (!$yeswikijs) {
    $yeswiki_javascripts .= '    <script src="tools/templates/libs/yeswiki-base.js"></script>' . "\n";
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

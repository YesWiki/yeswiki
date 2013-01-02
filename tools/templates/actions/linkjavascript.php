<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$yeswiki_javascripts = "\n".
'	<!-- javascripts -->'."\n";

$yeswiki_javascripts .= '	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>'."\n".
						'	<script>window.jQuery || document.write(\'<script src="tools/templates/libs/jquery-1.8.2.min.js"><\/script>\')</script>'."\n";

// javascripts de base, nécessaires au bon fonctionnement de YesWiki
$yeswiki_javascripts .= '	<script src="tools/templates/libs/bootstrap.min.js"></script>'."\n".
						'	<script src="tools/templates/libs/yeswiki-base.js"></script>'."\n";

// on récupère le bon chemin pour le theme
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}

// on ajoute les javascripts du theme
$dir = (is_dir($repertoire) ? opendir($repertoire) : false);
while ($dir && ($file = readdir($dir)) !== false) {
  if (substr($file, -3, 3)=='.js') $scripts[] = '	<script src="'.$repertoire.'/'.$file.'"></script>'."\n";
}
if (is_dir($repertoire)) closedir($dir);

// on trie les javascripts par ordre alphabéthique
if (isset($scripts) && is_array($scripts)) {
	asort($scripts);
	foreach ($scripts as $key => $val) {
	    $yeswiki_javascripts .= $val;
	}
}

// si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
$yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

// on vide la variable globale pour le javascript
$GLOBALS['js'] = '';


// on affiche
echo $yeswiki_javascripts;
?>

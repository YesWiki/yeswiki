<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// javascripts de base, nécessaires au bon fonctionnement de YesWiki
$yeswiki_javascripts = "\n".
'	<!-- javascripts -->'."\n".
'	<script type="text/javascript" src="tools/templates/libs/jquery-1.7.2.min.js"></script>'."\n".
'	<script type="text/javascript" src="tools/templates/libs/bootstrap.min.js"></script>'."\n".
'	<script type="text/javascript" src="tools/templates/libs/jquery.tools.min.js"></script>'."\n".
'	<script type="text/javascript" src="tools/templates/libs/yeswiki-base.js"></script>'."\n";

// on récupère le bon chemin pour le theme
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}

// on ajoute les javascripts du theme
$dir = opendir($repertoire);
while (false !== ($file = readdir($dir))) {
  if (substr($file, -3, 3)=='.js') $scripts[] = '	<script type="text/javascript" src="'.$repertoire.'/'.$file.'"></script>'."\n";
}
closedir($dir);

// on trie les javascripts par ordre alphabéthique
if (isset($scripts) && is_array($scripts)) {
	asort($scripts);
	foreach ($scripts as $key => $val) {
	    $yeswiki_javascripts .= $val;
	}
}

// on affiche
echo $yeswiki_javascripts;
?>

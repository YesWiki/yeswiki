<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$yeswiki_javascripts = "\n".
'    <!-- javascripts -->'."\n";

$yeswiki_javascripts .= '	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>'."\n".
						'	<script>window.jQuery || document.write(\'<script src="tools/templates/libs/vendor/jquery-1.10.2.min.js"><\/script>\')</script>'."\n";

// on récupère le bon chemin pour le theme
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}

// on scanne les javascripts du theme
$bootstrapjs = false; $yeswikijs = false;
$dir = (is_dir($repertoire) ? opendir($repertoire) : false);
while ($dir && ($file = readdir($dir)) !== false) {
	if (substr($file, -3, 3)=='.js') {
  		$scripts[] = '	<script src="'.$repertoire.'/'.$file.'"></script>'."\n";
		if (strstr($file, 'bootstrap.min.') || strstr($file, 'bs.')) $bootstrapjs = true; // le theme contient deja le js de bootstrap
		if (strstr($file, 'yeswiki.') || strstr($file, 'yw.')) $yeswikijs = true; // le theme contient deja le js de yeswiki
	}
}
if (is_dir($repertoire)) closedir($dir);

$yeswiki_javascripts_dir = '';
// on trie les javascripts par ordre alphabéthique
if (isset($scripts) && is_array($scripts)) {
	asort($scripts);
	foreach ($scripts as $key => $val) {
	    $yeswiki_javascripts_dir .= $val;
	}
}

// s'il n'y a pas le javascript de bootstrap dans le theme, on le rajoute
if (!$bootstrapjs) $yeswiki_javascripts .= '    <script src="tools/templates/libs/vendor/bootstrap-2.3.2.min.js"></script>'."\n";

// on ajoute les javascripts du theme
$yeswiki_javascripts .= $yeswiki_javascripts_dir;

// s'il n'y a pas le javascript de yeswiki dans le theme, on le rajoute
if (!$yeswikijs) $yeswiki_javascripts .= '    <script src="tools/templates/libs/yeswiki-base.js"></script>'."\n";

// si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
$yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

// on vide la variable globale pour le javascript
$GLOBALS['js'] = '';


// on affiche
echo $yeswiki_javascripts;
?>

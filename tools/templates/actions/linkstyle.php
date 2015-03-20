<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// feuilles de styles css
$styles = "\n".'	<!-- CSS files -->'."\n";

// si pas le mot yeswiki. ou yw. dans les css, on charge les styles par defaut de yeswiki
if (!strstr($this->config['favorite_style'], 'yw.')) {
	$styles .= '	<link rel="stylesheet" href="tools/templates/presentation/styles/yeswiki-base.css" />'."\n";
}


// si pas le mot bootstrap. ou bs. dans les css, on charge les styles bootstrap par defaut
if (!strstr($this->config['favorite_style'], 'bootstrap.') && !strstr($this->config['favorite_style'], 'bs.')) {
	$styles .= '	<link rel="stylesheet" href="tools/templates/presentation/styles/bootstrap.min.css" />'."\n";
}

// on regarde dans quel dossier se trouve le theme
if (file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'])) {
	$css_file = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
} else {
	$css_file = 'tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
}

// on ajoute le style css selectionne du theme
if ($this->config['favorite_style']!='none') {
	if (substr($this->config['favorite_style'], -4, 4) == '.css') {
		$styles .= '	<link rel="stylesheet" href="'.$css_file.'" id="mainstyle" />'."\n";
	}
}

// si l'action propose d'autres css a ajouter, on les ajoute
$othercss = $this->GetParameter('othercss'); 
if (!empty($othercss)) {
	$tabcss = explode(',', $othercss);
	foreach($tabcss as $cssfile) {
		if (file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile)) {
			$styles .= '	<link rel="stylesheet" href="themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile.'" />'."\n";
		} elseif (file_exists('tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile)) {
			$styles .= '	<link rel="stylesheet" href="tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile.'" />'."\n";
		}
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
	}
	elseif ($imgextension=='.png') {
		$styles .= '	<style>
		body {
			background-image: url("files/backgrounds/'.$this->config['favorite_background_image'].'");
		}
	</style>'."\n";
	}
}
 	
echo $styles;
?>

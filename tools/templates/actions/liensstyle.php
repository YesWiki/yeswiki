<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//feuilles de styles de base yeswiki
$wikini_styles_css = '  <link rel="stylesheet" href="tools/templates/presentation/styles/yeswiki-base.css" />'."\n".
'  <link rel="stylesheet" href="tools/templates/presentation/styles/bootstrap.min.css" />'."\n";


// si l'action propose d'autres css à ajouter, on les ajoute
$othercss = $this->GetParameter('othercss'); 
if (!empty($othercss)) {
	$tabcss = explode(',', $othercss);
	foreach($tabcss as $cssfile) {
		if (file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile)) {
			$wikini_styles_css .= '  <link rel="stylesheet" href="themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile.'" />'."\n";
		} elseif (file_exists('tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile)) {
			$wikini_styles_css .= '  <link rel="stylesheet" href="tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile.'" />'."\n";
		}
	}
}

if (file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'])) {
	$css_file = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
} else {
	$css_file = 'tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
}

if ($this->config['favorite_style']!='none') {
	if (substr($this->config['favorite_style'], -4, 4) == '.css') $wikini_styles_css .= '<link rel="stylesheet" href="'.$css_file.'" id="mainstyle" />';
	elseif (substr($this->config['favorite_style'], -5, 5) == '.less') {
		$wikini_styles_css .= '  <link rel="stylesheet/less" href="'.$css_file.'" id="mainstyle" />
		<script src="tools/templates/libs/less-1.3.0.min.js" type="text/javascript"></script>';
	}
	
}

// on ajoute aux css le background personnalisé
if (isset($this->page['metadatas']['bgimg'])) {
	$imgextension = strtolower(substr($this->page['metadatas']['bgimg'], -4, 4));
	if ($imgextension=='.jpg') {
		$wikini_styles_css .= '<style>
		body {
			background-image: url("files/backgrounds/'.$this->page['metadatas']['bgimg'].'");
			background-repeat:no-repeat;
			width:100%;
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
		$wikini_styles_css .= '<style>
		body {
			background-image: url("files/backgrounds/'.$this->page['metadatas']['bgimg'].'");
		}
		</style>'."\n";
	}
}
 	
echo $wikini_styles_css;
?>

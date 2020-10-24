<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// feuilles de styles css
$styles = "\n".'  <!-- CSS files -->'."\n";

// si pas le mot yeswiki. ou yw. dans les css, on charge les styles par defaut de yeswiki
if (!strstr($this->config['favorite_style'], 'yw.')) {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/tools/templates/presentation/styles/yeswiki-base.css" />'."\n";
}

// si pas le mot bootstrap. ou bs. dans les css, on charge les styles bootstrap par defaut
if (!strstr($this->config['favorite_style'], 'bootstrap.') && !strstr($this->config['favorite_style'], 'bs.')) {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/tools/templates/presentation/styles/bootstrap.min.css" />'."\n";
}

// on regarde dans quel dossier se trouve le theme
if (!empty($this->config['use_fallback_theme'])) {
    $styleFile = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
} else {
    $styleFile = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
    if (file_exists('custom/'.$styleFile)) {
        $styleFile = 'custom/'.$styleFile;
    }
}

// on ajoute le style css selectionne du theme
if ($this->config['favorite_style']!='none') {
    if (substr($this->config['favorite_style'], -4, 4) == '.css') {
        $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$styleFile.'" id="mainstyle" />'."\n";
    }
}

// on ajoute les icones de fontawesome
if (empty($this->config['fontawesome']) || $this->config['fontawesome'] != '0') {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/tools/templates/libs/vendor/fontawesome-5/css/all.min.css" />'."\n";
}

// si l'action propose d'autres css a ajouter, on les ajoute
$othercss = $this->GetParameter('othercss');
if (!empty($othercss)) {
    $tabcss = explode(',', $othercss);
    foreach ($tabcss as $cssfile) {
        $style = 'themes/'.$this->config['favorite_theme'].'/styles/'.$cssfile;
        if (file_exists('custom/'.$style)) {
            $style = 'custom/'.$style;
        }
        $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$style.'" />'."\n";
    }
}

// if exists and not empty, add the 'PageCss' yeswiki page to the styles (the PageCss content must respect the CSS syntax)
$pageCss = $this->LoadPage('PageCss');
if ($pageCss && !empty($pageCss['body'])){
    $styles .= '  <link rel="stylesheet" href="' . $this->href('css', 'PageCss') .'" />'."\n";
}

if (file_exists('custom/styles/custom.css')) {
    $styles .= '  <link rel="stylesheet" href="'.$this->getBaseUrl().'/custom/styles/custom.css" />' . "\n";
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
    } elseif ($imgextension=='.png') {
        $styles .= '	<style>
		body {
			background-image: url("files/backgrounds/'.$this->config['favorite_background_image'].'");
		}
	</style>'."\n";
    }
}
    
echo $styles;

<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//feuilles de styles de base yeswiki
$wikini_styles_css = '  <link rel="stylesheet" href="tools/templates/presentation/styles/yeswiki-base.css">';

if (file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'])) {
	$css_file = 'themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
} else {
	$css_file = 'tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'];
}

if ($this->config['favorite_style']!='none') {
	if (substr($this->config['favorite_style'], -4, 4) == '.css') $wikini_styles_css .= '<link rel="stylesheet" type="text/css" href="'.$css_file.'" media="screen" title="'.$this->config['favorite_style'].'" />';
	elseif (substr($this->config['favorite_style'], -5, 5) == '.less') {
		$wikini_styles_css .= '<link rel="stylesheet/less" type="text/css" href="'.$css_file.'" media="screen" title="'.$this->config['favorite_style'].'" />
		<script src="tools/templates/libs/less-1.1.5.min.js" type="text/javascript"></script>';
	}
	
}
 	
echo $wikini_styles_css;
?>

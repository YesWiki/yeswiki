<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//feuilles de styles
$wikini_styles_css = '';
if ($this->config['favorite_style']!='none') $wikini_styles_css .= '<link rel="stylesheet" type="text/css" href="tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'].'" media="screen" title="'.$this->config['favorite_style'].'" />';

//verification de l'existance d'une feuille de style pour l'impression
$style_impression = 'tools/templates/themes/'.$this->config['favorite_theme'].'/styles/print/print.css';
if (file_exists($style_impression)) {
	$wikini_styles_css .= "\n".'<link rel="stylesheet" href="'.$style_impression.'" type="text/css" media="print" />';
}   
 	
echo $wikini_styles_css;
?>
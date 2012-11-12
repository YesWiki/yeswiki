<?php
	
/**
 * 
 * Handler "diaporama" pour YesWiki.
 * Florian Schmitt <florian@outils-reseaux.org>.
 * Licence GPL.
 *
 *
**/

// Verification de securite
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}
	
// on recupere les entetes html mais pas ce qu'il y a dans le body
$header =  explode('<body',$this->Header());
echo str_replace('<html', '<html class="slideshow-html"', $header[0])."<body class=\"slideshow-body\">\n";

//fonction de generation du diaporama (teste les droits et l'existence de la page)
echo print_diaporama($this->tag);
	
//on recupere juste les javascripts et la fin des balises body et html
$footer =  preg_replace('/^.+<script/Us', "\n".'<script', $this->Footer());

echo $footer;
?>

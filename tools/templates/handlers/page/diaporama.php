<?php
	
/**
 * 
 * Handler "diaporama" pour YesWiki.
 * Développé par Florian Schmitt <florian@outils-reseaux.org>.
 * Licence GPL.
 *
 *
**/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}
	
// on récupère les entêtes html mais pas ce qu'il y a dans le body
$header =  explode('<body',$this->Header());
echo $header[0]."<body>\n";

//fonction de génération du diaporama (teste les droits et l'existence de la page)
echo print_diaporama($this->tag);
	
//on récupère juste les javascripts et la fin des balises body et html
$footer =  preg_replace('/^.+<script/Us', '<script', $this->Footer());

echo $footer;
?>

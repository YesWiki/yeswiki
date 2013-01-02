<?php
	
/**
 * 
 * Handler "diaporama" pour YesWiki.
 * D�velopp� par Florian Schmitt <florian@outils-reseaux.org>.
 * Licence GPL.
 *
 *
**/

// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}
	
// on r�cup�re les ent�tes html mais pas ce qu'il y a dans le body
$header =  explode('<body',$this->Header());
echo $header[0]."<body>\n";

//fonction de g�n�ration du diaporama (teste les droits et l'existence de la page)
echo print_diaporama($this->tag);
	
//on r�cup�re juste les javascripts et la fin des balises body et html
$footer =  preg_replace('/^.+<script/Us', '<script', $this->Footer());

echo $footer;
?>

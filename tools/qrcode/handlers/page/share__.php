<?php
/*
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

	// création et affichage QRcode du lien de page
	include_once 'tools/qrcode/libs/qrlib.php';
	$url = $this->Href();	
	$cache_image = str_replace('wakka.php?wiki=', '', $this->config['base_url']).'cache/qrcode-'.$this->getPageTag().'.png';
	QRcode::png($url, $cache_image, QR_CORRECTION, 4, 2);
	$html = '<img class="right" src="'.$cache_image.'" title="QRcode de l\'adresse de cette page " alt="'.$url.'" />'."\n";

// Agrégation du QRcode dans le buffer du handler share
$plugin_output_new = preg_replace ('/<h2>Partager /', utf8_encode($html)."\n".'<h2>Partager ', $plugin_output_new);
?>

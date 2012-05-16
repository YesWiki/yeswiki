<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

// Sauvegarde des metas
if ( isset($_POST["submit"]) && $_POST["submit"] == 'Sauver' && isset($_POST["theme"]) && !isset($this->page['metadatas']['theme']) ) {
	$this->SaveMetaDatas($this->GetPageTag(), array('theme' => $_POST["theme"], 'style' => $_POST["style"], 'squelette' => $_POST["squelette"], 'bgimg' => $_POST["bgimg"] ));
}

// Si une valeur de body est passée en paramètre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
if (isset($_GET["body"]) && !isset($_POST["body"])) {
	$_POST["body"] = '======'.$_GET["body"].'======';
}
?>

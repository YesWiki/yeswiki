<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

// Sauvegarde
if ( isset($_POST["submit"]) && $_POST["submit"] == 'Sauver' && isset($_POST["theme"]) ) {
	$_POST["body"] = $_POST["body"].'{{template theme="'.$_POST["theme"].'" squelette="'.$_POST["squelette"].'" style="'.$_POST["style"].'"}}';
}

// Si une valeur de body est passée en paramètre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
if (isset($_GET["body"]) && !isset($_POST["body"])) {
	$_POST["body"] = '======'.$_GET["body"].'======';
}
?>

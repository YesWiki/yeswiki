<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// Sauvegarde des metas
if (isset($_GET["newpage"]) && $_GET["newpage"]==1 && isset($_GET["theme"]) && !isset($this->page['metadatas']['theme'])) {
    $this->SaveMetaDatas($this->GetPageTag(), array('theme' => $_GET["theme"], 'style' => $_GET["style"] ?? CSS_PAR_DEFAUT , 'squelette' => $_GET["squelette"] ?? SQUELETTE_PAR_DEFAUT , 'bgimg' => $_GET["bgimg"] ?? null));
}

// Si une valeur de body est passee en paramétre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
if (isset($_GET["body"]) && !isset($_POST["body"])) {
    $_POST["body"] = '======'.$_GET["body"].'======';
}

// ajout des scripts necessaires pour le mode edition
$js = add_templates_list_js();
$this->addJavascript($js);
$this->addJavascriptFile('tools/templates/javascripts/template-edit.js');

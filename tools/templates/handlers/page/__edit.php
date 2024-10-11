<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// Si une valeur de body est passee en paramétre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
if (isset($_GET['body']) && !isset($_POST['body'])) {
    $_POST['body'] = '======' . $_GET['body'] . '======';
}

$this->addJavascriptFile('tools/templates/javascripts/change-theme.js');
$this->addJavascriptFile('tools/templates/javascripts/template-edit.js');

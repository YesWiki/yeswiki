<?php
// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

header("content-type: application/javascript");
include_once 'tools/aceditor/libs/ACeditor.js.php';
<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) {
    $wakkaConfig['lang'] = $metadatas['lang'];
} elseif (!isset($wakkaConfig['lang'])) {
    $wakkaConfig['lang'] = 'fr';
}

include_once 'tools/autoupdate/lang/autoupdate_'
    . $wakkaConfig['lang']
    . '.inc.php';

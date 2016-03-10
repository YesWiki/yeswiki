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
include_once 'tools/attach/lang/attach_'.$wakkaConfig['lang'].'.inc.php';


// theme de jplayer utilise : de base on a 2 possibilites : pink.flag ou blue.monday
$wakkaConfig['attach_jplayer_skin'] = isset($wakkaConfig['attach_jplayer_skin']) ? $wakkaConfig['attach_jplayer_skin'] : 'blue.monday';

// size of images
$wakkaConfig['image-small-width'] = isset($wakkaConfig['image-small-width']) ? $wakkaConfig['image-small-width'] : 140;
$wakkaConfig['image-small-height'] = isset($wakkaConfig['image-small-height']) ? $wakkaConfig['image-small-height'] : 97;
$wakkaConfig['image-medium-width'] = isset($wakkaConfig['image-medium-width']) ? $wakkaConfig['image-medium-width'] : 300;
$wakkaConfig['image-medium-height'] = isset($wakkaConfig['image-medium-height']) ? $wakkaConfig['image-medium-height'] : 209;
$wakkaConfig['image-big-width'] = isset($wakkaConfig['image-big-width']) ? $wakkaConfig['image-big-width'] : 780;
$wakkaConfig['image-big-height'] = isset($wakkaConfig['image-big-height']) ? $wakkaConfig['image-big-height'] : 544;

// une fonction pour passer les parametres a l'upload
$wikiClasses [] = 'ExtendAttach';

$wikiClassesContent [] = '
	// Fonction supplementaire pour paser des parametres a l\'upload
    function setParameter($parameter,$value) {
        $this->parameter[$parameter]=$value;
    }
';

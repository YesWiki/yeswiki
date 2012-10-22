<?php
if (!defined("WIKINI_VERSION")) {
	die ("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) { $wakkaConfig['lang'] = $metadatas['lang']; }
elseif (!isset($wakkaConfig['lang'])) { $wakkaConfig['lang'] = 'fr'; } 
include_once 'tools/attach/lang/attach_'.$wakkaConfig['lang'].'.inc.php';


// theme de jplayer utilise : de base on a 2 possibilites : pink.flag ou blue.monday
define ('ATTACH_JPLAYER_SKIN', 'blue.monday');

// une fonction pour passer les parametres a l'upload
$wikiClasses [] = 'ExtendAttach';

$wikiClassesContent [] = ' 
	// Fonction supplementaire pour paser des parametres a l\'upload
    function setParameter($parameter,$value) {
        $this->parameter[$parameter]=$value;
    }
';

?>

<?php
if (!defined("WIKINI_VERSION")) {
	die ("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) { $wakkaConfig['lang'] = $metadatas['lang']; }
elseif (!isset($wakkaConfig['lang'])) { $wakkaConfig['lang'] = 'fr'; } 
include_once 'tools/attach/lang/attach_'.$wakkaConfig['lang'].'.inc.php';


// Une fonction pour passer les parametres � l'upload
$wikiClasses [] = 'ExtendAttach';

$wikiClassesContent [] = ' 
	// Fonction supplementaire pour les tests ...
    function setParameter($parameter,$value) {
        $this->parameter[$parameter]=$value;
    }
';	

?>

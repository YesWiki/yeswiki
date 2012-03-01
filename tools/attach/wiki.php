<?php
if (!defined("WIKINI_VERSION")) {
	die ("acc&egrave;s direct interdit");
}

// Une fonction pour passer les parametres à l'upload
$wikiClasses [] = 'ExtendAttach';

$wikiClassesContent [] = ' 
	// Fonction supplementaire pour les tests ...
    function setParameter($parameter,$value) {
        $this->parameter[$parameter]=$value;
    }
';	

?>

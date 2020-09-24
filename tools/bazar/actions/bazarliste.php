<?php

/**
 * bazarliste : programme affichant les fiches du bazar sous forme de liste accordeon (ou autre template).
 *
 *
 *
 *@author        Florian SCHMITT <florian@outils-reseaux.org>
 *
 *@version       $Revision: 1.5 $ $Date: 2010/03/04 14:19:03 $
 **/

// test de sécurité pour vérifier si on passe par wiki
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

global $bazarFiche;

$this->AddJavascriptFile('tools/bazar/libs/bazar.js');

// Recuperation de tous les parametres
$GLOBALS['params'] = getAllParameters($this);
// tableau des fiches correspondantes aux critères
if (is_array($GLOBALS['params']['idtypeannonce'])) {
    $results = array();
    foreach ($GLOBALS['params']['idtypeannonce'] as $formId) {
        $results = array_merge(
            $results,
            $bazarFiche->search(['tabquery' => $GLOBALS['params']['query'], 'formsIds' => [$formId]])
        );
    }
} else {
    $results = $bazarFiche->search(['tabquery' => $GLOBALS['params']['query']]);
}

// affichage à l'écran
echo displayResultList($results, $GLOBALS['params'], false);

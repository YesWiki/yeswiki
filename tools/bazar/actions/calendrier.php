<?php
/**
* calendrier : programme affichant les evenements du bazar sous forme de Calendrier dans wikini
*
*
* @package Bazar
*
* @author        Florian SCHMITT <florian@outils-reseaux.org>
* @version       $Revision: 1.1 $ $Date: 2011-03-22 09:33:24 $
*
*/

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

// a la place du choix par défaut, on affiche en calendrier
if ($GLOBALS['params']['template'] == $GLOBALS['wiki']->config['default_bazar_template']) {
    $GLOBALS['params']['template'] = 'calendar.tpl.html';
}


// affichage à l'écran
echo displayResultList($results, $GLOBALS['params'], false);

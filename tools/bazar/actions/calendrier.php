<?php
/**
* calendrier : programme affichant les evenements du bazar sous forme de Calendrier dans wikini
*
*/

// pour retro-compatibilité
$this->setParameter('template', 'calendar');
include( __DIR__.'/bazarliste.php');

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
            $bazarFiche->search(['queries' => $GLOBALS['params']['query'], 'formsIds' => [$formId]])
        );
    }
} else {
    $results = $bazarFiche->search(['queries' => $GLOBALS['params']['query']]);
}

// a la place du choix par défaut, on affiche en calendrier
if ($GLOBALS['params']['template'] == $GLOBALS['wiki']->config['default_bazar_template']) {
    $GLOBALS['params']['template'] = 'calendar.tpl.html';
}


// affichage à l'écran
echo displayResultList($results, $GLOBALS['params'], false);

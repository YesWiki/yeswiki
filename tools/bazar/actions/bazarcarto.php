<?php

/**
 *  Programme gerant les fiches bazar depuis une interface de type geographique.
 **/

use YesWiki\Bazar\Service\FicheManager;

// pour retro-compatibilité
$this->setParameter('template', 'map');
include(__DIR__.'/bazarliste.php');

$ficheManager = $this->services->get(FicheManager::class);

$this->AddJavascriptFile('tools/bazar/libs/bazar.js');

// test de sécurité pour vérifier si on passe par wiki
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// Recuperation de tous les parametres
$GLOBALS['params'] = getAllParameters($this);
// tableau des fiches correspondantes aux critères
if (is_array($GLOBALS['params']['idtypeannonce'])) {
    $fiches = array();
    foreach ($GLOBALS['params']['idtypeannonce'] as $formId) {
        $fiches = array_merge(
            $fiches,
            $ficheManager->search(['queries' => $GLOBALS['params']['query'], 'formsIds' => [$formId]])
        );
    }
} else {
    $fiches = $ficheManager->search(['queries' => $GLOBALS['params']['query']]);
}

// a la place du choix par défaut, on affiche en carte
if ($GLOBALS['params']['template'] == $GLOBALS['wiki']->config['default_bazar_template']) {
    $GLOBALS['params']['template'] = 'map.tpl.html';
}

// affichage à l'écran
echo displayResultList($fiches, $GLOBALS['params'], false);

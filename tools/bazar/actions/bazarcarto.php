<?php

/**
 *  Programme gerant les fiches bazar depuis une interface de type geographique.
 **/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+


// test de sécurité pour vérifier si on passe par wiki
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// Recuperation de tous les parametres
$GLOBALS['params'] = getAllParameters($this);
// tableau des fiches correspondantes aux critères
if (is_array($GLOBALS['params']['idtypeannonce'])) {
    $results = array();
    foreach ($GLOBALS['params']['idtypeannonce'] as $formid) {
        $results = array_merge(
            $results,
            baz_requete_recherche_fiches($GLOBALS['params']['query'], 'alphabetique', $formid, '', 1, '', '', true, '')
        );
    }
} else {
    $results = baz_requete_recherche_fiches($GLOBALS['params']['query'], 'alphabetique', '', '', 1, '', '', true, '');
}

// a la place du choix par défaut, on affiche en carte
if ($GLOBALS['params']['template'] == $GLOBALS['wiki']->config['default_bazar_template']) {
    $GLOBALS['params']['template'] = 'map.tpl.html';
}

// affichage à l'écran
echo displayResultList($results, $GLOBALS['params'], false);

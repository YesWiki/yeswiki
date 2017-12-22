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

// a la place du choix par défaut, on affiche en calendrier
if ($GLOBALS['params']['template'] == $GLOBALS['wiki']->config['default_bazar_template']) {
    $GLOBALS['params']['template'] = 'calendar.tpl.html';
}

$GLOBALS['params']['minical'] = $this->GetParameter('minical');

// affichage à l'écran
echo displayResultList($results, $GLOBALS['params'], false);

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

// on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
    $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
}
++$GLOBALS['_BAZAR_']['nbbazarliste'];

// Recuperation de tous les parametres
$params = getAllParameters($this);
if (empty($this->GetParameter('template'))) {
    $params['template'] = 'map.tpl.html';
}

// tableau des fiches correspondantes aux critères
if (is_array($params['idtypeannonce'])) {
    $results = array();
    foreach ($params['idtypeannonce'] as $formid) {
        $results = array_merge(
            $results,
            baz_requete_recherche_fiches($params['query'], 'alphabetique', $formid, '', 1, '', '', true, '')
        );
    }
} else {
    $results = baz_requete_recherche_fiches($params['query'], 'alphabetique', '', '', 1, '', '', true, '');
}

// affichage à l'écran
echo displayResultList($results, $params, false);

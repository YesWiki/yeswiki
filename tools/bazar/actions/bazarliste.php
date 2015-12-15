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

// on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
if (!isset($GLOBALS['nbbazarliste'])) {
    $GLOBALS['nbbazarliste'] = 0;
}
++$GLOBALS['nbbazarliste'];

// Recuperation de tous les parametres
$params = getAllParameters($this);

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

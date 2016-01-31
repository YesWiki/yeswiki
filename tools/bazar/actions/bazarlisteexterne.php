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

$url = $this->getParameter('url');
if (empty($url)) {
    exit('<div class="alert alert-danger">action bazarlisteexterne : parametre url obligatoire.</div>');
}

// Recuperation de tous les parametres
$params = getAllParameters($this);

// tableau des fiches correspondantes aux critères
$i = 0;
if (is_array($params['idtypeannonce'])) {
    $results = array();
    $form = array();
    foreach ($params['idtypeannonce'] as $formid) {
        // requete pour obtenir le formulaire et les listes
        if (!isset($form[$formid])) {
            $json = file_get_contents($url.'/wakka.php?wiki=BazaR/json&demand=forms&form='.$formid);
            $form = $form + json_decode($json, true);
        }

        // requete pour obtenir les fiches
        $json = file_get_contents($url.'/wakka.php?wiki=BazaR/json&demand=entries&form='.$formid);
        $results = json_decode($json, true);
    }
} else {
    // requete pour obtenir le formulaire et les listes
    if (!isset($form[$formid])) {
        $json = file_get_contents($url.'/wakka.php?wiki=BazaR/json&demand=forms&form='.$formid);
        $form = $form + json_decode($json, true);
    }
    $json = file_get_contents($url.'/wakka.php?wiki=BazaR/json&demand=entries&form='.$formid);
    $results = json_decode($json, true);
}

// affichage à l'écran
echo displayResultList($results, $params, false, $form);

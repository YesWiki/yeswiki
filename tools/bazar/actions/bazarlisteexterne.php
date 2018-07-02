<?php

/**
 * Bazarlisteexterne : affiche des fiches externes sous forme de liste
 *
 * @author Florian SCHMITT <mrflos@lilo.org>
 **/

// test de sécurité pour vérifier si on passe par wiki
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// on compte les actions bazarliste afin de différencier les instances
if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
    $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
}
++$GLOBALS['_BAZAR_']['nbbazarliste'];

$url = $this->getParameter('url');
if (empty($url)) {
    exit(
        '<div class="alert alert-danger">Action bazarlisteexterne :'
        .' parametre url obligatoire.</div>'
    );
}
$arr = explode("/wakka.php", $url, 2);
$arr = explode("/?", $arr[0], 2);
$url = $arr[0];

// Recuperation de tous les parametres
$params = getAllParameters($this);
$querystring = '';
if (is_array($params['query'])) {
    foreach ($params['query'] as $key => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $querystring .= $key.'='.$value.'|';
    }
    $querystring = '&query='.htmlspecialchars(substr($querystring, 0, -1));
}

// tableau des fiches correspondantes aux critères
$i = 0;
if (is_array($params['idtypeannonce'])) {
    $results = array();
    $form = array();
    foreach ($params['idtypeannonce'] as $formid) {
        // requete pour obtenir le formulaire et les listes
        if (!isset($form[$formid])) {
            $json = getCachedUrlContent($url.'?BazaR/json&demand=forms');
            $jsonc = json_decode($json, true);
            if ($jsonc) {
                $form = $form + $jsonc;
            } else {
                echo '<div class="alert alert-danger">Erreur bazarlisteexterne :'
                  .'contenu des formulaires mal formaté.</div>';
                return;
            }
        }

        // requete pour obtenir les fiches
        $json = getCachedUrlContent(
            $url.'?BazaR/json&demand=entries&form='.$formid.$querystring
        );
        $results = json_decode($json, true);
    }
} else {
    // requete pour obtenir le formulaire et les listes
    if (!isset($form[$formid])) {
        $json = getCachedUrlContent($url.'?BazaR/json&demand=forms');
        $jsonc = json_decode($json, true);
        if ($jsonc) {
            $form = $form + $jsonc;
        } else {
            echo '<div class="alert alert-danger">Erreur bazarlisteexterne :'
            .' contenu des formulaires mal formaté.</div>';
            return;
        }
    }
    $json = getCachedUrlContent(
        $url.'?BazaR/json&demand=entries&form='.$formid.$querystring
    );
    $results = json_decode($json, true);
}

if ($results) {
    // affichage à l'écran
    echo displayResultList($results, $params, false, $form);
}

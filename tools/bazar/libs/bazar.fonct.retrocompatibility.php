<?php

function baz_insertion_fiche($data)
{
    $data['antispam'] = 1;
    return $GLOBALS['bazarFiche']->create($data['id_fiche'], $data);
}

function baz_mise_a_jour_fiche($data)
{
    return $GLOBALS['bazarFiche']->update($data['id_fiche'], $data);
}

function baz_suppression($idFiche)
{
    return $GLOBALS['bazarFiche']->delete($idFiche);
}

function baz_valeurs_fiche($idFiche)
{
    return $GLOBALS['bazarFiche']->getOne($idFiche);
}

function baz_requete_recherche_fiches(
    $tableau_criteres = '',
    $tri = '',
    $id = '',
    $categorie_fiche = '',
    $statut = 1,
    $personne = '',
    $nb_limite = '',
    $motcles = true,
    $q = '',
    $facettesearch = 'OR'
)
{
    if( $id==='' ) $id = [];

    return $GLOBALS['bazarFiche']->search([
        'queries' => $tableau_criteres,
        'formsIds' => $id, // Types de fiches (par ID de formulaire)
        'user' => $personne, // N'affiche que les fiches d'un utilisateur
        'keywords' => $q, // Mots-clés pour la recherche fulltext
        'searchOperator' => $facettesearch // Opérateur à appliquer aux mots-clés
    ]);
}

function validateForm($data)
{
    try {
        $GLOBALS['bazarFiche']->validate($data);
        return array('result' => true);
    } catch(\Exception $e) {
        return array('result' => false, 'error' => $e->getMessage());
    }
}

function searchResultstoArray($pages, $params, $formtab = '')
{
    $fiches = array();

    foreach ($pages as $page) {
        $fiche = $GLOBALS['bazarFiche']->decode($page['body']);
        $GLOBALS['bazarFiche']->addDisplayData($fiche, false, $params['correspondance']);
        $fiches[$fiche['id_fiche']] = $fiche;
    }

    return $fiches;
}

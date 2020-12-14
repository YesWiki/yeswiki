<?php

use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;

/**
 * @deprecated Use FicheManager::create
 */
function baz_insertion_fiche($data)
{
    $data['antispam'] = 1;
    return $GLOBALS['wiki']->services->get(FicheManager::class)->create($data['id_fiche'], $data);
}

/**
 * @deprecated Use FicheManager::update
 */
function baz_mise_a_jour_fiche($data)
{
    return $GLOBALS['wiki']->services->get(FicheManager::class)->update($data['id_fiche'], $data);
}

/**
 * @deprecated Use FicheManager::delete
 */
function baz_suppression($idFiche)
{
    return $GLOBALS['wiki']->services->get(FicheManager::class)->delete($idFiche);
}

/**
 * @deprecated Use FicheManager::getOne
 */
function baz_valeurs_fiche($idFiche)
{
    return $GLOBALS['wiki']->services->get(FicheManager::class)->getOne($idFiche);
}

/**
 * @deprecated Use FicheManager::search
 */
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
) {
    if ($id==='') {
        $id = [];
    }

    $fiches = $GLOBALS['wiki']->services->get(FicheManager::class)->search([
        'queries' => $tableau_criteres,
        'formsIds' => $id, // Types de fiches (par ID de formulaire)
        'user' => $personne, // N'affiche que les fiches d'un utilisateur
        'keywords' => $q, // Mots-clés pour la recherche fulltext
        'searchOperator' => $facettesearch // Opérateur à appliquer aux mots-clés
    ]);

    // Re-encode fiche as Wiki page
    return array_map(function ($fiche) {
        return ['body' => json_encode($fiche)];
    }, $fiches);
}

/**
 * @deprecated Use FicheManager::validate
 */
function validateForm($data)
{
    try {
        $GLOBALS['wiki']->services->get(FicheManager::class)->validate($data);
        return array('result' => true);
    } catch (\Exception $e) {
        return array('result' => false, 'error' => $e->getMessage());
    }
}

/**
 * @deprecated
 */
function searchResultstoArray($pages, $params, $formtab = '')
{
    $fiches = array();

    foreach ($pages as $page) {
        $fiche = $GLOBALS['wiki']->services->get(FicheManager::class)->decode($page['body']);
        $GLOBALS['wiki']->services->get(FicheManager::class)->appendDisplayData($fiche, false, $params['correspondance']);
        $fiches[$fiche['id_fiche']] = $fiche;
    }

    return $fiches;
}

/**
 * @deprecated Use FicheManager::formatDataBeforeSave
 */
function baz_requete_bazar_fiche($data)
{
    return $GLOBALS['wiki']->services->get(FicheManager::class)->formatDataBeforeSave($data);
}

/**
 * @deprecated Use FormManager::getOne, FormManager::getMany or FormManager::getAll
 */
function baz_valeurs_formulaire($idformulaire = [])
{
    $formManager = $GLOBALS['wiki']->services->get(FormManager::class);

    if (is_array($idformulaire) and count($idformulaire) > 0) {
        return $formManager->getMany($idformulaire);
    } elseif ($idformulaire != '' and !is_array($idformulaire)) {
        return $formManager->getOne($idformulaire);
    } else {
        return $formManager->getAll();
    }
}

/**
 * @deprecated Use FormManager::prepareData
 */
function bazPrepareFormData($form)
{
    return $GLOBALS['wiki']->services->get(FormManager::class)->prepareData($form);
}

/**
 * @deprecated Use FormManager::parseTemplate
 */
function formulaire_valeurs_template_champs($template)
{
    return $GLOBALS['wiki']->services->get(FormManager::class)->parseTemplate($template);
}

/**
 * @deprecated Use FormManager::findNewId
 */
function baz_nextId()
{
    return $GLOBALS['wiki']->services->get(FormManager::class)->findNewId();
}

/**
 * @deprecated Use BazarField::canEdit
 */
function testACLsiSaisir($mode, $tableau_template, $valeurs_fiche)
{
    $acl = empty($tableau_template[12]) ? '' : $tableau_template[12] ; // acl pour l'écriture

    if (isset($valeurs_fiche['id_fiche'])) {
        $tag = $valeurs_fiche['id_fiche'] ;
    } else {
        $tag = '' ;
    }
    $mode_creation = '' ;
    if ($tag == '') {
        $mode_creation = 'creation' ;
    }

    return $mode == 'saisie' && !empty($acl) && !$GLOBALS['wiki']->CheckACL($acl, null, true, $tag, $mode_creation)  ;
}

/**
 * @deprecated Use ListManager::getOne or ListManager::getAll
 */
function baz_valeurs_liste($idliste = '')
{
    $idliste = trim($idliste);
    if ($idliste != '') {
        return $GLOBALS['wiki']->services->get(ListManager::class)->getOne($idliste);
    } else {
        return $GLOBALS['wiki']->services->get(ListManager::class)->getAll();
    }
}

<?php

use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Bazar\Service\FormManager;

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
    $GLOBALS['wiki']->services->get(FormManager::class)->prepareData($form);
}

/**
 * @deprecated Use FormManager::parseTemplate
 */
function formulaire_valeurs_template_champs($template)
{
    return $GLOBALS['wiki']->services->get(FormManager::class)->parseTemplate($template);
}

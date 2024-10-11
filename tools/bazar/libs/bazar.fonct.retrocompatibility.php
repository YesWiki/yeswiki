<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Controller\FormController;
use YesWiki\Bazar\Controller\ListController;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\TemplateEngine;

/**
 * @deprecated Use EntryManager::create
 */
function baz_insertion_fiche($data)
{
    $data['antispam'] = 1;

    return $GLOBALS['wiki']->services->get(EntryManager::class)->create($data['id_fiche'], $data);
}

/**
 * @deprecated Use EntryManager::update
 */
function baz_mise_a_jour_fiche($data)
{
    return $GLOBALS['wiki']->services->get(EntryManager::class)->update($data['id_fiche'], $data);
}

/**
 * @deprecated Use EntryManager::delete
 */
function baz_suppression($idFiche)
{
    return $GLOBALS['wiki']->services->get(EntryManager::class)->delete($idFiche);
}

/**
 * @deprecated Use EntryManager::getOne
 */
function baz_valeurs_fiche($idFiche)
{
    return $GLOBALS['wiki']->services->get(EntryManager::class)->getOne($idFiche);
}

/**
 * @deprecated Use EntryManager::search
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
    if ($id === '') {
        $id = [];
    }

    $fiches = $GLOBALS['wiki']->services->get(EntryManager::class)->search([
        'queries' => $tableau_criteres,
        'formsIds' => $id, // Types de fiches (par ID de formulaire)
        'user' => $personne, // N'affiche que les fiches d'un utilisateur
        'keywords' => $q, // Mots-clés pour la recherche fulltext
        'searchOperator' => $facettesearch, // Opérateur à appliquer aux mots-clés
    ]);

    // Re-encode fiche as Wiki page
    return array_map(function ($fiche) {
        return ['body' => json_encode($fiche)];
    }, $fiches);
}

/**
 * @deprecated Use EntryManager::validate
 */
function validateForm($data)
{
    try {
        $GLOBALS['wiki']->services->get(EntryManager::class)->validate($data);

        return ['result' => true];
    } catch (\Exception $e) {
        return ['result' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @deprecated
 */
function searchResultstoArray($pages, $params, $formtab = '')
{
    $fiches = [];

    foreach ($pages as $page) {
        $fiche = $GLOBALS['wiki']->services->get(EntryManager::class)->decode($page['body']);
        $GLOBALS['wiki']->services->get(EntryManager::class)->appendDisplayData($fiche, false, $params['correspondance'] ?? '', $page);
        $fiches[$fiche['id_fiche']] = $fiche;
    }

    return $fiches;
}

/**
 * @deprecated Use EntryManager::formatDataBeforeSave
 */
function baz_requete_bazar_fiche($data)
{
    return $GLOBALS['wiki']->services->get(EntryManager::class)->formatDataBeforeSave($data);
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
    $acl = empty($tableau_template[12]) ? '' : $tableau_template[12]; // acl pour l'écriture

    if (isset($valeurs_fiche['id_fiche'])) {
        $tag = $valeurs_fiche['id_fiche'];
    } else {
        $tag = '';
    }
    $mode_creation = '';
    if ($tag == '') {
        $mode_creation = 'creation';
    }

    return $mode == 'saisie' && !empty($acl) && !$GLOBALS['wiki']->CheckACL($acl, null, true, $tag, $mode_creation);
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

/**
 * @deprecated Use ListController
 */
function baz_gestion_listes()
{
    if ($_GET['action'] == BAZ_ACTION_MODIFIER_LISTE) {
        return $GLOBALS['wiki']->services->get(ListController::class)->update($_GET['idliste']);
    } elseif ($_GET['action'] == BAZ_ACTION_NOUVELLE_LISTE) {
        return $GLOBALS['wiki']->services->get(ListController::class)->create();
    } elseif ($_GET['action'] == BAZ_ACTION_SUPPRIMER_LISTE) {
        return $GLOBALS['wiki']->services->get(ListController::class)->delete($_GET['idliste']);
    } else {
        return $GLOBALS['wiki']->services->get(ListController::class)->displayAll();
    }
}

/**
 * @deprecated Use FormController
 */
function baz_gestion_formulaire()
{
    if ($_GET['action'] === 'modif') {
        return $GLOBALS['wiki']->services->get(FormController::class)->update($_GET['idformulaire']);
    } elseif ($_GET['action'] === 'new') {
        return $GLOBALS['wiki']->services->get(FormController::class)->create();
    } elseif ($_GET['action'] === 'empty') {
        return $GLOBALS['wiki']->services->get(FormController::class)->clear($_GET['idformulaire']);
    } elseif ($_GET['action'] === 'delete') {
        return $GLOBALS['wiki']->services->get(FormController::class)->delete($_GET['idformulaire']);
    } else {
        return $GLOBALS['wiki']->services->get(FormController::class)->displayAll();
    }
}

/**
 * @deprecated Use FormController::create or FormController::update
 */
function baz_formulaire_des_formulaires($mode, $form = '')
{
    if ($form !== '') {
        return $GLOBALS['wiki']->services->get(FormController::class)->update($form['bn_id_nature']);
    } else {
        return $GLOBALS['wiki']->services->get(FormController::class)->create();
    }
}

/**
 * @deprecated Use FormController::selectForm, FormController::create or FormController::update
 */
function baz_formulaire($mode, $url = '', $valeurs = '')
{
    switch ($mode) {
        case BAZ_CHOISIR_TYPE_FICHE:
            return $GLOBALS['wiki']->services->get(EntryController::class)->selectForm();
        case BAZ_ACTION_NOUVEAU:
            return $GLOBALS['wiki']->services->get(EntryController::class)->create($_GET['id_typeannonce'] ?? $_GET['id'] ?? $_POST['id_typeannonce']);
        case BAZ_ACTION_MODIFIER:
            return $GLOBALS['wiki']->services->get(EntryController::class)->update($_GET['id_fiche'] ?? $_POST['id_typeannonce']);
    }
}

/**
 * @deprecated Use FormController::create or FormController::update
 */
function baz_afficher_formulaire_fiche($mode, $url = '', $valeurs = '')
{
    switch ($mode) {
        case BAZ_ACTION_NOUVEAU:
            return $GLOBALS['wiki']->services->get(EntryController::class)->create($_GET['id_typeannonce'] ?? $_GET['id'] ?? $_POST['id_typeannonce']);
        case BAZ_ACTION_MODIFIER:
            return $GLOBALS['wiki']->services->get(EntryController::class)->update($_GET['id_fiche'] ?? $_POST['id_typeannonce']);
    }
}

/**
 * @deprecated Use Guard::isAllowed
 */
function baz_a_le_droit($demande = 'saisie_fiche', $id = '')
{
    return $GLOBALS['wiki']->services->get(Guard::class)->isAllowed($demande, $id);
}

/**
 * @deprecated Use EntryController::view
 */
function baz_voir_fiche($danslappli, $idfiche, $form = '')
{
    try {
        $output = $GLOBALS['wiki']->services->get(EntryController::class)->view($idfiche, '', $danslappli);
    } catch (Throwable $t) {
        return $GLOBALS['wiki']->services->get(TemplateEngine::class)
            ->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PERFORMABLE_ERROR') . "<br/>{$t->getMessage()} in <i>{$t->getFile()}</i> on line <i>{$t->getLine()}</i>",
            ]);
    }

    return $output;
}

/**
 * @deprecated Use WikiAction::formatArguments
 */
function getAllParameters($wiki)
{
    return [];
}

/**
 * @deprecated Use WikiAction::formatArguments
 */
function getAllParameters_carto($wiki)
{
    return [];
}

/**
 * @deprecated Call BazarListeAction
 */
function displayResultList($entries, $params = [], $info_nb = true)
{
    $entryController = $GLOBALS['wiki']->services->get(EntryController::class);

    return $entryController->renderBazarList($entries, $params, $info_nb);
}

/**
 * @deprecated Call BazarListeAction
 */
function baz_rechercher($typeannonce = '', $categorienature = '')
{
    return $GLOBALS['wiki']->Action('bazarliste', 0, ['idtypeannonce' => $typeannonce]);
}

<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Controller\FormController;
use YesWiki\Bazar\Controller\ListController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiAction;

class BazarAction extends YesWikiAction
{
    public const VARIABLE_VOIR = 'vue';
    public const VARIABLE_ACTION = 'action';
    
    // Premier niveau d'action : pour toutes les fiches
    public const VOIR_DEFAUT = 'formulaire'; // Recherche
    public const VOIR_CONSULTER = 'consulter'; // Recherche
    public const VOIR_MES_FICHES = 'mes_fiches';
    public const VOIR_SAISIR = 'saisir';
    public const VOIR_FORMULAIRE = 'formulaire';
    public const VOIR_LISTES = 'listes';
    public const VOIR_IMPORTER = 'importer';
    public const VOIR_EXPORTER = 'exporter';
    
    // Second : actions du choix de premier niveau.
    public const MOTEUR_RECHERCHE = 'recherche';
    public const CHOISIR_TYPE_FICHE = 'choisir_type_fiche'; // Modifier le formulaire de creation des fiches

    // Entries
    public const ACTION_ENTRY_VIEW = 'voir_fiche';
    public const ACTION_ENTRY_CREATE = 'saisir_fiche';
    public const ACTION_ENTRY_EDIT = 'modif_fiche';
    public const ACTION_ENTRY_DELETE = 'supprimer';

    // Forms
    public const ACTION_FORM_CREATE = 'new';
    public const ACTION_FORM_EDIT = 'modif';
    public const ACTION_FORM_DELETE = 'delete';
    public const ACTION_FORM_EMPTY = 'empty';

    // Lists
    public const ACTION_LIST_CREATE = 'saisir_liste';
    public const ACTION_LIST_EDIT = 'modif_liste';
    public const ACTION_LIST_DELETE = 'supprimer_liste';

    public const ACTION_PUBLIER = 'publier'; // Valider la fiche
    public const ACTION_PAS_PUBLIER = 'pas_publier'; // Invalider la fiche

    function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $listController = $this->getService(ListController::class);
        $formController = $this->getService(FormController::class);
        $entryController = $this->getService(EntryController::class);

        // TODO put in all bazar templates
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        $this->arguments = getAllParameters($this->wiki);
        $GLOBALS['params'] = $this->arguments;

        $view = $this->arguments[self::VARIABLE_VOIR];
        $action = $this->arguments[self::VARIABLE_ACTION];

        // si c'est demandé, on affiche le menu
        if ($this->arguments['voirmenu'] != '0') {
            $menuitems = array_map('trim', explode(',', $this->arguments['voirmenu']));
            echo baz_afficher_menu($menuitems);
        }

        switch ($view) {
            case self::VOIR_CONSULTER:
                switch ($action) {
                    case self::MOTEUR_RECHERCHE:
                        return baz_rechercher(
                            $this->arguments['idtypeannonce'],
                            $this->arguments['categorienature']
                        );
                    case self::ACTION_ENTRY_VIEW:
                        if (isset($_REQUEST['id_fiche'])) {
                            $fiche = $entryManager->getOne($_REQUEST['id_fiche'], false, !empty($_REQUEST['time']) ? $_REQUEST['time'] : '');
                            if (!$fiche) {
                                return '<div class="alert alert-danger">'
                                    ._t('BAZ_PAS_DE_FICHE_AVEC_CET_ID').' : '
                                    .htmlspecialchars($_REQUEST['id_fiche']).'</div>';
                            } else {
                                return baz_voir_fiche(1, $fiche);
                            }
                        } else {
                            return '<div class="alert alert-danger">'
                                ._t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE').'</div>';
                        }
                    default:
                        return baz_rechercher(
                            isset($_REQUEST['id_typeannonce']) ?
                                $_REQUEST['id_typeannonce'] : $this->arguments['idtypeannonce'],
                            $this->arguments['categorienature']
                        );
                }
            case self::VOIR_MES_FICHES:
                return baz_afficher_liste_fiches_utilisateur();
            case self::VOIR_SAISIR:
                switch ($action) {
                    case self::ACTION_ENTRY_CREATE:
                        return $entryController->create($_REQUEST['id_typeannonce'] ?? $_REQUEST['id'] ?? $this->arguments['idtypeannonce'][0]);
                    case self::ACTION_ENTRY_EDIT:
                        return $entryController->update($_REQUEST['id_fiche']);
                    case self::ACTION_ENTRY_DELETE:
                        return $entryController->delete($_REQUEST['id_fiche']);
                    case self::ACTION_PUBLIER:
                        return publier_fiche(1).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                    case self::ACTION_PAS_PUBLIER:
                        return publier_fiche(0).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                    default:
                        if( !empty($this->arguments['idtypeannonce']) ) {
                            return $entryController->create($this->arguments['idtypeannonce'][0]);
                        } else {
                            return $entryController->selectForm();
                        }
                }
                break;
            case self::VOIR_FORMULAIRE:
                switch($action) {
                    case self::ACTION_FORM_CREATE:
                        return $formController->create();
                    case self::ACTION_FORM_EDIT:
                        return $formController->update($_GET['idformulaire']);
                    case self::ACTION_FORM_DELETE:
                        return $formController->delete($_GET['idformulaire']);
                    case self::ACTION_FORM_EMPTY:
                        return $formController->empty($_GET['idformulaire']);
                    default:
                        return $formController->displayAll(!empty($_GET['msg']) ? $_GET['msg'] : null);
                }
            case self::VOIR_LISTES:
                switch($action) {
                    case self::ACTION_LIST_CREATE:
                        return $listController->create();
                    case self::ACTION_LIST_EDIT:
                        return $listController->update($_GET['idliste']);
                    case self::ACTION_LIST_DELETE:
                        return $listController->delete($_GET['idliste']);
                    default:
                        return $listController->displayAll();
                }
            case self::VOIR_IMPORTER:
                return baz_afficher_formulaire_import();
            case self::VOIR_EXPORTER:
                return baz_afficher_formulaire_export();
            default:
                return baz_rechercher(
                    isset($_REQUEST['id_typeannonce']) ? $_REQUEST['id_typeannonce'] : $this->arguments['idtypeannonce']
                );
        }

    }
}

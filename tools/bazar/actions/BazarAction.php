<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Controller\FormController;
use YesWiki\Bazar\Controller\ListController;
use YesWiki\Core\YesWikiAction;

class BazarAction extends YesWikiAction
{
    public const VARIABLE_VOIR = 'vue';
    public const VARIABLE_ACTION = 'action';
    
    // Premier niveau d'action : pour toutes les fiches
    public const VOIR_DEFAUT = 'formulaire'; // Recherche
    public const VOIR_CONSULTER = 'consulter'; // Recherche
    public const VOIR_SAISIR = 'saisir';
    public const VOIR_FORMULAIRE = 'formulaire';
    public const VOIR_LISTES = 'listes';
    public const VOIR_IMPORTER = 'importer';
    public const VOIR_EXPORTER = 'exporter';

    // Entries
    public const MOTEUR_RECHERCHE = 'recherche';
    public const ACTION_ENTRY_VIEW = 'voir_fiche';
    public const ACTION_ENTRY_CREATE = 'saisir_fiche';
    public const ACTION_ENTRY_EDIT = 'modif_fiche';
    public const ACTION_ENTRY_DELETE = 'supprimer';

    // Forms
    public const ACTION_FORM_CREATE = 'new';
    public const ACTION_FORM_EDIT = 'modif';
    public const ACTION_FORM_DELETE = 'delete';
    public const ACTION_FORM_EMPTY = 'empty';
    public const CHOISIR_TYPE_FICHE = 'choisir_type_fiche';

    // Lists
    public const ACTION_LIST_CREATE = 'saisir_liste';
    public const ACTION_LIST_EDIT = 'modif_liste';
    public const ACTION_LIST_DELETE = 'supprimer_liste';

    public const ACTION_PUBLIER = 'publier'; // Valider la fiche
    public const ACTION_PAS_PUBLIER = 'pas_publier'; // Invalider la fiche

    function formatArguments($arg)
    {
        return([
            'action' => $arg['action'] ?? $_GET['action'],
            'vue' => $arg['vue'] ?? $_GET['vue'] ?? 'formulaire',
            // afficher le menu de vues bazar ?
            'voirmenu' => $arg['voirmenu'] ?? $this->params->get('baz_menu'),
            // Identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $this->formatArray($_REQUEST['id_typeannonce'] ?? $arg['id'] ?? $arg['idtypeannonce'] ?? $_GET['id']),
            // Permet de rediriger vers une url après saisie de fiche
            'redirecturl' => $arg['redirecturl']
        ]);
    }

    function run()
    {
        $listController = $this->getService(ListController::class);
        $formController = $this->getService(FormController::class);
        $entryController = $this->getService(EntryController::class);

        // TODO put in all bazar templates
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        // TODO virer
//        $this->arguments = getAllParameters($this->wiki);
//        $GLOBALS['params'] = $this->arguments;

        $view = $this->arguments[self::VARIABLE_VOIR];
        $action = $this->arguments[self::VARIABLE_ACTION];

        // Display menu, unless we explicitly don't want to see it
        if ($this->arguments['voirmenu'] != '0') {
            echo $this->render('@bazar/menu.twig', [
                'menuItems' => array_map('trim', explode(',', $this->arguments['voirmenu'])),
                'view' => $view
            ]);
        }

        switch ($view) {
            case self::VOIR_SAISIR:
                switch ($action) {
                    case self::ACTION_ENTRY_CREATE:
                        return $entryController->create($_REQUEST['id_typeannonce'] ?? $_REQUEST['id'] ?? $this->arguments['idtypeannonce'][0], $this->arguments['redirecturl']);
                    case self::ACTION_ENTRY_EDIT:
                        return $entryController->update($_REQUEST['id_fiche']);
                    case self::ACTION_ENTRY_DELETE:
                        return $entryController->delete($_REQUEST['id_fiche']);
                    case self::ACTION_PUBLIER:
                        publier_fiche(1);
                        return $entryController->view($_REQUEST['id_fiche']);
                    case self::ACTION_PAS_PUBLIER:
                        publier_fiche(0);
                        return $entryController->view($_REQUEST['id_fiche']);
                    case self::CHOISIR_TYPE_FICHE:
                        return $entryController->selectForm();
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
            case self::VOIR_CONSULTER:
            case self::VOIR_DEFAUT:
            default:
                switch ($action) {
                    case self::ACTION_ENTRY_VIEW:
                        return $entryController->view($_REQUEST['id_fiche'], $_REQUEST['time'] ?? '');
                    case self::MOTEUR_RECHERCHE:
                    default:
                        // TODO call bazarcarto or calendrier if the template is matching
                        return $this->callAction('bazarliste', $this->arguments);
                }
        }

    }
}

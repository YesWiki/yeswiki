<?php

use YesWiki\Bazar\Controller\FormController;
use YesWiki\Bazar\Controller\ListController;
use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Core\YesWikiAction;

class BazarAction extends YesWikiAction
{
    public const VARIABLE_VOIR = 'vue';
    public const VARIABLE_ACTION = 'action';
    
    // Premier niveau d'action : pour toutes les fiches
    public const VOIR_DEFAUT = 'formulaire'; // Recherche
    public const VOIR_CONSULTER = 'consulter'; // Recherche
    public const VOIR_MES_FICHES = 'mes_fiches';
    public const VOIR_S_ABONNER = 'rss';
    public const VOIR_SAISIR = 'saisir';
    public const VOIR_FORMULAIRE = 'formulaire';
    public const VOIR_LISTES = 'listes';
    public const VOIR_IMPORTER = 'importer';
    public const VOIR_EXPORTER = 'exporter';
    
    // Second : actions du choix de premier niveau.
    public const MOTEUR_RECHERCHE = 'recherche';
    public const CHOISIR_TYPE_FICHE = 'choisir_type_fiche'; // Modifier le formulaire de creation des fiches
    public const VOIR_FICHE = 'voir_fiche';
    public const ACTION_NOUVEAU = 'saisir_fiche';
    public const ACTION_NOUVEAU_V = 'sauver_fiche'; // Creation apres validation
    public const ACTION_MODIFIER = 'modif_fiche';
    public const ACTION_MODIFIER_V = 'modif_sauver_fiche';

    // Formulaires
    public const ACTION_FORM_CREATE = 'new';
    public const ACTION_FORM_EDIT = 'modif';
    public const ACTION_FORM_DELETE = 'delete';
    public const ACTION_FORM_EMPTY = 'empty';

    // Listes
    public const ACTION_LIST_CREATE = 'saisir_liste';
    public const ACTION_LIST_EDIT = 'modif_liste';
    public const ACTION_LIST_DELETE = 'supprimer_liste';

    public const ACTION_SUPPRESSION = 'supprimer';
    public const ACTION_PUBLIER = 'publier'; // Valider la fiche
    public const ACTION_PAS_PUBLIER = 'pas_publier'; // Invalider la fiche
    public const LISTE_RSS = 'rss'; // Tous les flux  depend de s'abonner
    public const VOIR_FLUX_RSS = 'affiche_rss'; // Un flux

    function run()
    {
        $ficheManager = $this->getService(FicheManager::class);
        $listController = $this->getService(ListController::class);
        $formController = $this->getService(FormController::class);

        // TODO put in templates
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        $this->arguments = getAllParameters($this->wiki);
        $GLOBALS['params'] = $this->arguments;

        $view = $GLOBALS['params'][self::VARIABLE_VOIR];
        $action = $GLOBALS['params'][self::VARIABLE_ACTION];

        // si c'est demandé, on affiche le menu
        if ($GLOBALS['params']['voirmenu'] != '0') {
            $menuitems = array_map('trim', explode(',', $GLOBALS['params']['voirmenu']));
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
                    case self::VOIR_FICHE:
                        if (isset($_REQUEST['id_fiche'])) {
                            $fiche = $ficheManager->getOne($_REQUEST['id_fiche'], false, !empty($_REQUEST['time']) ? $_REQUEST['time'] : '');
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
            case self::VOIR_S_ABONNER:
                switch ($action) {
                    case self::LISTE_RSS:
                        return baz_liste_rss();
                    case self::VOIR_FLUX_RSS:
                        return baz_afficher_flux_rss();
                    default:
                        return baz_liste_rss();
                }
            case self::VOIR_SAISIR:
                switch ($action) {
                    case self::ACTION_SUPPRESSION:
                        $ficheManager->delete($_REQUEST['id_fiche']);
                        header('Location: '.$this->wiki->Href('', $_REQUEST['id_fiche'], 'message=delete_ok&'.self::VARIABLE_VOIR.'='.self::VOIR_CONSULTER));
                        break;
                    case self::ACTION_PUBLIER:
                        return publier_fiche(1).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                    case self::ACTION_PAS_PUBLIER:
                        return publier_fiche(0).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                    case self::ACTION_NOUVEAU:
                        // Affichage du formulaire du saisie d'une' fiche
                        return baz_formulaire(self::ACTION_NOUVEAU);
                    case self::ACTION_MODIFIER:
                        // Affichage du formulaire de modification d'une fiche
                        return baz_formulaire(self::ACTION_MODIFIER);
                    case self::ACTION_NOUVEAU_V:
                        // Affichage du formulaire du saisie d'une' fiche
                        return baz_formulaire(self::ACTION_NOUVEAU_V);
                    case self::ACTION_MODIFIER_V:
                        // Affichage du formulaire de modification d'une fiche
                        return baz_formulaire(self::ACTION_MODIFIER_V);
                    default:
                        // Choix du type de fiche à saisir
                        return baz_formulaire(self::CHOISIR_TYPE_FICHE);
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
                        return $formController->displayAll($_GET['msg']);
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

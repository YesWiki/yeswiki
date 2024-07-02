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
    public const ACTION_FORM_CONFIRM_DELETE = 'confirm_delete';
    public const ACTION_FORM_EMPTY = 'empty';
    public const ACTION_FORM_CONFIRM_EMPTY = 'confirm_empty';
    public const ACTION_FORM_CLONE = 'clone';
    public const CHOISIR_TYPE_FICHE = 'choisir_type_fiche';

    // Lists
    public const ACTION_LIST_CREATE = 'saisir_liste';
    public const ACTION_LIST_EDIT = 'modif_liste';
    public const ACTION_LIST_DELETE = 'supprimer_liste';

    public const ACTION_PUBLIER = 'publier'; // Valider la fiche
    public const ACTION_PAS_PUBLIER = 'pas_publier'; // Invalider la fiche

    public function formatArguments($arg)
    {
        $redirecturl = $this->sanitizedGet('redirecturl', function () use ($arg) {
            return $arg['redirecturl'] ?? '';
        });
        // YesWiki pages links, like "HomePage" or "HomePage/xml"
        if (!empty($redirecturl)) {
            $wikiLink = $this->wiki->extractLinkParts((substr($redirecturl, 0, 1) == '?') ? substr($redirecturl, 1) : $redirecturl);
            if ($wikiLink) {// General URL
                $tag = $wikiLink['tag'];
                $method = $wikiLink['method'];
                $params = $wikiLink['params'] ?? [];
                $redirecturl = $this->wiki->Href($method, $tag, $params, false);
            }
        }

        return [
            self::VARIABLE_ACTION => $this->sanitizedGet(self::VARIABLE_ACTION, function () use ($arg) {
                return $arg[self::VARIABLE_ACTION] ?? null;
            }),
            self::VARIABLE_VOIR => $this->sanitizedGet(self::VARIABLE_VOIR, function () use ($arg) {
                return $arg[self::VARIABLE_VOIR] ?? self::VOIR_DEFAUT;
            }),
            // afficher le menu de vues bazar ?
            'voirmenu' => $this->sanitizedGet('voirmenu', function () use ($arg) {
                return $arg['voirmenu'] ?? $this->params->get('baz_menu');
            }),
            // Identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $this->formatArray($_REQUEST['id_typeannonce'] ?? $arg['id'] ?? $arg['idtypeannonce'] ?? (!empty($_GET['id']) ? strip_tags($_GET['id']) : null)),
            // Permet de rediriger vers une url après saisie de fiche
            'redirecturl' => $redirecturl,
        ];
    }

    /**
     * check if get is scalar then return it or result of callback.
     *
     * @param function $callback
     *
     * @return scalar
     */
    protected function sanitizedGet(string $key, $callback)
    {
        return (isset($_GET[$key]) && is_scalar($_GET[$key]))
            ? $_GET[$key]
            : (is_callable($callback) ? $callback() : null);
    }

    public function run()
    {
        $listController = $this->getService(ListController::class);
        $formController = $this->getService(FormController::class);
        $entryController = $this->getService(EntryController::class);

        // TODO put in all bazar templates
        $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');

        $view = $this->arguments[self::VARIABLE_VOIR];
        $action = $this->arguments[self::VARIABLE_ACTION];

        // Display menu, unless we explicitly don't want to see it
        if ($this->arguments['voirmenu'] !== '0') {
            echo $this->render('@bazar/menu.twig', [
                'menuItems' => array_map('trim', explode(',', $this->arguments['voirmenu'])),
                'view' => $view,
            ]);
        }

        switch ($view) {
            case self::VOIR_SAISIR:
                if ($this->isWikiHibernated()) {
                    return $this->getMessageWhenHibernated();
                }
                switch ($action) {
                    case self::ACTION_ENTRY_CREATE:
                        return $entryController->create($_REQUEST['id_typeannonce'] ?? $_REQUEST['id'] ?? $this->arguments['idtypeannonce'][0], $this->arguments['redirecturl']);
                    case self::ACTION_ENTRY_EDIT:
                        return $entryController->update($_REQUEST['id_fiche']);
                    case self::ACTION_ENTRY_DELETE:
                        return $entryController->delete($_REQUEST['id_fiche'], true);
                    case self::ACTION_PUBLIER:
                        return $entryController->publish($_REQUEST['id_fiche'], true);
                    case self::ACTION_PAS_PUBLIER:
                        return $entryController->publish($_REQUEST['id_fiche'], false);
                    case self::CHOISIR_TYPE_FICHE:
                        return $entryController->selectForm();
                    default:
                        if (!empty($this->arguments['idtypeannonce'])) {
                            if (count($this->arguments['idtypeannonce']) > 1) {
                                return $entryController->selectForm($this->arguments['idtypeannonce']);
                            } else {
                                return $entryController->create($this->arguments['idtypeannonce'][0], $this->arguments['redirecturl']);
                            }
                        } else {
                            return $entryController->selectForm();
                        }
                }
                // no break
            case self::VOIR_FORMULAIRE:
                switch ($action) {
                    case self::ACTION_FORM_CREATE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->create();
                    case self::ACTION_FORM_EDIT:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->update($_GET['idformulaire']);
                    case self::ACTION_FORM_DELETE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->delete($_GET['idformulaire']);
                    case self::ACTION_FORM_CONFIRM_DELETE:
                    case self::ACTION_FORM_CONFIRM_EMPTY:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $this->render('@bazar/forms/forms_confirm.twig', [
                            'type' => ($action == self::ACTION_FORM_CONFIRM_DELETE) ? 'delete' : 'empty',
                        ]);
                    case self::ACTION_FORM_EMPTY:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->empty($_GET['idformulaire']);
                    case self::ACTION_FORM_CLONE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->clone($_GET['idformulaire']);
                    default:
                        return $formController->displayAll(!empty($_GET['msg']) ? $_GET['msg'] : null);
                }
                // no break
            case self::VOIR_LISTES:
                switch ($action) {
                    case self::ACTION_LIST_CREATE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $listController->create();
                    case self::ACTION_LIST_EDIT:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $listController->update($_GET['idliste']);
                    case self::ACTION_LIST_DELETE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $listController->delete($_GET['idliste']);
                    default:
                        return $listController->displayAll();
                }
                // no break
            case self::VOIR_IMPORTER:
                return $this->callAction('bazarimport', $this->arguments);
            case self::VOIR_EXPORTER:
                return $this->callAction('bazarexport', $this->arguments);
            case self::VOIR_CONSULTER:
            case self::VOIR_DEFAUT:
            default:
                switch ($action) {
                    case self::ACTION_ENTRY_VIEW:
                        return $entryController->view($_REQUEST['id_fiche'], $_REQUEST['time'] ?? '');
                    case self::MOTEUR_RECHERCHE:
                    default:
                        $this->arguments['search'] = true;

                        return $this->callAction('bazarliste', $this->arguments);
                }
        }
    }
}

<?php

namespace YesWiki\Content\Action;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Controller\FormController;
use YesWiki\Content\Controller\ListController;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\UrlFormatter;

class BazarAction extends YesWikiAction implements RegisteredAction
{
    /** `{{bazar}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'bazar';
    }

    public const URL_VIEW_PARAM = 'vue';
    public const URL_ACTION_PARAM = 'action';

    // Premier niveau d'action : pour toutes les fiches
    public const VIEW_DEFAULT = 'formulaire'; // Recherche
    public const VIEW_SEARCH = 'consulter'; // Recherche
    public const VIEW_CREATE = 'saisir';
    public const VIEW_FORMS = 'formulaire';
    public const VIEW_LISTS = 'listes';
    public const VIEW_IMPORT = 'importer';
    public const VIEW_EXPORT = 'exporter';
    public const VIEW_SUBSCRIPTIONS = 'abonnements';

    // Entries
    public const ACTION_SEARCH = 'recherche';
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
    public const ACTION_CHOOSE_FORM = 'choisir_type_fiche';

    // Lists
    public const ACTION_LIST_CREATE = 'saisir_liste';
    public const ACTION_LIST_EDIT = 'modif_liste';
    public const ACTION_LIST_DELETE = 'supprimer_liste';

    // Abonnements
    public const ACTION_SUBSCRIPTION_LIST = 'list';
    public const ACTION_SUBSCRIPTION_ADD = 'add';
    public const ACTION_SUBSCRIPTION_REMOVE = 'remove';
    public const ACTION_ABONNEMENT_SYNC = 'sync';

    public const ACTION_PUBLIER = 'publier'; // Valider la fiche
    public const ACTION_PAS_PUBLIER = 'pas_publier'; // Invalider la fiche

    public function formatArguments($arg)
    {
        $redirecturl = $this->sanitizedGet('redirecturl', function () use ($arg) {
            return $arg['redirecturl'] ?? '';
        });

        // YesWiki pages links, like "HomePage" or "HomePage/xml"
        if (!empty($redirecturl)) {
            $wikiLink = $this->getService(UrlFormatter::class)->extractLinkParts((substr($redirecturl, 0, 1) == '?') ? substr($redirecturl, 1) : $redirecturl);
            if ($wikiLink) {// General URL
                $tag = $wikiLink['tag'];
                $method = $wikiLink['method'];
                $params = $wikiLink['params'];
                $redirecturl = $this->getService(UrlFormatter::class)->href($method, $tag, $params, false);
            }
        }

        $req = $this->getRequest();
        $reqIdTypeAnnonce = $req->get('form_id');
        $reqId = $req->get('id');
        $vIDs = (isset($reqIdTypeAnnonce) && trim($reqIdTypeAnnonce) != ''
                ? $reqIdTypeAnnonce
                : (isset($reqId) && trim($reqId) != ''
                    ? $reqId
                    : (isset($arg['form_id']) && trim($arg['form_id']) != ''
                        ? $arg['form_id']
                        : (isset($arg['id']) && trim($arg['id']) != ''
                        ? $arg['id']
                        : ''))));

        $vBazarListService = $this->getService(BazarListService::class);

        $vIDs = $vBazarListService->getIDs($vIDs);

        return [
            self::URL_ACTION_PARAM => $this->sanitizedGet(self::URL_ACTION_PARAM, function () use ($arg) {
                return $arg[self::URL_ACTION_PARAM] ?? null;
            }),
            self::URL_VIEW_PARAM => $this->sanitizedGet(self::URL_VIEW_PARAM, function () use ($arg) {
                return $arg[self::URL_VIEW_PARAM] ?? self::VIEW_DEFAULT;
            }),
            // afficher le menu de vues bazar ?
            'voirmenu' => $this->sanitizedGet('voirmenu', function () use ($arg) {
                return $arg['voirmenu'] ?? $this->params->get('baz_menu');
            }),
            // Identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $vIDs,
            // Permet de rediriger vers une url après saisie de fiche
            'redirecturl' => $redirecturl,
        ];
    }

    /**
     * check if get is scalar then return it or result of callback.
     *
     * @param callable $callback
     *
     * @return scalar
     */
    protected function sanitizedGet(string $key, $callback)
    {
        $val = $this->getRequest()->query->get($key);

        return (isset($val) && is_scalar($val))
            ? $val
            : (is_callable($callback) ? $callback() : null);
    }

    public function run()
    {
        $req = $this->getRequest();
        $listController = $this->getService(ListController::class);
        $formController = $this->getService(FormController::class);
        $entryController = $this->getService(EntryController::class);

        // TODO put in all bazar templates
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);

        $view = $this->arguments[self::URL_VIEW_PARAM];
        $action = $this->arguments[self::URL_ACTION_PARAM];

        // Prepended to whatever the view returns, rather than printed: an action that
        // prints lands wherever the output buffer happens to be rather than where it was
        // called from, which is how bazar's own menu came out above the title of the page
        // that called it.
        $menu = $this->arguments['voirmenu'] === '0' ? '' : $this->render('@core/menu.twig', [
            'menuItems' => array_map('trim', explode(',', $this->arguments['voirmenu'])),
            'view' => $view,
        ]);

        return $menu . $this->runView($view, $action, $req, $listController, $formController, $entryController);
    }

    /**
     * The body of the action: what the requested view returns, without the menu.
     *
     * @return string
     */
    private function runView(mixed $view, mixed $action, Request $req, ListController $listController, FormController $formController, EntryController $entryController): string
    {
        switch ($view) {
            case self::VIEW_CREATE:
                if ($this->isWikiHibernated()) {
                    return $this->getMessageWhenHibernated();
                }
                switch ($action) {
                    case self::ACTION_ENTRY_CREATE:
                        return $entryController->create($req->get('form_id') ?? $req->get('id') ?? $this->arguments['idtypeannonce']['locals'][0], $this->arguments['redirecturl']);
                    case self::ACTION_ENTRY_EDIT:
                        return $entryController->update($req->get('tag'));
                    case self::ACTION_ENTRY_DELETE:
                        // delete() answers a bool; the view has nothing of its own to show
                        return (string)$entryController->delete($req->get('tag'), true);
                    case self::ACTION_PUBLIER:
                        return $entryController->publish($req->get('tag'), true);
                    case self::ACTION_PAS_PUBLIER:
                        return $entryController->publish($req->get('tag'), false);
                    case self::ACTION_CHOOSE_FORM:
                        return $entryController->selectForm();
                    default:
                        if (!empty($this->arguments['idtypeannonce']['locals'])) {
                            if (count($this->arguments['idtypeannonce']['locals']) > 1) {
                                return $entryController->selectForm($this->arguments['idtypeannonce']['locals']);
                            }

                            return $entryController->create($this->arguments['idtypeannonce']['locals'][0], $this->arguments['redirecturl']);
                        }

                        return $entryController->selectForm();
                }
                // no break
            case self::VIEW_FORMS:
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

                        return $formController->update($req->query->get('idformulaire'));
                    case self::ACTION_FORM_DELETE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->delete($req->query->get('idformulaire'));
                    case self::ACTION_FORM_CONFIRM_DELETE:
                    case self::ACTION_FORM_CONFIRM_EMPTY:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $this->render('@core/forms/forms_confirm.twig', [
                            'type' => ($action == self::ACTION_FORM_CONFIRM_DELETE) ? 'delete' : 'empty',
                        ]);
                    case self::ACTION_FORM_EMPTY:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->empty($req->query->get('idformulaire'));
                    case self::ACTION_FORM_CLONE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $formController->clone($req->query->get('idformulaire'));
                    default:
                        return $formController->displayAll($req->query->get('msg'));
                }
                // no break
            case self::VIEW_SUBSCRIPTIONS:
                switch ($action) {
                    case self::ACTION_SUBSCRIPTION_LIST:
                        return $formController->manageAbonnements($req->query->get('idformulaire'));
                    case self::ACTION_SUBSCRIPTION_ADD:
                        if ($req->query->get('type') === 'following') {
                            return $formController->addFollowing($req->query->get('idformulaire'), $req->query->get('actor'));
                        }
                        // no break
                    case self::ACTION_SUBSCRIPTION_REMOVE:
                        if ($req->query->get('type') === 'followers') {
                            return $formController->removeFollower($req->query->get('idformulaire'), $req->query->get('actor'));
                        }

                        return $formController->removeFollowing($req->query->get('idformulaire'), $req->query->get('actor'));

                    case self::ACTION_ABONNEMENT_SYNC:
                        return $formController->syncActorPosts($req->query->get('idformulaire'), $req->query->get('actor'));
                    default:
                        return $formController->displayAll($req->query->get('msg'));
                }
                // no break
            case self::VIEW_LISTS:
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

                        return $listController->update($req->query->get('idliste'));
                    case self::ACTION_LIST_DELETE:
                        if ($this->isWikiHibernated()) {
                            return $this->getMessageWhenHibernated();
                        }

                        return $listController->delete($req->query->get('idliste'));
                    default:
                        return $listController->displayAll();
                }
                // no break
            case self::VIEW_IMPORT:
                return $this->callAction('bazarimport', $this->arguments);
            case self::VIEW_EXPORT:
                return $this->callAction('bazarexport', $this->arguments);
            case self::VIEW_SEARCH:
            case self::VIEW_DEFAULT:
            default:
                switch ($action) {
                    case self::ACTION_ENTRY_VIEW:
                        return $entryController->view($req->get('tag'), $req->get('time', ''));
                    case self::ACTION_SEARCH:
                    default:
                        $this->arguments['search'] = true;

                        return $this->callAction('bazarliste', array_merge($this->arguments, ['idtypeannonce' => $this->arguments['idtypeannonce']['locals']]));
                }
        }
    }
}

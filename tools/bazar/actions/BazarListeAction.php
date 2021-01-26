<?php

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;

class BazarListeAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        // ICONS
        $iconField = $_GET['iconfield'] ?? $arg['iconfield'] ?? null;
        $icon = $_GET['icon'] ?? $arg['icon'] ?? null;
        if (!empty($icon)) {
            $tabparam = $this->getMultipleParameters($icon, ',', '=');
            if ($tabparam['fail'] != 1) {
                if (count($tabparam) > 1 && !empty($iconField)) {
                    // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                    foreach ($tabparam as $key=>$data) {
                        $tabparam[$data] = $key;
                    }
                    $icon = $tabparam;
                } else {
                    $icon = trim($tabparam[0]);
                }
            } else {
                exit('<div class="alert alert-danger">action bazarliste : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"</div>');
            }
        } else {
            $icon = $this->params->get('baz_marker_icon');
        }

        // COLORS
        $colorField = $_GET['colorfield'] ?? $arg['colorfield'] ?? null;
        $color = $_GET['color'] ?? $arg['color'] ?? null;
        if (!empty($color)) {
            $tabparam = $this->getMultipleParameters($color, ',', '=');
            if ($tabparam['fail'] != 1) {
                if (count($tabparam) > 1 && !empty($colorField)) {
                    // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                    foreach ($tabparam as $key=>$data) {
                        $tabparam[$data] = $key;
                    }
                    $color = $tabparam;
                } else {
                    $color = trim($tabparam[0]);
                    if (!in_array($color, BazarCartoAction::$availableColors)) {
                        $color = $GLOBALS['wiki']->config['baz_marker_color'];
                    }
                }
            } else {
                exit('<div class="alert alert-danger">action bazarliste : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"</div>');
            }
        } else {
            $color = $this->params->get('baz_marker_color');
        }

        return([
            // SELECTION DES FICHES
            // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $this->formatArray($arg['id'] ?? $arg['idtypeannonce'] ?? $_GET['id'] ?? null),
            // Paramètres pour une requete specifique
            'query' => $this->formatQuery($arg),
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => $this->formatDateMin($_GET['period'] ?? $arg['period'] ?? null),
            // sélectionner seulement les fiches d'un utilisateur
            'user' => $arg['user'] ?? (isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == "true") ? 
                $this->getService(UserManager::class)->getLoggedUserName() : null,
            // Ordre du tri (asc ou desc)
            'ordre' => $arg['ordre'] ?? 'asc',
            // Champ du formulaire utilisé pour le tri
            'champ' => $arg['champ'] ?? 'bf_titre',
            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'] ?? null,
            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'] ?? null,
            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false,'random'),
            // Transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'correspondance' => $arg['correspondance'] ?? null,

            // AFFICHAGE
            // Template pour l'affichage de la liste de fiches
            'template' => $_GET['template'] ?? $arg['template'] ?? $this->params->get('default_bazar_template'),
            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'] ?? '',
            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )
            'barregestion' => $this->formatBoolean($arg, true,'barregestion') ,
            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg , false,'showexportbuttons'),
            // Affiche le formulaire de recherche en haut
            'search' => $this->formatBoolean($arg , false,'search'),
            // Affiche le nombre de fiche en haut
            'shownumentries' => $this->formatBoolean($arg, true , 'shownumentries'),

            // FACETTES
            // Identifiants des champs utilisés pour les facettes
            // Plusieures valeurs possibles, séparées par des virgules, "all" pour toutes les facettes possibles
            // Exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
            'groups' => $this->formatArray($_GET['groups'] ?? $arg['groups'] ?? null),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{bazarliste titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($_GET['titles'] ?? $arg['titles'] ?? null),
            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),
            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg, false,'filtertext'),
            // facette à gauche ou à droite
            'filterposition' => $GET['filterposition'] ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $GET['filterposition'] ?? $arg['filterposition'] ?? '3',
            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($_GET['groupsexpanded'] ?? $arg , true, 'groupsexpanded'),
            // Prefixe des classes CSS utilisees pour la carto
            'iconprefix' => isset($_GET['iconprefix']) ? trim($_GET['iconprefix']) : isset($arg['iconprefix']) ? trim($arg['iconprefix']) : $this->params->get('baz_marker_icon_prefix') ?? '',
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,
            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color,
        ]);
    }

    function run()
    {
        // If the template is a map or a calendar, call the dedicated action so that
        // arguments can be properly formatted. The second first condition prevents infinite loops
        if( ($this->arguments['template'] === 'map' || $this->arguments['template'] === 'map.tpl.html') && isset($this->arguments['calledBy']) && $this->arguments['calledBy'] !== 'BazarCartoAction' ) {
            return $this->callAction('bazarcarto', $this->arguments);
        } elseif( ($this->arguments['template'] === 'calendar' || $this->arguments['template'] === 'calendar.tpl.html') && $this->arguments['calledBy'] !== 'CalendrierAction' ) {
            return $this->callAction('calendrier', $this->arguments);
        }

        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);
        $externalWikiService = $this->getService(ExternalBazarService::class);

        if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
            $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
        }
        ++$GLOBALS['_BAZAR_']['nbbazarliste'];

        // TODO put in all bazar templates
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        // Are the entries on an external wiki ?
        if( !empty($this->arguments['url']) ) {
            $forms = $externalWikiService->getForms($this->arguments['url']);
            $entries = $externalWikiService->getEntries([
                'url' => $this->arguments['url'],
                'queries' => $this->arguments['query'],
                'formsIds' => $this->arguments['idtypeannonce'],
            ]);
        } else {
            $forms = $formManager->getAll();
            $entries = $entryManager->search([
                'queries' => $this->arguments['query'],
                'formsIds' => $this->arguments['idtypeannonce'],
                'keywords' => $_REQUEST['q'] ?? '',
                'user' => $this->arguments['user'],
                'dateMin' => $this->arguments['dateMin']
            ]);

            // Add display data to all entries
            $entries = array_map(function ($fiche) use ($entryManager) {
                $entryManager->appendDisplayData($fiche, false, $this->arguments['correspondance']);
                return $fiche;
            }, $entries);
        }

        // Sort entries
        if ($this->arguments['random']) {
            shuffle($entries);
        } else {
            usort($entries, $this->buildFieldSorter($this->arguments['ordre'], $this->arguments['champ']));
        }

        // Limit entries
        if ($this->arguments['nb'] !== '') {
            $entries = array_slice($entries, 0, $this->arguments['nb']);
        }

        $filters = $this->formatFilters($entries, $forms);

        $this->arguments['nbbazarliste'] = $GLOBALS['_BAZAR_']['nbbazarliste'] ;

        return $this->render('@bazar/entries/list.twig', [
            'listId' => $GLOBALS['_BAZAR_']['nbbazarliste'],
            'filters' => $filters,
            'renderedEntries' => $this->renderEntries($entries),
            'numEntries' => count($entries),
            'params' => $this->arguments,
            // Search form parameters
            'keywords' => $_GET['q'] ?? '',
            'pageTag' => $this->wiki->getPageTag(),
            'forms' => count($this->arguments['idtypeannonce']) === 0 ? $forms : '',
            'formId' => $this->arguments['idtypeannonce'][0],
            'facette' => $_GET['facette'] ?? null,
        ]);
    }

    private function renderEntries($entries) : string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];

        $data['fiches'] = $entries;
        $data['info_res'] = $showNumEntries ? '<div class="alert alert-info">'._t('BAZ_IL_Y_A').' '.count($data['fiches']).' '.(count($data['fiches']) <= 1 ? _t('BAZ_FICHE') : _t('BAZ_FICHES')).'</div>' : '';
        $data['param'] = $this->arguments;
        $data['pager_links'] = '';

        if (!empty($this->arguments['pagination'])) {
            require_once 'tools/bazar/libs/vendor/Pager/Pager.php';
            $tab = $_GET;
            unset($tab['wiki']);
            $pager = &Pager::factory([
                'mode' => $this->params->get('BAZ_MODE_DIVISION'),
                'perPage' => $this->arguments['pagination'],
                'delta' => $this->params->get('BAZ_DELTA'),
                'httpMethod' => 'GET',
                'path' => $this->wiki->getBaseUrl(),
                'extraVars' => $tab,
                'altNext' => _t('BAZ_SUIVANT'),
                'altPrev' => _t('BAZ_PRECEDENT'),
                'nextImg' => _t('BAZ_SUIVANT'),
                'prevImg' => _t('BAZ_PRECEDENT'),
                'itemData' => $data['fiches'],
                'curPageSpanPre' => '<li class="active"><a>',
                'curPageSpanPost' => '</a></li>',
                'useSessions' => false,
                'closeSession' => false,
            ]);
            $data['fiches'] = $pager->getPageData();
            $data['pager_links'] = '<div class="bazar_numero text-center"><ul class="pagination">'.$pager->links.'</ul></div>';
        }

        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $param['template'] = $templateName . '.tpl.html';
        }

        return $this->render("@bazar/{$templateName}", $data);
    }

    private function formatFilters($entries, $forms) : array
    {
        $formManager = $this->getService(FormManager::class);

        if (count($this->arguments['groups']) > 0) {
            // Scanne tous les champs qui pourraient faire des filtres pour les facettes
            $facettables = $formManager->scanAllFacettable($entries, $this->arguments['groups']);

            if (count($facettables) > 0) {
                $i = 0;
                $first = true;
                $filters = [];

                // Récupere les facettes cochees
                $tabfacette = [];
                if (isset($_GET['facette']) && !empty($_GET['facette'])) {
                    $tab = explode('|', $_GET['facette']);
                    //découpe la requete autour des |
                    foreach ($tab as $req) {
                        $tabdecoup = explode('=', $req, 2);
                        if (count($tabdecoup)>1) {
                            $tabfacette[$tabdecoup[0]] = explode(',', trim($tabdecoup[1]));
                        }
                    }
                }

                foreach ($facettables as $id => $facettable) {
                    // Formatte la liste des resultats en fonction de la source
                    if ($facettable['type'] == 'liste') {
                        $field = $this->findFieldByName($forms, $facettable['source']);
                        $list['titre_liste'] = $field->getName();
                        $list['label'] = $field->getOptions();
                    } elseif ($facettable['type'] == 'fiche') {
                        $src = str_replace(array('listefiche', 'checkboxfiche'), '', $facettable['source']);
                        $form = $forms[$src];
                        $list['titre_liste'] = $form['bn_label_nature'];
                        foreach ($facettable as $idfiche => $nb) {
                            if ($idfiche != 'source' && $idfiche != 'type') {
                                $f = $GLOBALS['wiki']->services->get(EntryManager::class)->getOne($idfiche);
                                $list['label'][$idfiche] = $f['bf_titre'];
                            }
                        }
                    } elseif ($facettable['type'] == 'form') {
                        if ($facettable['source'] == 'id_typeannonce') {
                            $list['titre_liste'] = _t('BAZ_TYPE_FICHE');
                            foreach ($facettable as $idf => $nb) {
                                if ($idf != 'source' && $idf != 'type') {
                                    $list['label'][$idf] = $forms[$idf]['bn_label_nature'];
                                }
                            }
                        } elseif ($facettable['source'] == 'owner') {
                            $list['titre_liste'] = _t('BAZ_CREATOR');
                            foreach ($facettable as $idf => $nb) {
                                if ($idf != 'source' && $idf != 'type') {
                                    $list['label'][$idf] = $idf;
                                }
                            }
                        } else {
                            $list['titre_liste'] = $id;
                            foreach ($facettable as $idf => $nb) {
                                if ($idf != 'source' && $idf != 'type') {
                                    $list['label'][$idf] = $idf;
                                }
                            }
                        }
                    }

                    $idkey = htmlspecialchars($id);

                    $filters[$idkey]['icon'] =
                        (isset($this->arguments['groupicons'][$i]) && !empty($this->arguments['groupicons'][$i])) ?
                            '<i class="'.$this->arguments['groupicons'][$i].'"></i> ' : '';

                    $filters[$idkey]['title'] =
                        (isset($this->arguments['titles'][$i]) && !empty($this->arguments['titles'][$i])) ?
                            $this->arguments['titles'][$i] : $list['titre_liste'];

                    $filters[$idkey]['collapsed'] = !$first && !$this->arguments['groupsexpanded'];

                    foreach ($list['label'] as $listkey => $label) {
                        if (isset($facettables[$id][$listkey]) && !empty($facettables[$id][$listkey])) {
                            $filters[$idkey]['list'][] = [
                                'id' => $idkey.$listkey,
                                'name' => $idkey,
                                'value' => htmlspecialchars($listkey),
                                'label' => $label,
                                'nb' => $facettables[$id][$listkey],
                                'checked' => (isset($tabfacette[$idkey]) and in_array($listkey, $tabfacette[$idkey])) ? ' checked' : '',
                            ];
                        }
                    }
                    ++$i;
                    $first = false;
                }
                
                return $filters;
            }
        }

        return [];
    }

    private function formatQuery($arg) : array
    {
        $queryArray = [];

        // Aggregate argument and $_GET values
        if (isset($_GET['query'])) {
            if (!empty($arg['query'])) {
                $query = $arg['query'].'|'.$_GET['query'];
            } else {
                $query = $_GET['query'];
            }
        } else {
            $query = $arg['query'] ?? null;
        }

        // Create an array from the queries
        if (!empty($query)) {
            $res1 = explode('|', $query);
            foreach ($res1 as $req) {
                $res2 = explode('=', $req, 2);
                if (isset($queryArray[$res2[0]]) && !empty($queryArray[$res2[0]])) {
                    $queryArray[$res2[0]] = $queryArray[$res2[0]].','.trim($res2[1]);
                } else {
                    $queryArray[$res2[0]] = trim($res2[1]);
                }
            }
        }

        return $queryArray;
    }

    private function formatDateMin($period)
    {
        switch ($period) {
            case 'day':
                $d = strtotime("-1 day");
                return date("Y-m-d H:i:s", $d);
            case 'week':
                $d = strtotime("-1 week");
                return date("Y-m-d H:i:s", $d);
            case 'month':
                $d = strtotime("-1 month");
                return date("Y-m-d H:i:s", $d);
        }
    }

    /*
     * Scan all forms and return the first field matching the given ID
     */
    private function findFieldByName($forms, $name)
    {
        foreach( $forms as $form ) {
            foreach ($form['prepared'] as $field) {
                if ($field instanceof BazarField) {
                    if ($field->getPropertyName() === $name) {
                        return $field;
                    }
                } elseif (is_array($field)) {
                    if (isset($field['id']) && $field['id'] === $name) {
                        return $field;
                    }
                }
            }
        }
    }

    private function buildFieldSorter($ordre, $champ) : callable
    {
        return function ($a, $b) use ($ordre, $champ) {
            if ($ordre == 'desc') {
                return strcoll($b[$champ], $a[$champ]);
            } else {
                return strcoll($a[$champ], $b[$champ]);
            }
        };
    }
}

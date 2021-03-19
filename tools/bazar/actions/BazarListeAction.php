<?php

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\Service\TemplateNotFound;

class BazarListeAction extends YesWikiAction
{
    protected const BAZARCARTO_TEMPLATES = ["map","gogomap"] ; // liste des templates sans .twig ni .tpl.html
    protected const CALENDRIER_TEMPLATES = ["calendar"] ; // liste des templates sans .twig ni .tpl.html

    public function formatArguments($arg)
    {
        // ICONS FIELD
        $iconField = $_GET['iconfield'] ?? $arg['iconfield'] ?? null ;

        // ICONS
        $icon = $_GET['icon'] ?? $arg['icon'] ??  null;
        $iconAlreadyDefined = ($icon == $this->params->get('baz_marker_icon') || is_array($icon)) ;
        if (!$iconAlreadyDefined) {
            if (!empty($icon)) {
                $tabparam = getMultipleParameters($icon, ',', '=');
                if ($tabparam['fail'] != 1) {
                    if (count($tabparam) > 1 && !empty($iconField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                        foreach ($tabparam as $key=>$data) {
                            $tabparam[$data] = $key;
                        }
                        $icon = $tabparam;
                    } else {
                        $icon = trim(array_values($tabparam)[0]);
                    }
                } else {
                    exit('<div class="alert alert-danger">action bazarliste : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"</div>');
                }
            } else {
                $icon = $this->params->get('baz_marker_icon');
            }
        }
        
        // COLORS FIELD
        $colorField = $_GET['colorfield'] ?? $arg['colorfield'] ?? null ;
        
        // COLORS
        $color = $_GET['color'] ?? $arg['color'] ?? null ;
        $colorAlreadyDefined = ($color == $this->params->get('baz_marker_color') || is_array($color)) ;
        if (!$colorAlreadyDefined) {
            if (!empty($color)) {
                $tabparam = getMultipleParameters($color, ',', '=');
                if ($tabparam['fail'] != 1) {
                    if (count($tabparam) > 1 && !empty($colorField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                        foreach ($tabparam as $key=>$data) {
                            $tabparam[$data] = $key;
                        }
                        $color = $tabparam;
                    } else {
                        $color = trim(array_values($tabparam)[0]);
                    }
                } else {
                    exit('<div class="alert alert-danger">action bazarliste : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"</div>');
                }
            } else {
                $color = $this->params->get('baz_marker_color');
            }
        }
        
        
        $template = $_GET['template'] ?? $arg['template'] ?? null ;
        $template = (!empty($template)) ? $template : $this->params->get('default_bazar_template');
        $prerenderer = $arg['prerenderer'] ?? null ;
        
        if (empty($prerenderer)) {
            if (preg_match_all("/^(.*)(.tpl.html)$/i", $template, $matches)) {
                $prerenderer = null ; // not prerenderer if .tpl.html
            } else {
                if (preg_match_all("/^(.*)(.twig)$/i", $template, $matches)) {
                    $prerenderer = $matches[1][0] ;
                } else {
                    $prerenderer = $template ;
                }
                $prerenderer .= 'PreRenderer' ;
            }
        }
        if (!empty($prerenderer)) {
            $prerenderer =
                (
                    ($serviceName = 'YesWiki\\Custom\\Controller\\' . $prerenderer) &&
                    $this->wiki->services->has($serviceName)
                ) ? $serviceName :
                (
                    (
                        ($serviceName = 'YesWiki\\Bazar\\Controller\\' . $prerenderer) &&
                        $this->wiki->services->has($serviceName)
                    ) ? $serviceName :
                    (
                        ($serviceName = $prerenderer) &&
                        $this->wiki->services->has($serviceName)
                    ) ? $serviceName : null
                );
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
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == "true") ?
                $this->getService(UserManager::class)->getLoggedUserName() : null),
            // Ordre du tri (asc ou desc)
            'ordre' => $arg['ordre'] ?? ((empty($arg['champ']) && (!empty($arg['agenda']) || !empty($arg['datefilter']))) ? 'desc' : 'asc') ,
            // Champ du formulaire utilisé pour le tri
            'champ' => $arg['champ'] ?? ((!empty($arg['agenda']) || !empty($arg['datefilter'])) ? 'bf_date_debut_evenement' : 'bf_titre') ,
            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'] ?? null,
            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'] ?? null,
            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false, 'random'),
            // Transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'correspondance' => $arg['correspondance'] ?? null,
            // paramètre de tri des fiches sur une date (en gardant la retrocompatibilité avec le paramètre agenda)
            'agenda' => $arg['datefilter'] ?? $arg['agenda'] ?? null,
            'datefilter' => $arg['datefilter'] ?? $arg['agenda'] ?? null,

            // AFFICHAGE
            // Template pour l'affichage de la liste de fiches
            'template' => $template,
            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'] ?? '',
            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )
            'barregestion' => $this->formatBoolean($arg, true, 'barregestion') ,
            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg, false, 'showexportbuttons'),
            // Affiche le formulaire de recherche en haut
            'search' => $this->formatBoolean($arg, false, 'search'),
            // Affiche le nombre de fiche en haut
            'shownumentries' => $this->formatBoolean($arg, false, 'shownumentries'),

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
            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),
            // facette à gauche ou à droite
            'filterposition' => $_GET['filterposition'] ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $_GET['filtercolsize'] ?? $arg['filtercolsize'] ?? '3',
            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($_GET['groupsexpanded'] ?? $arg, true, 'groupsexpanded'),
            // Prefixe des classes CSS utilisees pour la carto et calendrier
            'iconprefix' => isset($_GET['iconprefix']) ? trim($_GET['iconprefix']) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,
            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color ,

            // prerenderer controller
            'prerenderer' => $prerenderer,
        ]);
    }

    public function run()
    {
        // If the template is a map or a calendar, call the dedicated action so that
        // arguments can be properly formatted. The second first condition prevents infinite loops
        if (self::specialActionFromTemplate($this->arguments['template'], "BAZARCARTO_TEMPLATES")
                && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'BazarCartoAction')) {
            return $this->callAction('bazarcarto', $this->arguments);
        } elseif (self::specialActionFromTemplate($this->arguments['template'], "CALENDRIER_TEMPLATES")
                && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'CalendrierAction')) {
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
        if (!empty($this->arguments['url'])) {
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

        // filter entries on datefilter parameter
        if (!empty($this->arguments['datefilter'])) {
            $entries = $this->filterEntriesOnDate($entries) ;
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
            'formId' => $this->arguments['idtypeannonce'][0] ?? null,
            'facette' => $_GET['facette'] ?? null,
        ]);
    }

    private function renderEntries($entries) : string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];

        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $templateName = $templateName . '.tpl.html';
            $this->arguments['template'] = $templateName;
        }

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

        if (!empty($this->arguments['prerenderer'])) {
            $data = $this->getService($this->arguments['prerenderer'])->preRender($data) ;
        }
        try {
            return $this->render("@bazar/{$templateName}", $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">'.$e->getMessage().'</div>';
        }
    }

    private function formatFilters($entries, $forms) : array
    {
        $formManager = $this->getService(FormManager::class);

        if (count($this->arguments['groups']) > 0) {
            // Scanne tous les champs qui pourraient faire des filtres pour les facettes
            $facettables = $formManager->scanAllFacettable($entries, $this->arguments['groups']);

            if (count($facettables) > 0) {
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
                    $list = [];
                    // Formatte la liste des resultats en fonction de la source
                    if ($facettable['type'] == 'liste') {
                        $field = $this->findFieldByName($forms, $facettable['source']);
                        $list['titre_liste'] = $field->getLabel();
                        $list['label'] = $field->getOptions();
                    } elseif ($facettable['type'] == 'fiche') {
                        $field = $this->findFieldByName($forms, $facettable['source']);
                        if ($field instanceof BazarField) {
                            $formId = $field->getName() ;
                            $form = $forms[$formId];
                            $list['titre_liste'] = $form['bn_label_nature'];
                            foreach ($facettable as $idfiche => $nb) {
                                if ($idfiche != 'source' && $idfiche != 'type') {
                                    $f = $this->getService(EntryManager::class)->getOne($idfiche);
                                    $list['label'][$idfiche] = $f['bf_titre'];
                                }
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

                    $i = array_key_first(array_filter($this->arguments['groups'], function ($value) use ($idkey) {
                        return ($value == $idkey) ;
                    }));

                    $filters[$idkey]['icon'] =
                        (isset($this->arguments['groupicons'][$i]) && !empty($this->arguments['groupicons'][$i])) ?
                            '<i class="'.$this->arguments['groupicons'][$i].'"></i> ' : '';

                    $filters[$idkey]['title'] =
                        (isset($this->arguments['titles'][$i]) && !empty($this->arguments['titles'][$i])) ?
                            $this->arguments['titles'][$i] : $list['titre_liste'];

                    $filters[$idkey]['collapsed'] = ($i != 0) && !$this->arguments['groupsexpanded'];

                    $filters[$idkey]['index'] = $i;

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
                }

                
                // reorder $filters

                uasort($filters, function ($a, $b) {
                    if (isset($a['index']) && isset($b['index'])) {
                        if ($a['index'] == $b['index']) {
                            return 0 ;
                        } else {
                            return ($a['index'] < $b['index']) ? -1 : 1 ;
                        }
                    } elseif (isset($a['index'])) {
                        return 1 ;
                    } elseif (isset($b['index'])) {
                        return -1 ;
                    } else {
                        return 0 ;
                    }
                }) ;

                foreach ($filters as $id => $filter) {
                    if (isset($filter['index'])) {
                        unset($filter['index']) ;
                    }
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
                if (is_array($arg['query'])) {
                    $queryArray = $arg['query'] ;
                    $query = $_GET['query'];
                } else {
                    $query = $arg['query'].'|'.$_GET['query'];
                }
            } else {
                $query = $_GET['query'];
            }
        } else {
            if (isset($arg['query']) && is_array($arg['query'])) {
                $queryArray = $arg['query'] ;
                $query = null;
            } else {
                $query = $arg['query'] ?? null;
            }
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
        foreach ($forms as $form) {
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

    /* Method to test if the current template is associated to a specific bazar actions
     * @param $templateName string (ex. "map","map.tpl.html","map.twig")
     * @param $constName string name of the constant array containing the right template names
     *                          "BAZARCARTO_TEMPLATES" or "CALENDRIER_TEMPLATES"
     */
    public static function specialActionFromTemplate(string $templateName, string $constName): bool
    {
        switch ($constName) {
            case "BAZARCARTO_TEMPLATES":
                $baseArray = self::BAZARCARTO_TEMPLATES ;
                break;
            case "CALENDRIER_TEMPLATES":
                $baseArray = self::CALENDRIER_TEMPLATES ;
                break;
            default:
                return false;
        }

        $templatesnames = [];
        foreach ($baseArray as $templateBaseName) {
            $templatesnames[] = $templateBaseName;
            $templatesnames[] = $templateBaseName . '.tpl.html';
            $templatesnames[] = $templateBaseName . '.twig';
        }

        return in_array($templateName, $templatesnames) ;
    }

    protected function filterEntriesOnDate($entries) : array
    {
        $TODAY_TEMPLATE = "/^(today|aujourdhui|=0(D)?)$/i" ;
        $FUTURE_TEMPLATE = "/^(futur|future|>0(D)?)$/i" ;
        $PAST_TEMPLATE = "/^(past|passe|<0(D)?)$/i" ;
        $DATE_TEMPLATE = "(\+|-)(([0-9]+)Y)?(([0-9]+)M)?(([0-9]+)D)?" ;
        $EQUAL_TEMPLATE = "/^=".$DATE_TEMPLATE."$/i" ;
        $MORE_TEMPLATE = "/^>".$DATE_TEMPLATE."$/i" ;
        $LOWER_TEMPLATE = "/^<".$DATE_TEMPLATE."$/i" ;
        $BETWEEN_TEMPLATE = "/^>".$DATE_TEMPLATE."&<".$DATE_TEMPLATE."$/i" ;

        if (preg_match_all($TODAY_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $todayMidnigth = new \DateTime() ;
            $todayMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($todayMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, "=", $todayMidnigth) ;
            });
        } elseif (preg_match_all($FUTURE_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $now = new \DateTime() ;
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, ">", $now) ;
            });
        } elseif (preg_match_all($PAST_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $now = new \DateTime() ;
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, "<", $now) ;
            });
        } elseif (preg_match_all($EQUAL_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];
            
            $dateMidnigth = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $dateMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($dateMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, "=", $dateMidnigth) ;
            });
        } elseif (preg_match_all($MORE_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];
            
            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays) ;
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, ">", $date) ;
            });
        } elseif (preg_match_all($LOWER_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];
            
            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays) ;
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, "<", $date) ;
            });
        } elseif (preg_match_all($BETWEEN_TEMPLATE, $this->arguments['datefilter'], $matches)) {
            $signMore = $matches[1][0];
            $nbYearsMore = $matches[3][0];
            $nbMonthMore = $matches[5][0];
            $nbDaysMore = $matches[7][0];
            $dateMin = $this->extractDate($signMore, $nbYearsMore, $nbMonthMore, $nbDaysMore);
            $signLower = $matches[8][0];
            $nbYearsLower = $matches[10][0];
            $nbMonthLower = $matches[12][0];
            $nbDaysLower = $matches[14][0];
            $dateMax = $this->extractDate($signLower, $nbYearsLower, $nbMonthLower, $nbDaysLower);
            if ($dateMin->diff($dateMax)->invert == 0) {
                // $dateMax higher than $dateMin
                $entries = array_filter($entries, function ($entry) use ($dateMin) {
                    return $this->filterEntriesOnDateTraversing($entry, ">", $dateMin) ;
                });
                $entries = array_filter($entries, function ($entry) use ($dateMax) {
                    return $this->filterEntriesOnDateTraversing($entry, "<", $dateMax) ;
                });
            }
        }

        return $entries ;
    }

    private function extractDate(string $sign, string $nbYears, string $nbMonth, string $nbDays): \DateTime
    {
        $dateInterval = new \DateInterval(
            'P'
                .(!empty($nbYears) ? $nbYears . 'Y' : '')
                .(!empty($nbMonth) ? $nbMonth . 'M' : '')
                .(!empty($nbDays) ? $nbDays . 'D' : '')
        );
        $dateInterval->invert = ($sign == "-") ? 1 : 0;
        
        $date = new \DateTime() ;
        $date->add($dateInterval) ;

        return $date;
    }

    private function filterEntriesOnDateTraversing(?array $entry, string $mode = "=", \DateTime $date): bool
    {
        if (empty($entry) || !isset($entry['bf_date_debut_evenement'])) {
            return false;
        }

        $entryStartDate = new \DateTime($entry['bf_date_debut_evenement']);
        $entryEndDate = isset($entry['bf_date_fin_evenement']) ? new \DateTime($entry['bf_date_fin_evenement']) : null  ;
        if (isset($entry['bf_date_fin_evenement']) && strpos($entry['bf_date_fin_evenement'], 'T')=== false) {
            // all day (so = midnigth of next day)
            $entryEndDate->add(new \DateInterval("P1D"));
        }
        $nextDay = (clone $date)->add(new \DateInterval("P1D"));
        switch ($mode) {
            case "<":
                // start before date
                return (
                    $date->diff($entryStartDate)->invert == 1
                    && $entryEndDate && $date->diff($entryEndDate)->invert == 1
                    );
                break;
            case ">":
                // start after date or (before date but and end should be after date, end is needed)
                return (
                    $date->diff($entryStartDate)->invert == 0
                    || ($entryEndDate && $date->diff($entryEndDate)->invert == 0)
                    );
                break;
            case "=":
            default:
                // start before next day midnight and end should be after date midnigth
                return (
                        $nextDay->diff($entryStartDate)->invert == 1
                        && $entryEndDate && $date->diff($entryEndDate)->invert == 0
                    );
        }
    }
}

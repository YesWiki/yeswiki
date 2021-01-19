<?php

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;

class BazarListeAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        return([
            'idtypeannonce' => $this->formatArray($arg['id'] ?? $arg['idtypeannonce'] ?? $_GET['id']),

            // SELECTION DES FICHES
            'query' => $this->formatQuery($arg),
            // Ordre du tri (asc ou desc)
            'ordre' => $arg['ordre'] ?? 'asc',
            // Champ du formulaire utilisé pour le tri
            'champ' => $arg['champ'] ?? 'bf_titre',
            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'],
            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'],
            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg['random'], false),
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => getDateMin($_GET['period'] ?? $arg['period']),
            // transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'correspondance' => $arg['correspondance'],

            // AFFICHAGE
            // Template pour l'affichage de la liste de fiches
            // TODO in code: add '.tpl.html' at the end of the filename if not exist
            'template' => $_GET['template'] ?? $arg['template'] ?? $this->params->get('default_bazar_template'),
            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'],
            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )
            'barregestion' => $this->formatBoolean($arg['barregestion'], true),
            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg['showexportbuttons'], false),
            // Affiche le formulaire de recherche en haut
            'search' => $this->formatBoolean($arg['search'], false),

            // FACETTES
            // Identifiants des champs utilisés pour les facettes
            // Plusieures valeurs possibles, séparées par des virgules, "all" pour toutes les facettes possibles
            // Exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
            'groups' => $this->formatArray($_GET['groups'] ?? $arg['groups']),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{bazarliste titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($_GET['titles'] ?? $arg['titles']),
            'groupicons' => $this->formatArray($arg['groupicons']),
            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg['filtertext'], false),
            // facette à gauche ou à droite
            'filterposition' => $GET['filterposition'] ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $GET['filterposition'] ?? $arg['filterposition'] ?? '3',
            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($_GET['groupsexpanded'] ?? $arg['groupsexpanded'], true)
        ]);
    }

    function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);

        if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
            $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
        }
        ++$GLOBALS['_BAZAR_']['nbbazarliste'];

        $forms = $formManager->getAll();

        // TODO put in all bazar templates
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        $entries = $entryManager->search([
            'queries' => $this->arguments['query'],
            'formsIds' => $this->arguments['idtypeannonce'],
            'keywords' => $_REQUEST['q'],
            'dateMin' => $this->arguments['dateMin']
        ]);

        // Add display data to all entries
        $entries = array_map(function ($fiche) use ($entryManager) {
            $entryManager->appendDisplayData($fiche, false, $this->arguments['correspondance']);
            return $fiche;
        }, $entries);

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

        return $this->render('@bazar/entries/list.twig', [
            'listId' => $GLOBALS['_BAZAR_']['nbbazarliste'],
            'filters' => $filters,
            'renderedEntries' => $this->renderEntries($entries),
            'numEntries' => count($entries),
            'params' => $this->arguments,
            // Search form parameters
            'keywords' => $_GET['q'],
            'pageTag' => $this->wiki->getPageTag(),
            'forms' => count($this->arguments['idtypeannonce']) === 0 ? $forms : '',
            'formId' => $this->arguments['idtypeannonce'][0],
            'facette' => $_GET['facette'],
        ]);
    }

    private function renderEntries($entries) : string
    {
        $showNumEntries = count($this->arguments['groups']) == 0 || count($entries) == 0;

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
        if (strpos($templateName, '.html') === false) {
            $param['template'] = $templateName . '.tpl.html';
        }

        return $this->render("@bazar/{$templateName}", $data);
    }

    private function formatFilters($entries, $forms) : array
    {
        if (count($this->arguments['groups']) > 0) {
            // Scanne tous les champs qui pourraient faire des filtres pour les facettes
            $facetteValue = $this->scanAllFacettable($entries, $this->arguments['groups'], $forms);

            if (count($facetteValue) > 0) {
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

                foreach ($this->arguments['groups'] as $id) {
                    // Formatte la liste des resultats en fonction de la source
                    if (isset($facetteValue[$id])) {
                        if ($facetteValue[$id]['type'] == 'liste') {
                            $field = findFieldByName($forms, $facetteValue[$id]['source']);
                            $list['titre_liste'] = $field->getName();
                            $list['label'] = $field->getOptions();
                        } elseif ($facetteValue[$id]['type'] == 'fiche') {
                            $src = str_replace(array('listefiche', 'checkboxfiche'), '', $facetteValue[$id]['source']);
                            $form = $forms[$src];
                            $list['titre_liste'] = $form['bn_label_nature'];
                            foreach ($facetteValue[$id] as $idfiche => $nb) {
                                if ($idfiche != 'source' && $idfiche != 'type') {
                                    $f = $GLOBALS['wiki']->services->get(EntryManager::class)->getOne($idfiche);
                                    $list['label'][$idfiche] = $f['bf_titre'];
                                }
                            }
                        } elseif ($facetteValue[$id]['type'] == 'form') {
                            if ($facetteValue[$id]['source'] == 'id_typeannonce') {
                                $list['titre_liste'] = _t('BAZ_TYPE_FICHE');
                                foreach ($facetteValue[$id] as $idf => $nb) {
                                    if ($idf != 'source' && $idf != 'type') {
                                        $list['label'][$idf] = $forms[$idf]['bn_label_nature'];
                                    }
                                }
                            } elseif ($facetteValue[$id]['source'] == 'owner') {
                                $list['titre_liste'] = _t('BAZ_CREATOR');
                                foreach ($facetteValue[$id] as $idf => $nb) {
                                    if ($idf != 'source' && $idf != 'type') {
                                        $list['label'][$idf] = $idf;
                                    }
                                }
                            } else {
                                $list['titre_liste'] = $id;
                                foreach ($facetteValue[$id] as $idf => $nb) {
                                    if ($idf != 'source' && $idf != 'type') {
                                        $list['label'][$idf] = $idf;
                                    }
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
                        if (isset($facetteValue[$id][$listkey]) && !empty($facetteValue[$id][$listkey])) {
                            $filters[$idkey]['list'][] = [
                                'id' => $idkey.$listkey,
                                'name' => $idkey,
                                'value' => htmlspecialchars($listkey),
                                'label' => $label,
                                'nb' => $facetteValue[$id][$listkey],
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

    private function scanAllFacettable($entries, $groups, $formtab = '', $onlyLists = false)
    {
        $facetteValue = $fields = [];

        foreach ($entries as $entry) {
            // on recupere les valeurs du formulaire si elles n'existaient pas
            $valform = isset($formtab[$entry['id_typeannonce']]) ? $formtab[$entry['id_typeannonce']] : baz_valeurs_formulaire($entry['id_typeannonce']);
            // on filtre pour n'avoir que les liste, checkbox, listefiche ou checkboxfiche
            $fields[$entry['id_typeannonce']] = isset($fields[$entry['id_typeannonce']])
                ? $fields[$entry['id_typeannonce']]
                : filterFieldsByPropertyName($valform['prepared'], $groups);
            foreach ($entry as $key => $value) {
                $facetteasked = (isset($groups[0]) && $groups[0] == 'all') || in_array($key, $groups);
                if (!empty($value) and is_array($fields[$entry['id_typeannonce']]) && $facetteasked) {
                    $filteredFields = filterFieldsByPropertyName($fields[$entry['id_typeannonce']], [$key]);
                    $field = array_pop($filteredFields);

                    $fieldPropName = null;
                    if( $field instanceof BazarField ) {
                        $fieldPropName = $field->getPropertyName();
                        $fieldType = $field->getType();
                    } else if ( is_array($field)) {
                        $fieldPropName = $field['id'];
                        $fieldType = $field['type'];
                    }

                    if ($fieldPropName) {
                        $islistforeign = (strpos($fieldPropName, 'listefiche')===0) || (strpos($fieldPropName, 'checkboxfiche')===0);
                        $islist = in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste')) && !$islistforeign;
                        $istext = (!in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste', 'checkboxfiche', 'listefiche')));

                        if ($islistforeign) {
                            // listefiche ou checkboxfiche
                            $facetteValue[$fieldPropName]['type'] = 'fiche';
                            $facetteValue[$fieldPropName]['source'] = $key;
                            $tabval = explode(',', $value);
                            foreach ($tabval as $tval) {
                                if (isset($facetteValue[$fieldPropName][$tval])) {
                                    ++$facetteValue[$fieldPropName][$tval];
                                } else {
                                    $facetteValue[$fieldPropName][$tval] = 1;
                                }
                            }
                        } elseif ($islist) {
                            // liste ou checkbox
                            $facetteValue[$fieldPropName]['type'] = 'liste';
                            $facetteValue[$fieldPropName]['source'] = $key;
                            $tabval = explode(',', $value);
                            foreach ($tabval as $tval) {
                                if (isset($facetteValue[$fieldPropName][$tval])) {
                                    ++$facetteValue[$fieldPropName][$tval];
                                } else {
                                    $facetteValue[$fieldPropName][$tval] = 1;
                                }
                            }
                        } elseif ($istext and !$onlyLists) {
                            // texte
                            $facetteValue[$key]['type'] = 'form';
                            $facetteValue[$key]['source'] = $key;
                            if (isset($facetteValue[$key][$value])) {
                                ++$facetteValue[$key][$value];
                            } else {
                                $facetteValue[$key][$value] = 1;
                            }
                        }
                    }
                }
            }
        }
        return $facetteValue;
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
            $query = $arg['query'];
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

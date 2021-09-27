<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Wiki;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;

class BazarListService
{
    public function __construct(
        Wiki $wiki,
        EntryManager $entryManager,
        EntryController $entryController,
        ExternalBazarService $externalBazarService,
        FormManager $formManager
    ) {
        $this->wiki = $wiki;
        $this->entryManager = $entryManager;
        $this->entryController = $entryController;
        $this->externalBazarService = $externalBazarService;
        $this->formManager = $formManager;
    }

    public function getForms($options) : array
    {
        // External mode activated ?
        if (($options['externalModeActivated'] ?? false) === true) {
            return $this->externalBazarService
                        ->getFormsForBazarListe($options['externalIds'], $options['refresh']);
        } else {
            return $this->formManager->getAll();
        }
    }

    public function getEntries($options, $forms = null) : array
    {
        if (!$forms) {
            $forms = $this->getForms($options);
        }

        // External mode activated ?
        // TODO BazarListdynamic test externalmode works
        if (($options['externalModeActivated'] ?? false) === true) {
            $entries = $this->externalBazarService->getEntries([
                'forms' => $forms,
                'refresh' => $options['refresh'],
                'queries' => $options['query']
            ]);
        } else {
            $entries = $this->entryManager->search(
                [
                    'queries' => $options['query'] ?? '',
                    'formsIds' => $options['idtypeannonce'],
                    'keywords' => $_REQUEST['q'] ?? '',
                    'user' => $options['user'],
                    'minDate' => $options['dateMin'],
                    'correspondance' => $options['correspondance'] ?? ''
                ],
                true, // filter on read ACL,
                true // use Guard
            );
        }

        // filter entries on datefilter parameter
        if (!empty($options['datefilter'])) {
            $entries = $this->entryController->filterEntriesOnDate($entries, $options['datefilter']) ;
        }

        // Sort entries
        if ($options['random']) {
            shuffle($entries);
        } else {
            usort($entries, $this->buildFieldSorter($options['ordre'], $options['champ']));
        }

        // Limit entries
        if ($options['nb'] !== '') {
            $entries = array_slice($entries, 0, $options['nb']);
        }

        return $entries;
    }

    public function formatFilters($options, $entries, $forms) : array
    {
        if (count($options['groups'] ?? []) == 0) {
            return [];
        }
        
        // Scanne tous les champs qui pourraient faire des filtres pour les facettes
        $facettables = $this->formManager
                            ->scanAllFacettable($entries, $options['groups']);

        if (count($facettables) == 0) {
            return [];
        }
        
        if (!$forms) {
            $forms = $this->getForms($options);
        }
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
            if (in_array($facettable['type'], ['liste','fiche'])) {
                $field = $this->findFieldByName($forms, $facettable['source']);
                if (!($field instanceof BazarField)) {
                    if ($this->debug) {
                        trigger_error("Waiting field instanceof BazarField from findFieldByName, ".
                            (
                                (is_null($field)) ? 'null' : (
                                    (gettype($field) == "object") ? get_class($field) : gettype($field)
                                )
                            ) . ' returned');
                    }
                } elseif ($facettable['type'] == 'liste') {
                    $list['titre_liste'] = $field->getLabel();
                    $list['label'] = $field->getOptions();
                } elseif ($facettable['type'] == 'fiche') {
                    $formId = $field->getLinkedObjectName() ;
                    $form = $forms[$formId];
                    $list['titre_liste'] = $form['bn_label_nature'];
                    foreach ($facettable as $idfiche => $nb) {
                        if ($idfiche != 'source' && $idfiche != 'type') {
                            $f = $this->entryManager->getOne($idfiche);
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

            $i = array_key_first(array_filter($options['groups'], function ($value) use ($idkey) {
                return ($value == $idkey) ;
            }));

            $filters[$idkey]['icon'] =
                (isset($options['groupicons'][$i]) && !empty($options['groupicons'][$i])) ?
                    '<i class="'.$options['groupicons'][$i].'"></i> ' : '';

            $filters[$idkey]['title'] =
                (isset($options['titles'][$i]) && !empty($options['titles'][$i])) ?
                    $options['titles'][$i] : $list['titre_liste'];

            $filters[$idkey]['collapsed'] = ($i != 0) && !$options['groupsexpanded'];

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
        });

        foreach ($filters as $id => $filter) {
            if (isset($filter['index'])) {
                unset($filter['index']) ;
            }
        }

        return $filters;
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
                }
            }
        }
    }

    private function buildFieldSorter($ordre, $champ): callable
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

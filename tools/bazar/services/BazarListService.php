<?php

namespace YesWiki\Bazar\Service;

use Attach;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Wiki;

class BazarListService
{
    protected $entryController;
    protected $entryManager;
    protected $externalBazarService;
    protected $formManager;
    protected $wiki;

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

    public function getForms($options): array
    {
        // External mode activated ?
        if (($options['externalModeActivated'] ?? false) === true) {
            return $this->externalBazarService
                        ->getFormsForBazarListe($options['externalIds'], $options['refresh']);
        } else {
            return $this->formManager->getAll();
        }
    }

    private function replaceDefaultImage($options, $forms, $entries): array
    {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $attach = new attach($this->wiki);
        $basePath = $attach->GetUploadPath();
        $basePath = $basePath . (substr($basePath, -1) != '/' ? '/' : '');

        foreach ($options['idtypeannonce'] as $idtypeannonce) {
            $template = $forms[(int)$idtypeannonce]['template'] ?? [];
            $image_names = array_map(
                function ($item) {return $item[1]; },
                array_filter(
                    $template,
                    function ($item) { return $item[0] == 'image'; }
                )
            );
            foreach ($image_names as $image_name) {
                $default_image_filename = "defaultimage{$idtypeannonce}_{$image_name}.jpg";
                if (file_exists($basePath . $default_image_filename)) {
                    $image_key = 'image' . $image_name;
                    foreach ($entries as $key => $entry) {
                        if (array_key_exists($image_key, $entry) && ($entry[$image_key] == null)) {
                            $entry[$image_key] = $default_image_filename;
                        }
                        $entries[$key] = $entry;
                    }
                }
            }
        }

        return $entries;
    }

    public function getEntries($options, $forms = null): array
    {
        if (!$forms) {
            $forms = $this->getForms($options);
        }

        // External mode activated ?
        // TODO BazarListdynamic test externalmode works
        if (($options['externalModeActivated'] ?? false) === true) {
            $entries = $this->externalBazarService->getEntries([
                'forms' => $forms,
                'refresh' => $options['refresh'] ?? false,
                'queries' => $options['query'] ?? '',
                'correspondance' => $options['correspondance'] ?? '',
            ]);
        } else {
            $entries = $this->entryManager->search(
                [
                    'queries' => $options['query'] ?? '',
                    'formsIds' => $options['idtypeannonce'] ?? [],
                    'keywords' => $_REQUEST['q'] ?? '',
                    'user' => $options['user'],
                    'minDate' => $options['dateMin'],
                    'correspondance' => $options['correspondance'] ?? '',
                ],
                true, // filter on read ACL,
                true // use Guard
            );
        }
        $entries = $this->replaceDefaultImage($options, $forms, $entries);

        // filter entries on datefilter parameter
        if (!empty($options['datefilter'])) {
            $entries = $this->entryController->filterEntriesOnDate($entries, $options['datefilter']);
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

    public function formatFilters($options, $entries, $forms): array
    {
        if (empty($options['groups'])) {
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
                if (count($tabdecoup) > 1) {
                    $tabfacette[$tabdecoup[0]] = explode(',', trim($tabdecoup[1]));
                }
            }
        }

        foreach ($facettables as $facetteId => $facettable) {
            $list = [];
            // Formatte la liste des resultats en fonction de la source
            if (in_array($facettable['type'], ['liste', 'fiche'])) {
                $field = $this->findFieldByName($forms, $facettable['source']);
                if (!($field instanceof BazarField)) {
                    if ($this->debug) {
                        trigger_error('Waiting field instanceof BazarField from findFieldByName, ' .
                            (
                                (is_null($field)) ? 'null' : (
                                    (gettype($field) == 'object') ? get_class($field) : gettype($field)
                                )
                            ) . ' returned');
                    }
                } elseif ($facettable['type'] == 'liste') {
                    $list['title'] = $field->getLabel();
                    $list['options'] = $field->getOptions();
                    $list['optionsTree'] = $field->getOptionsTree();
                } elseif ($facettable['type'] == 'fiche') {
                    $formId = $field->getLinkedObjectName();
                    $form = $forms[$formId];
                    $list['title'] = $form['bn_label_nature'];
                    $list['options'] = [];
                    foreach ($facettable as $idfiche => $nb) {
                        if ($idfiche != 'source' && $idfiche != 'type') {
                            $f = $this->entryManager->getOne($idfiche);
                            if (!empty($f['bf_titre'])) {
                                $list['options'][$idfiche] = $f['bf_titre'];
                            }
                        }
                    }
                }
            } elseif ($facettable['type'] == 'form') {
                if ($facettable['source'] == 'id_typeannonce') {
                    $list['title'] = _t('BAZ_TYPE_FICHE');
                    foreach ($facettable as $idf => $nb) {
                        if ($idf != 'source' && $idf != 'type') {
                            $list['options'][$idf] = $forms[$idf]['bn_label_nature'] ?? $idf;
                        }
                    }
                } elseif ($facettable['source'] == 'owner') {
                    $list['title'] = _t('BAZ_CREATOR');
                    foreach ($facettable as $idf => $nb) {
                        if ($idf != 'source' && $idf != 'type') {
                            $list['options'][$idf] = $idf;
                        }
                    }
                } else {
                    $list['title'] = $id;
                    foreach ($facettable as $idf => $nb) {
                        if ($idf != 'source' && $idf != 'type') {
                            $list['options'][$idf] = $idf;
                        }
                    }
                }
            }

            $facetteId = htmlspecialchars($facetteId);

            // sort facette labels
            natcasesort($list['options']);

            function createFilterOption($listkey, $label, $facetteId, $facettable, $tabfacette)
            {
                return [
                    'id' => $facetteId . $listkey,
                    'name' => $facetteId,
                    'value' => htmlspecialchars($listkey),
                    'label' => $label,
                    'nb' => $facettable[$listkey] ?? 0,
                    'checked' => (isset($tabfacette[$facetteId]) and in_array($listkey, $tabfacette[$facetteId])) ? ' checked' : '',
                ];
            }

            $filterOptions = [];
            foreach ($list['options'] as $listkey => $label) {
                if (!empty($facettable[$listkey])) {
                    $filterOptions[] = createFilterOption($listkey, $label, $facetteId, $facettable, $tabfacette);
                }
            }

            if (!empty($list['optionsTree'])) {
                function recursivelyConvertNode($node, $facetteId, $facettable, $tabfacette)
                {
                    $result = createFilterOption($node['id'], $node['label'], $facetteId, $facettable, $tabfacette);

                    foreach ($node['children'] as $childNode) {
                        // if (!empty($facettable[$childNode['id']])) {
                        $result['children'][] = recursivelyConvertNode($childNode, $facetteId, $facettable, $tabfacette);
                        // }
                    }

                    return $result;
                }
                foreach ($list['optionsTree'] as $node) {
                    // if (!empty($facettable[$node['id']])) {
                    $filterOptionsTree[] = recursivelyConvertNode($node, $facetteId, $facettable, $tabfacette);
                    // }
                }
            }

            $i = array_key_first(array_filter($options['groups'], function ($value) use ($facetteId) {
                return $value == $facetteId;
            }));

            $filters[$facetteId] = [
                'index' => $i,
                'icon' => !empty($options['groupicons'][$i]) ? '<i class="' . $options['groupicons'][$i] . '"></i> ' : '',
                'title' => !empty($options['titles'][$i]) ? $options['titles'][$i] : $list['title'],
                'collapsed' => ($i != 0) && !$options['groupsexpanded'],
                'list' => $filterOptions,
                'listTree' => $filterOptionsTree ?? null,
            ];
        }

        // reorder $filters
        uasort($filters, function ($a, $b) {
            if (isset($a['index']) && isset($b['index'])) {
                if ($a['index'] == $b['index']) {
                    return 0;
                } else {
                    return ($a['index'] < $b['index']) ? -1 : 1;
                }
            } elseif (isset($a['index'])) {
                return 1;
            } elseif (isset($b['index'])) {
                return -1;
            } else {
                return 0;
            }
        });

        foreach ($filters as $id => $filter) {
            if (isset($filter['index'])) {
                unset($filter['index']);
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
                $first = $b[$champ] ?? '';
                $second = $a[$champ] ?? '';
            } else {
                $first = $a[$champ] ?? '';
                $second = $b[$champ] ?? '';
            }
            // compare insentive uppercase even for special chars
            return strcmp($this->sanitizeStringForCompare($first), $this->sanitizeStringForCompare($second));
        };
    }

    private function sanitizeStringForCompare($value): string
    {
        $value = is_scalar($value)
            ? strval($value)
            : json_encode($value);

        return strtoupper(removeAccents($value));
    }
}

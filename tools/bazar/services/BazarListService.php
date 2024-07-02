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

    public function getFilters($options, $entries, $forms): array
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
        // only used for old non dynamic bazarlist
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
            // Create a filter object to be returned to the view
            $filter = [
                'title' => '',
                'icon' => '',
                'nodes' => [],
                'collapsed' => true,
            ];

            // Depending on the facette type, get the list of filter nodes
            if ($facettable['type'] == 'liste') {
                $field = $this->findFieldByName($forms, $facettable['source']);
                $filter['title'] = $field->getLabel();
                if (!empty($field->getOptionsTree())) {
                    foreach ($field->getOptionsTree() as $node) {
                        $filter['nodes'][] = $this->recursivelyCreateNode($node, $facetteId, $facettable, $tabfacette);
                    }
                } else {
                    foreach ($field->getOptions() as $value => $label) {
                        if (!empty($facettable[$value])) {
                            $filter['nodes'][] = $this->createFilterNode($value, $label, $facetteId, $facettable, $tabfacette);
                        }
                    }
                }
            } elseif ($facettable['type'] == 'fiche') {
                $filter['title'] = $form['bn_label_nature'];
                $field = $this->findFieldByName($forms, $facettable['source']);
                $formId = $field->getLinkedObjectName();
                $form = $forms[$formId];
                foreach ($facettable as $entryId => $nb) {
                    if ($entryId != 'source' && $entryId != 'type') {
                        $entry = $this->entryManager->getOne($entryId);
                        if (!empty($f['bf_titre'])) {
                            $filter['nodes'][] = $this->createFilterNode($entryId, $entry['bf_titre'], $facetteId, $facettable, $tabfacette);
                        }
                    }
                }
                natcasesort($filter['nodes']); // sort nodes by label
            } elseif ($facettable['type'] == 'form') {
                if ($facettable['source'] == 'id_typeannonce') {
                    $filter['title'] = _t('BAZ_TYPE_FICHE');
                } elseif ($facettable['source'] == 'owner') {
                    $filter['title'] = _t('BAZ_CREATOR');
                } else {
                    $filter['title'] = $id;
                }
                foreach ($facettable as $idf => $nb) {
                    if ($idf != 'source' && $idf != 'type') {
                        $label = $facettable['source'] == 'id_typeannonce' ? $forms[$idf]['bn_label_nature'] ?? $idf : $idf;
                        $filter['nodes'][] = $this->createFilterNode($idf, $label, $facetteId, $facettable, $tabfacette);
                    }
                }
                natcasesort($filter['nodes']); // sort nodes by label
            }

            $facetteId = htmlspecialchars($facetteId);

            $i = array_key_first(array_filter($options['groups'], function ($value) use ($facetteId) {
                return $value == $facetteId;
            }));

            // Filter Icon
            if (!empty($options['groupicons'][$i])) {
                $filter['icon'] = '<i class="' . $options['groupicons'][$i] . '"></i> ';
            }
            // Custom title
            if (!empty($options['titles'][$i])) {
                $filter['title'] = $options['titles'][$i];
            }
            // Initial Collapsed state
            $filter['collapsed'] = ($i != 0) && !$options['groupsexpanded'];
            $filter['index'] = $i;

            $filters[$facetteId] = $filter;
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

    private function createFilterNode($value, $label, $facetteId, $facettable, $tabfacette)
    {
        return [
            'value' => htmlspecialchars($value),
            'label' => $label,
            'children' => [],
            // below fields are only used for old non dynamic bazarlist
            'id' => $facetteId . $value,
            'name' => $facetteId,
            'nb' => $facettable[$value] ?? 0,
            'checked' => (isset($tabfacette[$facetteId]) and in_array($value, $tabfacette[$facetteId])) ? ' checked' : '',
        ];
    }

    private function recursivelyCreateNode($node, $facetteId, $facettable, $tabfacette)
    {
        $result = $this->createFilterNode($node['id'], $node['label'], $facetteId, $facettable, $tabfacette);
        foreach ($node['children'] as $childNode) {
            $result['children'][] = $this->recursivelyCreateNode($childNode, $facetteId, $facettable, $tabfacette);
        }

        return $result;
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

<?php

namespace YesWiki\Bazar\Service;

use Attach;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Field\EnumField;
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

    // Use bazarlist options like groups, titles, groupicons, groupsexpanded
    // To create a filters array to be used by the view
    // Note for [old-non-dynamic-bazarlist] For old bazarlist, most of the calculation happens on the backend
    // But with the new dynamic bazalist, everything is done on the front
    public function getFilters($options, $entries, $forms): array
    {
        // add default options
        $options = array_merge([
            'groups' => [],
            'dynamic' => true,
            'groupsexpanded' => false,
        ], $options);

        $formIdsUsed = array_unique(array_column($entries, 'id_typeannonce'));
        $formsUsed = array_map(function ($formId) use ($forms) { return $forms[$formId]; }, $formIdsUsed);
        $allFields = array_merge(...array_column($formsUsed, 'prepared'));

        $propNames = $options['groups'];
        // Special value groups=all use all available Enum fields
        if (count($propNames) == 1 && $propNames[0] == 'all') {
            $enumFields = array_filter($allFields, function ($field) {
                return $field instanceof EnumField;
            });
            $propNames = array_map(function ($field) { return $field->getPropertyName(); }, $enumFields);
        }

        $filters = [];

        foreach ($propNames as $index => $propName) {
            // Create a filter object to be returned to the view
            $filter = [
                'propName' => $propName,
                'title' => '',
                'icon' => '',
                'nodes' => [],
                'collapsed' => true,
            ];

            // Check if an existing Form Field existing by this propName
            foreach ($allFields as $aField) {
                if ($aField->getPropertyName() == $propName) {
                    $field = $aField;
                    break;
                }
            }
            // Depending on the propName, get the list of filter nodes
            if (!empty($field) && $field instanceof EnumField) {
                // ENUM FIELD
                $filter['title'] = $field->getLabel();

                if (!empty($field->getOptionsTree()) && $options['dynamic'] == true) {
                    // OptionsTree only supported by bazarlist dynamic
                    foreach ($field->getOptionsTree() as $node) {
                        $filter['nodes'][] = $this->recursivelyCreateNode($node);
                    }
                } else {
                    foreach ($field->getOptions() as $value => $label) {
                        $filter['nodes'][] = $this->createFilterNode($value, $label);
                    }
                }
            } elseif ($propName == 'id_typeannonce') {
                // SPECIAL PROPNAME id_typeannonce
                $filter['title'] = _t('BAZ_TYPE_FICHE');
                foreach ($formsUsed as $form) {
                    $filter['nodes'][] = $this->createFilterNode($form['bn_id_nature'], $form['bn_label_nature']);
                }
                usort($filter['nodes'], function ($a, $b) { return strcmp($a['label'], $b['label']); });
            } else {
                // OTHER PROPNAME (for example a field that is not an Enum)
                $filter['title'] = $propName == 'owner' ? _t('BAZ_CREATOR') : $propName;
                // We collect all values
                $uniqValues = array_unique(array_column($entries, $propName));
                sort($uniqValues);
                foreach ($uniqValues as $value) {
                    $filter['nodes'][] = $this->createFilterNode($value, $value);
                }
            }

            // Filter Icon
            if (!empty($options['groupicons'][$index])) {
                $filter['icon'] = '<i class="' . $options['groupicons'][$index] . '"></i> ';
            }
            // Custom title
            if (!empty($options['titles'][$index])) {
                $filter['title'] = $options['titles'][$index];
            }
            // Initial Collapsed state
            $filter['collapsed'] = ($index != 0) && !$options['groupsexpanded'];

            // [old-non-dynamic-bazarlist] For old bazarlist, most of the calculation happens on the backend
            if ($options['dynamic'] == false) {
                $checkedValues = $this->parseCheckedFiltersInURLForNonDynamic();
                // Calculate the count for each filterNode
                $entriesValues = array_column($entries, $propName);
                // convert string values to array
                $entriesValues = array_map(function ($val) { return explode(',', $val); }, $entriesValues);
                // flatten the array
                $entriesValues = array_merge(...$entriesValues);
                $countedValues = array_count_values($entriesValues);
                $adjustedNodes = [];
                foreach ($filter['nodes'] as $rootNode) {
                    $adjustedNodes[] = $this->recursivelyInitValuesForNonDynamic($rootNode, $propName, $countedValues, $checkedValues);
                }
                $filter['nodes'] = $adjustedNodes;
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    // [old-non-dynamic-bazarlist] filters state in stored in URL
    // ?Page&facette=field1=3,4|field2=web
    // => ['field1' => ['3', '4'], 'field2' => ['web']]
    private function parseCheckedFiltersInURLForNonDynamic()
    {
        if (empty($_GET['facette'])) {
            return [];
        }
        $result = [];
        foreach (explode('|', $_GET['facette']) as $field) {
            list($key, $values) = explode('=', $field);
            $result[$key] = explode(',', trim($values));
        }

        return $result;
    }

    private function createFilterNode($value, $label)
    {
        return [
            'value' => htmlspecialchars($value),
            'label' => $label,
            'children' => [],
        ];
    }

    private function recursivelyCreateNode($node)
    {
        $result = $this->createFilterNode($node['id'], $node['label']);
        foreach ($node['children'] as $childNode) {
            $result['children'][] = $this->recursivelyCreateNode($childNode);
        }

        return $result;
    }

    private function recursivelyInitValuesForNonDynamic($node, $propName, $countedValues, $checkedValues)
    {
        $result = array_merge($node, [
            'id' => $propName . $node['value'],
            'name' => $propName,
            'count' => $countedValues[$node['value']] ?? 0,
            'checked' => isset($checkedValues[$propName]) && in_array($node['value'], $checkedValues[$propName]) ? ' checked' : '',
        ]);

        foreach ($node['children'] as &$childNode) {
            $result['children'][] = $this->recursivelyInitValuesForNonDynamic($childNode, $propName, $countedValues, $checkedValues);
        }

        return $result;
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

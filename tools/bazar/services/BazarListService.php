<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Wiki;

class BazarListService
{
    protected $entryManager;
    protected $entryExtraFields;
    protected $externalBazarService;
    protected $formManager;
    protected $wiki;

    public function __construct(
        Wiki $wiki,
        EntryManager $entryManager,
        EntryExtraFieldsService $entryExtrafields,
        ExternalBazarService $externalBazarService,
        FormManager $formManager,
    ) {
        $this->wiki = $wiki;
        $this->entryManager = $entryManager;
        $this->entryExtraFields = $entryExtrafields;
        $this->externalBazarService = $externalBazarService;
        $this->formManager = $formManager;
    }

    public function getForms($pOptions = []): array
    {
        $vIDs = $this->getIDs($pOptions['idtypeannonce'] ?? $pOptions['id'] ?? '');

        $vLocalForms = $this->formManager->getMany($vIDs['locals']);
        $vExternalForms = $this->externalBazarService->getForms($vIDs['externals'], $pOptions['refresh'] ?? null);

        $vForms = $vLocalForms + $vExternalForms;

        return $vForms;
    }

    private function replaceDefaultImage($options, $forms, $entries): array
    {
        if (!class_exists('attach')) {
            include YESWIKI_SOURCE_DIR . '/tools/attach/libs/attach.lib.php';
        }
        $attach = new \Attach($this->wiki);
        $basePath = $attach->GetUploadPath();
        $basePath = $basePath . (substr($basePath, -1) != '/' ? '/' : '');
        $formIds = array_keys($forms) ?? [];

        foreach ($formIds as $id) {
            $template = $forms[(int) $id]['template'] ?? [];
            $image_names = array_map(
                function ($item) {
                    return $item[1];
                },
                array_filter(
                    $template,
                    function ($item) {
                        return $item[0] == 'image';
                    },
                ),
            );
            foreach ($image_names as $image_name) {
                $default_image_filename = "defaultimage{$id}_{$image_name}.jpg";
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

    public function getEntries($pOptions, $pForms = null): array
    {
        if (is_array($pOptions)) {
            $pOptions['queries'] = $pOptions['queries'] ?? $pOptions['query'] ?? null;
        }

        if ($pForms == null) {
            $vForms = $this->getForms($pOptions);
        } else {
            $vForms = $pForms;
        }

        $vSelectedID = $pOptions['selectedID'] ?? '';
        if (trim($vSelectedID) == '') {
            $vSelectedID = null;
        }

        $vIDs = $this->getIDs($vSelectedID ?? $pOptions['idtypeannonce'] ?? $pOptions['id'] ?? '');

        $vLocalIDs = $vIDs['locals'];
        $vExternalIDs = $vIDs['externals'];

        if (count($vLocalIDs) > 0 || count($vExternalIDs) == 0) {
            $vSearchManager = $this->wiki->services->get(SearchManager::class);

            $vLocalEntries = $vSearchManager->search(
                array_merge(
                    $pOptions,
                    [
                        'formsIds' => $vLocalIDs,
                    ],
                ),
                true, // filter on read ACL,
                true // use Guard
            );
        } else {
            $vLocalEntries = [];
        }

        if (count($vExternalIDs) > 0) {
            $vExternalEntries = $this->externalBazarService->getEntries(
                array_merge($pOptions, [
                    'idtypeannonce' => ['locals' => [], 'externals' => $vExternalIDs],
                    'forms' => $vForms,
                ]),
            );
        } else {
            $vExternalEntries = [];
        }

        $vEntries = array_merge($vLocalEntries, $vExternalEntries);

        // filter entries on datefilter parameter
        if (!empty($pOptions['datefilter'])) {
            $vEntries = $this->wiki->services->get(EntryController::class)->filterEntriesOnDate($vEntries, $pOptions['datefilter']);
        }

        // Sort entries
        if ($pOptions['random'] ?? false) {
            shuffle($vEntries);
        } else {
            usort($vEntries, $this->buildFieldSorter($pOptions['ordre'] ?? 'asc', $pOptions['champ'] ?? 'bf_titre'));
        }

        // Limit entries
        if ($pOptions['nb'] ?? false) {
            $vEntries = array_slice($vEntries, 0, $pOptions['nb']);
        }

        $vEntries = $this->replaceDefaultImage($pOptions, $vForms, $vEntries);

        // add extra informations (comments, reactions, metadatas)
        if (($pOptions['extrafields'] ?? false) === true) {
            foreach ($vEntries as $i => $vEntry) {
                $this->entryExtraFields->setEntryId($vEntry['id_fiche']);
                foreach (EntryExtraFieldsService::EXTRA_FIELDS as $vField) {
                    $vEntries[$i][$vField] = $this->entryExtraFields->get($vField);
                }
                // for the linked entries, we need to add some informations to html_data
                if (!empty($vEntries[$i]['linked_data'])) {
                    $vEntries[$i]['html_data'] .= $this->entryExtraFields->appendHtmlData($vEntries[$i]['linked_data']);
                }
            }
        }

        return $vEntries;
    }

    // Use bazarlist options like groups, titles, groupicons, groupsexpanded
    // To create a filters array to be used by the view
    // Note for [old-non-dynamic-bazarlist] For old bazarlist, most of the calculation happens on the backend
    // But with the new dynamic bazalist, everything is done on the front
    public function getFilters($options, $entries, $forms, $withIdIndexes = false): array
    {
        // add default options
        $options = array_merge([
            'groups' => [],
            'dynamic' => true,
            'groupsexpanded' => false,
        ], $options);

        $formIdsUsed = array_unique(array_column($entries, 'id_typeannonce'));
        $formsUsed = array_map(function ($formId) use ($forms) {
            return $forms[$formId] ?? null;
        }, $formIdsUsed);
        $allFields = array_merge(...array_column($formsUsed, 'prepared'));

        $propNames = $options['groups'];
        // Special value groups=all use all available Enum fields
        if (count($propNames) == 1 && $propNames[0] == 'all') {
            $enumFields = array_filter($allFields, function ($field) {
                return $field instanceof EnumField;
            });
            $propNames = array_map(function ($field) {
                return $field->getPropertyName();
            }, $enumFields);
        }

        $filters = [];
        $linkedSep = '_-_';
        foreach ($propNames as $index => $propName) {
            // Create a filter object to be returned to the view
            $filter = [
                'propName' => $propName,
                'title' => '',
                'icon' => '',
                'nodes' => [],
                'collapsed' => true,
            ];

            // Check if linked data value
            if (str_contains($propName, $linkedSep)) {
                $field = $propName;
            } else {
                // Check if an existing Form Field existing by this propName
                foreach ($allFields as $aField) {
                    if ($aField->getPropertyName() == $propName) {
                        $field = $aField;
                        break;
                    }
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
                usort($filter['nodes'], function ($a, $b) {
                    return strcmp($a['label'], $b['label']);
                });
            } elseif (str_contains($propName, $linkedSep)) {
                $idLinkedData = explode($linkedSep, $propName);
                $linkedField = [];
                if (!empty($idLinkedData[0]) && !empty($idLinkedData[1])) {
                    $linkedField = $this->formManager->findFieldWithId($formIdsUsed, $idLinkedData[0]);
                    if (!empty($linkedField)) {
                        $linkedFormId = $linkedField->getLinkedObjectName();
                        $finalField = $this->formManager->findFieldWithId([$linkedFormId], $idLinkedData[1]);
                        if (!empty($finalField)) {
                            $filter['title'] = $finalField->getLabel();
                            if ($finalField instanceof EnumField) {
                                if (!empty($finalField->getOptionsTree()) && $options['dynamic'] == true) {
                                    // OptionsTree only supported by bazarlist dynamic
                                    foreach ($finalField->getOptionsTree() as $node) {
                                        $filter['nodes'][$node['value']] = $this->recursivelyCreateNode($node);
                                    }
                                } else {
                                    foreach ($finalField->getOptions() as $value => $label) {
                                        $filter['nodes'][$value] = $this->createFilterNode($value, $label);
                                    }
                                }
                            }
                            // TODO: options?
                        }
                    }
                }
            } else {
                // OTHER PROPNAME (for example a field that is not an Enum)
                $foundField = $this->formManager->findFieldWithId($formIdsUsed, $propName);
                if (!empty($foundField)) {
                    $filter['title'] = $foundField->getLabel();
                } else {
                    $filter['title'] = $propName == 'owner' ? _t('BAZ_CREATOR') : $propName;
                }

                // We collect all values
                $uniqValues = array_unique(array_column($entries, $propName));

                usort($uniqValues, function ($a, $b) {
                    return strcmp(strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $a)), strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $b)));
                });

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
                $entriesValues = array_map(function ($val) {
                    return explode(',', $val ?? '');
                }, $entriesValues);
                // flatten the array
                $entriesValues = array_merge(...$entriesValues);
                $countedValues = array_count_values($entriesValues);
                $adjustedNodes = [];
                foreach ($filter['nodes'] as $rootNode) {
                    $adjustedNodes[] = $this->recursivelyInitValuesForNonDynamic($rootNode, $propName, $countedValues, $checkedValues);
                }
                $filter['nodes'] = $adjustedNodes;
            }
            if ($withIdIndexes) {
                $filters[$filter['propName']] = $filter;
            } else {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    // [old-non-dynamic-bazarlist] filters state in stored in URL
    // ?Page&facette=field1=3,4|field2=web
    // => ['field1' => ['3', '4'], 'field2' => ['web']]
    private function parseCheckedFiltersInURLForNonDynamic()
    {
        $facette = $this->wiki->request->query->get('facette');
        if (empty($facette)) {
            return [];
        }
        $result = [];
        foreach (explode('|', $facette) as $field) {
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

    private function getValueForArray($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }
        if (is_null($key)) {
            return $array;
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /* Get the unique ID (local or external) contained in $pIDs as [ "locals" => [...], "externals" => [...] ]
    or throw an exception if there is less or more than 1
    */

    public function getTheID($pIDs, $pThrowException = true)
    {
        $vIDs = $this->getIDs($pIDs);

        $vLocalIDs = $vIDs['locals'];
        $vExternalIDs = $vIDs['externals'];

        $vLocalIDsCount = count($vLocalIDs);
        $vExternalIDsCount = count($vExternalIDs);

        if ($vLocalIDsCount + $vExternalIDsCount != 1) {
            if ($pThrowException) {
                throw new \Exception('There should be exactly 1 ID specified instead of ' . ($vLocalIDsCount + $vExternalIDsCount));
            }

            return null;
        }

        if ($vLocalIDsCount == 1) {
            $vID = $vLocalIDs[0];
            $vKey = $vID;
            $vIsExternal = false;
        } else {
            $vID = $vExternalIDs[0]['id'];
            $vKey = $this->externalBazarService->getExternalFormIDKey($vExternalIDs[0]);
            $vIsExternal = true;
        }

        return ['id' => $vID, 'key' => $vKey, 'isExternal' => $vIsExternal];
    }

    public function getIDs($pIDs)
    {
        if ($pIDs === null) {
            $vLocalIDs = array_map(function ($pForm) {
                return $pForm['bn_id_nature'];
            }, $this->formManager->getAll());
            $vExternalIDs = [];
        } else {
            $vIDs = $this->parseIDs($pIDs);

            $vLocalIDs = $vIDs['locals'];
            $vExternalIDs = $vIDs['externals'];
        }

        $vLocalIDs = array_values(array_unique($vLocalIDs));

        $vUniqueExternalIDs = [];

        foreach ($vExternalIDs as $vExternalID) {
            $vKey = $vExternalID['url'] . '|' . $vExternalID['id'];

            if (isset($vUniqueExternalIDs[$vKey])) {
                throw new \Exception('The external ID ' . $vExternalID['id'] . ' is requested multiple times for server ' . $vExternalID['url']);
            }
            $vUniqueExternalIDs[$vKey] = $vExternalID;
        }

        $vUniqueExternalIDs = array_values($vUniqueExternalIDs);

        return
        [
            'locals' => $vLocalIDs,
            'externals' => $vUniqueExternalIDs,
        ];
    }

    protected function isValidID($pID)
    {
        if (!is_string($pID)) {
            return false;
        }

        $vID = intval($pID);

        if ($vID < 0) {
            return false;
        }

        if (strval($vID) !== $pID) {
            return false;
        }

        return true;
    }

    protected function isValidURL($pURL)
    {
        return true; // keep it for later : URL extracted by getExternalURLsFromIDs should be correct
    }

    protected function parseIDs($pIDs)
    {
        if (is_array($pIDs)) {
            if (isset($pIDs['locals'])) {
                // already parsed
                return $pIDs;
            }   // Ensure it is a string
            $pIDs = implode(',', $pIDs);
            // Ensure $pIDs is a string
        }

        $pIDs = preg_replace('/[^,\s]*\s*\|(?:\s*(?:\([\s,0-9\->]*\))|(?:[0-9\->]*))/', '"\\0"', strip_tags($pIDs));

        $vLines = str_getcsv($pIDs, ',', '"', '\\');

        $vLines = array_filter($vLines, function ($vLine) {
            return !empty($vLine) && trim($vLine) != '';
        });

        $vIDs = [];

        foreach ($vLines as $vLine) {
            if (preg_match('/^[()0-9,\s\->]*$/', $vLine)) {
                $vPiped = '|' . $vLine;
            } elseif (!strpos($vLine, '|')) {
                if (preg_match('/^[()0-9,\s\->]*$/', $vLine)) {
                    $vPiped = '|' . $vLine;
                } else {
                    $vPiped = $vLine . '|';
                }
            } else {
                $vPiped = $vLine;
            }

            $vExploded = explode('|', $vPiped);

            $vURL = trim($vExploded[0]);
            $vPostFix = $vExploded[1];

            $vPostFix = preg_replace('/[\s()]*/', '', $vPostFix);

            $vPostFix = explode(',', $vPostFix);

            foreach ($vPostFix as $vID) {
                $vCorrespondance = preg_split('/\->?/', $vID);

                if (count($vCorrespondance) > 1) {
                    $vIDs[] = ['url' => $vURL, 'id' => $vCorrespondance[0], 'localFormId' => $vCorrespondance[1]];
                } else {
                    $vIDs[] = ['url' => $vURL, 'id' => $vCorrespondance[0], 'localFormId' => ''];
                }
            }
        }

        $vResults = ['locals' => [], 'externals' => []];

        foreach ($vIDs as $vID) {
            if (trim($vID['url']) == '') {
                if (!$this->isValidID($vID['id'])) {
                    throw new \Exception('Invalid ID');
                }

                array_push($vResults['locals'], $vID['id']);
            } else {
                if (!$this->isValidURL($vID['url'])) {
                    throw new \Exception('Invalid URL ' . $vID['url']);
                }
                if (!$this->isValidID($vID['id'])) {
                    throw new \Exception('Invalid external ID ' . $vID['id'] . print_r($vID, true));
                }
                if (isset($vID['localFormId']) && (trim($vID['localFormId']) != '') && !$this->isValidID($vID['localFormId'])) {
                    throw new Exception('Invalid local ID');
                }

                array_push($vResults['externals'], $vID);
            }
        }

        return $vResults;
    }

    private function buildFieldSorter($ordre, $champ): callable
    {
        return function ($a, $b) use ($ordre, $champ) {
            if (strstr($champ, '.')) {
                $val1 = $this->getValueForArray($a, $champ);
                $val2 = $this->getValueForArray($b, $champ);
            } else {
                $val1 = $a[$champ] ?? '';
                $val2 = $b[$champ] ?? '';
            }
            if ($ordre == 'desc') {
                return strnatcmp(
                    $this->sanitizeStringForCompare($val2),
                    $this->sanitizeStringForCompare($val1),
                );
            } else {
                return strnatcmp(
                    $this->sanitizeStringForCompare($val1),
                    $this->sanitizeStringForCompare($val2),
                );
            }
        };
    }

    private function sanitizeStringForCompare($value): string
    {
        if ($value === null) {
            $value = '';
        }
        $value = is_scalar($value)
            ? strval($value)
            : json_encode($value);

        return strtoupper(removeAccents($value));
    }
}

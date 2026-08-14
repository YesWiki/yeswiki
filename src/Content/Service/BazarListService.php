<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\EnumField;
use YesWiki\Search\Service\SearchManager;

class BazarListService
{
    protected $entryManager;
    protected $entryExtraFields;
    protected $formManager;
    protected ContainerInterface $container;

    public function __construct(
        ContainerInterface $container,
        EntryManager $entryManager,
        EntryExtraFieldsService $entryExtrafields,
        FormManager $formManager,
    ) {
        $this->container = $container;
        $this->entryManager = $entryManager;
        $this->entryExtraFields = $entryExtrafields;
        $this->formManager = $formManager;
    }

    public function getForms($pOptions = []): array
    {
        $vIDs = $this->getIDs($pOptions['id'] ?? '');
        $this->refuseExternalIds($vIDs['externals']);

        return $this->formManager->getMany($vIDs['locals']);
    }

    /**
     * Refuse an id naming another wiki, explaining what to do instead (ticket 34).
     *
     * `{{entrylist id="https://other.wiki|4"}}` used to fetch that wiki's entries over HTTP on
     * every page view, through a 1,000-line cache. Content from elsewhere is imported now, so it
     * is *this* wiki's -- searchable, under our ACLs, and there when the source wiki is not.
     *
     * A BadRequestHttpException rather than a bare \Exception on purpose: Performer renders an
     * HttpException as its message alone, so the reader gets this sentence in place of the list
     * and the rest of the page still works. A plain exception would render the same message
     * wrapped in PERFORMABLE_ERROR and a stack dump, and in the API this is already the idiom for
     * "the request asks for something unsupported".
     *
     * @param list<array{url: string, id: string, localFormId?: string}> $externals
     */
    protected function refuseExternalIds(array $externals): void
    {
        if ($externals === []) {
            return;
        }

        $described = implode(', ', array_map(
            static fn (array $external): string => $external['id'] . ' @ ' . $external['url'],
            $externals
        ));

        throw new BadRequestHttpException(_t('BAZ_EXTERNAL_IDS_REMOVED', ['ids' => $described]));
    }

    private function replaceDefaultImage($options, $forms, $entries): array
    {
        $basePath = $this->container->get(AttachedFilePaths::class)->uploadPath();
        $basePath = $basePath . (substr($basePath, -1) != '/' ? '/' : '');
        $formIds = array_keys($forms) ?? [];

        foreach ($formIds as $id) {
            $template = $forms[(int)$id]['template'] ?? [];
            $image_names = array_map(
                function ($fieldObject) {
                    return $fieldObject['name'] ?? '';
                },
                array_filter(
                    $template,
                    function ($fieldObject) {
                        return ($fieldObject['type'] ?? '') == 'image';
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

        $vIDs = $this->getIDs($vSelectedID ?? $pOptions['id'] ?? '');

        $this->refuseExternalIds($vIDs['externals']);
        $vLocalIDs = $vIDs['locals'];

        // Unconditional now. It used to be `count($vLocalIDs) > 0 || count($vExternalIDs) == 0`,
        // which existed only to skip the local search when every id named another wiki.
        $vEntries = $this->container->get(SearchManager::class)->search(
            array_merge($pOptions, ['formsIds' => $vLocalIDs]),
            true, // filter on read ACL,
            true // use Guard
        );

        // filter entries on datefilter parameter
        if (!empty($pOptions['datefilter'])) {
            $vEntries = $this->container->get(EntryController::class)->filterEntriesOnDate($vEntries, $pOptions['datefilter']);
        }

        // Sort entries
        if ($pOptions['random'] ?? false) {
            shuffle($vEntries);
        } else {
            usort($vEntries, $this->buildFieldSorter($pOptions['order'] ?? 'asc', $pOptions['field'] ?? 'title'));
        }

        // Limit entries
        if ($pOptions['nb'] ?? false) {
            $vEntries = array_slice($vEntries, 0, $pOptions['nb']);
        }

        $vEntries = $this->replaceDefaultImage($pOptions, $vForms, $vEntries);

        // add extra informations (comments, reactions, metadatas)
        if (($pOptions['extrafields'] ?? false) === true) {
            foreach ($vEntries as $i => $vEntry) {
                $this->entryExtraFields->setEntryId($vEntry['tag']);
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

        $formIdsUsed = array_unique(array_column($entries, 'form_id'));
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
            } elseif ($propName == 'form_id') {
                // SPECIAL PROPNAME form_id
                $filter['title'] = _t('BAZ_TYPE_FICHE');
                foreach ($formsUsed as $form) {
                    $filter['nodes'][] = $this->createFilterNode($form['id'], $form['label']);
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
                $checkedValues = $this->checkedFacets();
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

    /**
     * The facets a reader has checked, read from the URL.
     *
     * Two spellings, because the boxes are a plain form now (ticket 37): the one a form
     * submits, `?facet[bf_type][]=a&facet[bf_type][]=b`, and the one every link already out
     * there says, `?facet=bf_type=a,b|bf_ville=nantes`.
     *
     * @return array<string, list<string>>
     */
    public function checkedFacets(): array
    {
        $facet = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)
            ->get()->query->all()['facet'] ?? null;

        if (is_array($facet)) {
            $result = [];
            foreach ($facet as $key => $values) {
                // one input per value (`facet[f][]=1&facet[f][]=3`, what the checkboxes
                // write) or one holding the lot (`facet[f]=1,3`, what the tag input writes
                // and what the old url spelling has always said)
                $values = is_array($values) ? $values : explode(',', (string)$values);
                $values = array_values(array_filter(
                    array_map('trim', array_map('strval', $values)),
                    static fn (string $value): bool => $value !== ''
                ));
                if ($values !== []) {
                    $result[(string)$key] = $values;
                }
            }

            return $result;
        }

        if (empty($facet) || !is_string($facet)) {
            return [];
        }
        $result = [];
        foreach (explode('|', $facet) as $field) {
            if (!str_contains($field, '=')) {
                continue;
            }
            [$key, $values] = explode('=', $field, 2);
            $result[$key] = explode(',', trim($values));
        }

        return $result;
    }

    /**
     * The entries the checked facets leave -- OR inside a box, AND between boxes.
     *
     * This used to be the browser's job: `bazar.js` hid the `.bazar-entry` elements whose
     * `data-` attributes did not match. That only ever worked for the templates that draw
     * one, so a card list came with facets that did nothing, and it filtered the page it had
     * rather than the list (facet + pagination showed a page with holes in it).
     *
     * @param array<array-key, array<string, mixed>> $entries
     * @param array<string, list<string>>|null       $checked defaults to what the URL says
     *
     * @return list<array<string, mixed>>
     */
    public function filterEntriesOnFacets(array $entries, ?array $checked = null): array
    {
        $checked ??= $this->checkedFacets();
        if ($checked === []) {
            return array_values($entries);
        }

        return array_values(array_filter(
            $entries,
            function (array $entry) use ($checked): bool {
                foreach ($checked as $propName => $values) {
                    if (array_intersect($values, $this->entryValues($entry, $propName)) === []) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * What an entry holds for a facet's property, as a list.
     *
     * A checkbox field stores its values comma-separated, and a facet over a *linked* entry's
     * field (`field_-_tag_-_prop`) is not a key of the entry at all -- it only exists as one
     * of the data attributes `EntryExtraFieldsService::appendHtmlData()` built.
     *
     * @param array<string, mixed> $entry
     *
     * @return list<string>
     */
    private function entryValues(array $entry, string $propName): array
    {
        $raw = $entry[$propName] ?? null;
        if ($raw === null || is_array($raw)) {
            $found = [];
            preg_match_all(
                '/data-' . preg_quote(strtolower($propName), '/') . '="([^"]*)"/i',
                (string)($entry['html_data'] ?? ''),
                $found
            );
            $raw = implode(',', array_map(
                static fn (string $value): string => html_entity_decode($value, ENT_QUOTES),
                $found[1]
            ));
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string)$raw)),
            static fn (string $value): bool => $value !== ''
        ));
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

        if ($vLocalIDsCount !== 1) {
            $this->refuseExternalIds($vExternalIDs);
        }

        // `isExternal` is kept in the shape, always false: callers destructure this array and an
        // absent key would be a silent null rather than a compile error.
        return ['id' => $vLocalIDs[0], 'key' => $vLocalIDs[0], 'isExternal' => false];
    }

    public function getIDs($pIDs)
    {
        if ($pIDs === null) {
            // just the ids: getAll() would prepare every form in the wiki to read one key
            $vLocalIDs = $this->formManager->getAllIds();
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
                $vFieldMapping = preg_split('/\->?/', $vID);

                if (count($vFieldMapping) > 1) {
                    $vIDs[] = ['url' => $vURL, 'id' => $vFieldMapping[0], 'localFormId' => $vFieldMapping[1]];
                } else {
                    $vIDs[] = ['url' => $vURL, 'id' => $vFieldMapping[0], 'localFormId' => ''];
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
                    throw new \Exception('Invalid local ID');
                }

                array_push($vResults['externals'], $vID);
            }
        }

        return $vResults;
    }

    private function buildFieldSorter($order, $sortField): callable
    {
        // stored wiki content still says {{entrylist champ="date_creation_fiche"}} or
        // champ="bf_titre" -- legacy entry-key names are aliased to the renamed ones
        // (ADR-0010; bf_titre maps to the computed `title`)
        $sortField = EntryManager::LEGACY_ENTRY_KEYS[$sortField] ?? $sortField;

        return function ($a, $b) use ($order, $sortField) {
            if (strstr($sortField, '.')) {
                $val1 = $this->getValueForArray($a, $sortField);
                $val2 = $this->getValueForArray($b, $sortField);
            } else {
                $val1 = $a[$sortField] ?? '';
                $val2 = $b[$sortField] ?? '';
            }
            if ($order == 'desc') {
                return strnatcmp(
                    $this->sanitizeStringForCompare($val2),
                    $this->sanitizeStringForCompare($val1),
                );
            }

            return strnatcmp(
                $this->sanitizeStringForCompare($val1),
                $this->sanitizeStringForCompare($val2),
            );
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

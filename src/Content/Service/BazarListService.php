<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\EnumField;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Search\Service\SearchManager;

class BazarListService
{
    protected EntryManager $entryManager;
    protected EntryExtraFieldsService $entryExtraFields;
    protected FormManager $formManager;
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

    /**
     * @param array<string, mixed> $pOptions
     *
     * @return array<int|string, mixed> the forms the options name, by id
     */
    public function getForms($pOptions = []): array
    {
        $vIDs = $this->getIDs($pOptions['id'] ?? '');
        $this->refuseExternalIds($vIDs['externals']);

        return $this->formManager->getMany($vIDs['locals']);
    }

    /**
     * Refuse an id naming another wiki, explaining what to do instead (ticket 34).
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

    /**
     * @param array<string, mixed>                   $options
     * @param array<int|string, mixed>               $forms
     * @param array<array-key, array<string, mixed>> $entries
     *
     * @return array<array-key, array<string, mixed>>
     */
    private function replaceDefaultImage($options, $forms, $entries): array
    {
        $basePath = $this->container->get(AttachedFilePaths::class)->uploadPath();
        $basePath = $basePath . (substr($basePath, -1) != '/' ? '/' : '');
        $formIds = array_keys($forms);

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
                if ($this->container->get(Storage::class)->exists($basePath . $default_image_filename)) {
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

    /**
     * @param array<string, mixed>          $pOptions
     * @param array<int|string, mixed>|null $pForms   the forms already loaded, or null to load them from the options
     *
     * @return array<array-key, array<string, mixed>>
     */
    public function getEntries($pOptions, $pForms = null): array
    {
        $pOptions['queries'] = $pOptions['queries'] ?? $pOptions['query'] ?? null;

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

        $vEntries = $this->container->get(SearchManager::class)->search(
            array_merge($pOptions, ['formsIds' => $vLocalIDs]),
            true,
            true
        );

        if (!empty($pOptions['datefilter'])) {
            $vEntries = $this->container->get(EntryController::class)->filterEntriesOnDate($vEntries, $pOptions['datefilter']);
        }

        if ($pOptions['random'] ?? false) {
            shuffle($vEntries);
        } else {
            usort($vEntries, $this->buildFieldSorter($pOptions['order'] ?? 'asc', $pOptions['field'] ?? 'title'));
        }

        if ($pOptions['nb'] ?? false) {
            $vEntries = array_slice($vEntries, 0, $pOptions['nb']);
        }

        $vEntries = $this->replaceDefaultImage($pOptions, $vForms, $vEntries);

        if (($pOptions['extrafields'] ?? false) === true) {
            foreach ($vEntries as $i => $vEntry) {
                $this->entryExtraFields->setEntryId($vEntry['tag']);
                foreach (EntryExtraFieldsService::EXTRA_FIELDS as $vField) {
                    $vEntries[$i][$vField] = $this->entryExtraFields->get($vField);
                }

                if (!empty($vEntries[$i]['linked_data'])) {
                    $vEntries[$i]['html_data'] .= $this->entryExtraFields->appendHtmlData($vEntries[$i]['linked_data']);
                }
            }
        }

        return $vEntries;
    }

    /**
     * @param array<string, mixed>                   $options
     * @param array<array-key, array<string, mixed>> $entries
     * @param array<int|string, mixed>               $forms
     * @param bool                                   $withIdIndexes key the filters by property name rather than by position
     *
     * @return array<array-key, array<string, mixed>>
     */
    public function getFilters($options, $entries, $forms, $withIdIndexes = false): array
    {
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
            $filter = [
                'propName' => $propName,
                'title' => '',
                'icon' => '',
                'nodes' => [],
                'collapsed' => true,
            ];

            if (str_contains($propName, $linkedSep)) {
                $field = $propName;
            } else {
                foreach ($allFields as $aField) {
                    if ($aField->getPropertyName() == $propName) {
                        $field = $aField;
                        break;
                    }
                }
            }

            if (!empty($field) && $field instanceof EnumField) {
                $filter['title'] = $field->getLabel();

                if (!empty($field->getOptionsTree()) && $options['dynamic'] == true) {
                    foreach ($field->getOptionsTree() as $node) {
                        $filter['nodes'][] = $this->recursivelyCreateNode($node);
                    }
                } else {
                    foreach ($field->getOptions() as $value => $label) {
                        $filter['nodes'][] = $this->createFilterNode($value, $label);
                    }
                }
            } elseif ($propName == 'form_id') {
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
                    if ($linkedField instanceof EnumField) {
                        $linkedFormId = $linkedField->getLinkedObjectName();
                        $finalField = $this->formManager->findFieldWithId([$linkedFormId], $idLinkedData[1]);
                        if (!empty($finalField)) {
                            $filter['title'] = $finalField->getLabel();
                            if ($finalField instanceof EnumField) {
                                if (!empty($finalField->getOptionsTree()) && $options['dynamic'] == true) {
                                    foreach ($finalField->getOptionsTree() as $node) {
                                        $filter['nodes'][$node['value']] = $this->recursivelyCreateNode($node);
                                    }
                                } else {
                                    foreach ($finalField->getOptions() as $value => $label) {
                                        $filter['nodes'][$value] = $this->createFilterNode($value, $label);
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $foundField = $this->formManager->findFieldWithId($formIdsUsed, $propName);
                if (!empty($foundField)) {
                    $filter['title'] = $foundField->getLabel();
                } else {
                    $filter['title'] = $propName == 'owner' ? _t('BAZ_CREATOR') : $propName;
                }

                $uniqValues = array_unique(array_column($entries, $propName));

                usort($uniqValues, function ($a, $b) {
                    return strcmp($this->sortKey($a), $this->sortKey($b));
                });

                foreach ($uniqValues as $value) {
                    $filter['nodes'][] = $this->createFilterNode($value, $value);
                }
            }

            if (!empty($options['groupicons'][$index])) {
                $filter['icon'] = '<i class="' . $options['groupicons'][$index] . '"></i> ';
            }

            if (!empty($options['titles'][$index])) {
                $filter['title'] = $options['titles'][$index];
            }

            $filter['collapsed'] = ($index != 0) && !$options['groupsexpanded'];

            if ($options['dynamic'] == false) {
                $checkedValues = $this->checkedFacets();

                $entriesValues = array_column($entries, $propName);

                $entriesValues = array_map(function ($val) {
                    return explode(',', $val ?? '');
                }, $entriesValues);

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
     * @return array<string, list<string>>
     */
    public function checkedFacets(): array
    {
        $facet = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)
            ->get()->query->all()['facet'] ?? null;

        if (is_array($facet)) {
            $result = [];
            foreach ($facet as $key => $values) {
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

    /** The transliterated, lower-cased form a facet value sorts on. */
    private function sortKey(string $value): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function createFilterNode(mixed $value, mixed $label)
    {
        return [
            'value' => htmlspecialchars((string)$value),
            'label' => $label,
            'children' => [],
        ];
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function recursivelyCreateNode($node)
    {
        $result = $this->createFilterNode($node['id'], $node['label']);
        foreach ($node['children'] as $childNode) {
            $result['children'][] = $this->recursivelyCreateNode($childNode);
        }

        return $result;
    }

    /**
     * @param array<string, mixed>        $node
     * @param string                      $propName
     * @param array<array-key, int>       $countedValues how many entries hold each value
     * @param array<string, list<string>> $checkedValues the facets the reader has checked
     *
     * @return array<string, mixed>
     */
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

    /**
     * @param string|null $key a key, or a dotted path into nested arrays
     */
    private function getValueForArray(mixed $array, $key, mixed $default = null): mixed
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

    /**
     * @param mixed $pIDs            a form id, a list of them, or the `locals`/`externals` shape parseIDs() returns
     * @param bool  $pThrowException
     *
     * @return array<string, mixed>|null null when the ids do not name exactly one local form
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

        return ['id' => $vLocalIDs[0], 'key' => $vLocalIDs[0], 'isExternal' => false];
    }

    /**
     * @param mixed $pIDs a form id, a list of them, or the `locals`/`externals` shape parseIDs() returns
     *
     * @return array<string, mixed> `locals` and `externals`, each a list
     */
    public function getIDs($pIDs)
    {
        if ($pIDs === null) {
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

    /** A form id the parser accepts: the decimal string of a non-negative integer. */
    public function isValidID(mixed $pID): bool
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

    protected function isValidURL(mixed $pURL): bool
    {
        return true;
    }

    /**
     * @param mixed $pIDs a form id, a list of them, or the `locals`/`externals` shape this returns
     *
     * @return array<string, mixed> `locals` and `externals`, each a list
     */
    protected function parseIDs($pIDs)
    {
        if (is_array($pIDs)) {
            if (isset($pIDs['locals'])) {
                return $pIDs;
            }
            $pIDs = implode(',', $pIDs);
        }

        $pIDs = preg_replace('/[^,\s]*\s*\|(?:\s*(?:\([\s,0-9\->]*\))|(?:[0-9\->]*))/', '"\\0"', strip_tags((string)$pIDs)) ?? '';

        $vLines = str_getcsv($pIDs, ',', '"', '');

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

            $vPostFix = preg_replace('/[\s()]*/', '', $vPostFix) ?? '';

            $vPostFix = explode(',', $vPostFix);

            foreach ($vPostFix as $vID) {
                $vFieldMapping = preg_split('/\->?/', $vID);

                $vFieldMapping = $vFieldMapping === false ? [] : $vFieldMapping;
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
                if (trim($vID['localFormId']) != '' && !$this->isValidID($vID['localFormId'])) {
                    throw new \Exception('Invalid local ID');
                }

                array_push($vResults['externals'], $vID);
            }
        }

        return $vResults;
    }

    /**
     * @param mixed $order     'desc' to reverse, anything else to sort ascending
     * @param mixed $sortField the entry key to sort on, possibly a dotted path
     */
    private function buildFieldSorter($order, $sortField): callable
    {
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

    private function sanitizeStringForCompare(mixed $value): string
    {
        if ($value === null) {
            $value = '';
        }
        $value = is_scalar($value)
            ? strval($value)
            : (string)json_encode($value);

        return strtoupper(StringUtilService::withoutDiacritics($value));
    }
}

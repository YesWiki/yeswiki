<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class SearchManager
{
    protected $wiki;
    protected $dbService;
    protected $aclService;

    public const MISSING_PROPERTY = '_MISSING_PROPERTY_';
    public const MISSING_FIELD = '_MISSING_FIELD_';
    public const ENTRY_METADATA_FIELDS = ['id_fiche', 'id_typeannonce', 'date_creation_fiche', 'date_maj_fiche', 'statut_fiche', 'url'];

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        AclService $aclService,
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->aclService = $aclService;
    }

    /**
     * prepare searches.
     *
     * @param array $forms (needed to filter only on concerned forms)
     *
     * @return array ['needle 1'=>[],
     *               'needle 2'=>[$result1,$result2]
     *               ,...]  // each $result= [
     *               'propertyName' => 'bf_...',
     *               'key' => 'bf_...',
     *               'isCheckBox' => true,
     *               ]
     */
    public function searchWithLists(string $phrase, array $forms = []): array
    {
        $needles = [];
        if (!empty($phrase) && preg_match_all('/^([^" ]+)|(?:")([^"]+)(?:")|([^" ]+)$|(?: )([^" ]+)(?: )/', $phrase, $matches)) {
            foreach ($matches[0] as $key => $match) {
                for ($i = 1; $i < 5; $i++) {
                    if (!empty($matches[$i][$key])) {
                        if (!array_key_exists($matches[$i][$key], $needles)) {
                            $needle = $this->prepareNeedleForRegexp($matches[$i][$key]);
                            $needles[$needle] = [];
                        }
                    }
                }
            }

            foreach ($forms as $form) {
                foreach ($this->searchInFormOptions($needles, $form) as $result) {
                    $needle = $result['needle'];
                    if (array_key_exists($needle, $needles)) {
                        array_push($needles[$needle], $result);
                    } else {
                        $needles[$needle] = [$result];
                    }
                }
            }
        }

        return $needles;
    }

    /**
     * search needles in values (options) of EnumField and return array [['propertyName' => ...,'key'=>$key,'isCheckbox' => true],].
     */
    private function searchInFormOptions(array $needles, array $form): array
    {
        $results = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof EnumField) {
                $options = $field->getOptions();
                if (is_array($options)) {
                    foreach ($options as $key => $option) {
                        foreach ($needles as $needle => $values) {
                            if (is_array($option)) {
                                $option = implode(' ', $option);
                            }
                            if (preg_match('/' . mb_strtolower(preg_quote($needle)) . '/i', mb_strtolower($option), $matches)) {
                                $results[] = [
                                    'propertyName' => $field->getPropertyName(),
                                    'key' => $key,
                                    'isCheckBox' => ($field instanceof CheckboxField),
                                    'needle' => $needle,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * prepare needle by removing accents and define string for regexp.
     */
    private function prepareNeedleForRegexp(string $needle): string
    {
        $needle = str_replace(['(', ')', '/'], ['\\(', '\\)', '\\/'], $needle);

        $needle = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'],
            ['a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y'],
            $needle,
        );

        $needle = str_replace(
            [
                'a',
                'c',
                'e',
                'i',
                'n',
                'o',
                'u',
                'y',
            ],
            [
                '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)',
                '(c|ç|C|Ç)',
                '(e|è|é|ê|ë|E|È|É|Ê|Ë)',
                '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)',
                '(n|ñ|N|Ñ)',
                '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)',
                '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)',
                '(y|ý|ÿ|Y|Ý)',
            ],
            $needle,
        );

        return $needle;
    }

    /**
     * Build the SQL fields conditions for keywords.
     *
     *  @param pKeywords <string> : the keywords search string in the format :
     *      <keywords>       = ( <token> | <exluded token> )+ [ "|" <keywords> ]
     *      <token>          = <string without space>	|
     *				           "'" <string with spaces between single quotes> "'" |
     *				           '"' <string with spaces between double quotes> '"'
     *      <excluded token> = "-" <token>
     *
     * 	 example : toto -"tata tutu" | "titi tutu" tete -tyty
     *				=
     *            "toto" AND ("titi tutu" OR "tete") AND NOT "tata tutu" AND NOT "tyty"
     *
     *   NOTE : position of excluded fields has no signification
     *  @param pSearchFields <array> of <fields>
     *				   <fields> = <array> of properties
     *		: fields descriptions (structures, etc...)
     *
     * @return <string> : fields conditions for keywords
     */
    public function buildKeywordsConditions($pKeywords, $pSearchFields, $pMinKeywordsLength)
    {
        $vParsedKeywords = $this->parseKeywords($pKeywords, $pMinKeywordsLength);

        if ((count($vParsedKeywords['CNF']) == 0 && count($vParsedKeywords['excludeds']) == 0) || count($pSearchFields) == 0) {
            return '';
        }

        $vANDs = [];

        foreach ($vParsedKeywords['CNF'] as $vAND) {
            $vORs = [];

            foreach ($vAND as $vOR) {
                $vIsRegExp = $this->isRegExp($vOR);

                foreach ($pSearchFields as $vFieldName => $vField) {
                    foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                        $vORRequest = '';

                        switch ($vFieldDescriptor['_mode_']) {
                            case 'single':
                                if ($vIsRegExp) {
                                    $vORRequest = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' REGEXP \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vOR)) . '\'';
                                } else {
                                    $vORRequest = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vOR) . '%\'';
                                }

                                break;

                            case 'multiple':
                                if ($vIsRegExp) {
                                    $vORRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE ' . $this->dbService->getCollation() . ' REGEXP \'^' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vOR)) . '$\')';
                                } else {
                                    $vORRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE ' . $this->dbService->getCollation() . ' LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vOR) . '%\')';
                                }

                                break;
                        }

                        if ($vField['hasMultipleStructures']) {
                            if ($vORRequest != '') {
                                $vORRequest = '( ' . $this->column('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) {
                                    return '\'' . $pFormID . '\'';
                                }, $vFieldDescriptor['_ids_'])) . ') AND ' . $vORRequest . ')';
                            }
                        }

                        if ($vORRequest != '') {
                            $vORs[] = $vORRequest;
                        }
                    }
                }
            }

            if (count($vORs) > 0) {
                $vANDs[] = '(' . implode(' OR ', $vORs) . ')';
            }
        }

        foreach ($vParsedKeywords['excludeds'] as $vExcluded) {
            $vIsRegExp = $this->isRegExp($vExcluded);

            foreach ($pSearchFields as $vFieldName => $vField) {
                $vExcludedRequest = '';

                foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                    switch ($vFieldDescriptor['_mode_']) {
                        case 'single':
                            if ($vIsRegExp) {
                                $vExcludedRequest = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' NOT REGEXP \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vExcluded)) . '\'';
                            } else {
                                $vExcludedRequest = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' NOT LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vExcluded) . '%\'';
                            }

                            break;

                        case 'multiple':
                            if ($vIsRegExp) {
                                $vExcludedRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE ' . $this->dbService->getCollation() . ' NOT REGEXP \'^' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vExcluded)) . '$\')';
                            } else {
                                $vExcludedRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE ' . $this->dbService->getCollation() . ' NOT LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vExcluded) . '%\')';
                            }

                            break;
                    }

                    if ($vField['hasMultipleStructures']) {
                        if ($vExcludedRequest != '') {
                            $vExcludedRequest = '( ' . $this->column('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) {
                                return '\'' . $pFormID . '\'';
                            }, $vFieldDescriptor['_ids_'])) . ') AND ' . $vExcludedRequest . ')';
                        }
                    }

                    if ($vExcludedRequest != '') {
                        $vANDs[] = $vExcludedRequest;
                    }
                }
            }
        }

        return implode(
            ' AND ',
            array_unique($vANDs),
        );
    }

    /**
     * Build the SQL fields conditions for queries.
     *
     * @param $pQueries : <array> of <query>
     *                  <query> = [ "name" => <string>, "operator" => <string>, "values" => <array of strings> ]
     *
     * @return = <string> fields conditions for queries
     */
    public function buildQueriesConditions($pQueries, $pFields)
    {
        $vQueriesConditions = [];

        foreach ($pQueries as $vQuery) {
            $vFieldName = $vQuery['name'];

            $vOperator = $vQuery['operator'];

            $vField = $pFields[$vFieldName];

            $vQueryConditions = [];

            switch ($vOperator) {
                case '==':
                    $vRegExpOperator = 'REGEXP';
                    $vComparisonOperator = '=';
                    $vFindInSetOperator = 'FIND_IN_SET';
                    break;
                case '!=':
                    $vRegExpOperator = 'NOT REGEXP';
                    $vComparisonOperator = '!=';
                    $vFindInSetOperator = 'NOT FIND_IN_SET';
                    break;
                case '<':
                    $vRegExpOperator = 'REGEXP';
                    $vComparisonOperator = '<';
                    $vFindInSetOperator = 'FIND_IN_SET';
                    break;
                case '>':
                    $vRegExpOperator = 'REGEXP';
                    $vComparisonOperator = '>';
                    $vFindInSetOperator = 'FIND_IN_SET';
                    break;
                case '<=':
                    $vRegExpOperator = 'REGEXP';
                    $vComparisonOperator = '<=';
                    $vFindInSetOperator = 'FIND_IN_SET';
                    break;
                case '>=':
                    $vRegExpOperator = 'REGEXP';
                    $vComparisonOperator = '>=';
                    $vFindInSetOperator = 'FIND_IN_SET';
                    break;
                default:
                    throw new Exception($vOperator . ' is not recognized');

                    return [];
            }

            foreach ($vField['descriptors'] as $vHash => $vDescriptor) {
                $vValueConditions = [];

                foreach ($vQuery['values'] as $vValue) {
                    $vIsRegExp = $this->isRegExp($vValue);

                    switch ($vDescriptor['_mode_']) {
                        case 'single':
                            if ($vIsRegExp) {
                                $vValueConditions[] = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' ' . $vRegExpOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vValue)) . '\'';
                            } else {
                                if ($vDescriptor['_type_'] == 'number') {
                                    if (isset($vValue) && trim($vValue) !== '') {
                                        if (!is_numeric(trim($vValue)) || !is_finite((float)trim($vValue))) {
                                            $vValueConditions[] = 'FALSE';
                                        } else {
                                            $vValueConditions[] = 'CAST(' . $this->column($vFieldName) . ' AS DOUBLE) ' . $vComparisonOperator . ' ' . (float)trim($vValue);
                                        }
                                    } else {
                                        $vValueConditions[] = '(' . $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' ' . $vComparisonOperator . ' \'\' )';
                                    }
                                } else {
                                    $vValueConditions[] = $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ' ' . $vComparisonOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $vValue) . '\'';
                                }
                            }

                            break;

                        case 'multiple':
                            if ($vIsRegExp) {
                                $vValueConditions[] = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE ' . $this->dbService->getCollation() . ' ' . $vRegExpOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vValue)) . '\')';
                            } else {
                                $vValueConditions[] = $vFindInSetOperator . ' (\'' . mysqli_real_escape_string($this->wiki->dblink, $vValue) . '\' COLLATE ' . $this->dbService->getCollation() . ', ' . $this->column($vFieldName) . ' COLLATE ' . $this->dbService->getCollation() . ')';
                            }

                            break;

                        case self::MISSING_FIELD:
                        case self::MISSING_PROPERTY:
                            $vValueConditions[] = ($vComparisonOperator === '!=') ? 'TRUE' : 'FALSE';

                            break;
                    }
                }

                $vDescriptorCondition = '';

                if (count($vValueConditions) > 0) {
                    $vDescriptorCondition = implode($vComparisonOperator === '!=' ? ' AND ' : ' OR ', $vValueConditions);

                    if ($vField['hasMultipleStructures']) {
                        if ($vDescriptorCondition != '') {
                            $vDescriptorCondition = $this->column('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) {
                                return '\'' . $pFormID . '\'';
                            }, $vDescriptor['_ids_'])) . ') AND (' . $vDescriptorCondition . ')';
                        }
                    }
                }

                if ($vDescriptorCondition != '') {
                    $vQueryConditions[] = '(' . $vDescriptorCondition . ')';
                }
            }

            if (count($vQueryConditions) > 0) {
                $vQueriesConditions[] = '(' . implode(' OR ', $vQueryConditions) . ')';
            }
        }

        return implode(' AND ', $vQueriesConditions);
    }

    /**
     * Return the request for searching entries in database.
     *
     * @param array &$params
     *
     * @return $string
     */
    public function prepareSearchRequest(&$params = [], bool $filterOnReadACL = false, bool $applyOnAllRevisions = false): string
    {
        $params = array_merge(
            [
                'queries' => [],
                'formsIds' => [],
                'user' => '',
                'minDate' => '',
                'correspondance' => '',
            ],
            $params,
        );

        $vKeywords = $params['keywords'] ?? '';

        $vQueries = $this->parseQuery($params['queries']);

        foreach ($vQueries as $vQuery) {
            if (!$this->isFieldName($vQuery['name'] ?? null)) {
                return '';
            }
        }

        $vIDsRequest = '';

        if (!empty($params['formsIds'])) {
            $vFormIDs = $params['formsIds'];

            if (!is_array($vFormIDs)) {
                $vFormIDs = [$vFormIDs];
            }

            $vFormIDs = array_map(
                function ($vID) {
                    $vType = \gettype($vID);

                    if ($vType == 'integer') {
                        return $vID;
                    }

                    if ($vType == 'string') {
                        $vTrimmed = trim($vID);
                        $vIntValue = intval($vID);

                        if (strval($vID) == strval($vIntValue)) {
                            return $vIntValue;
                        }

                        return null;
                    }

                    return null;
                },
                $vFormIDs,
            );

            $vFormIDs = array_filter(
                $vFormIDs,
                function ($pID) {
                    return $pID !== null;
                },
            );

            $vIDsRequest .= 'JSON_UNQUOTE(JSON_EXTRACT(body, \'$.id_typeannonce\')) IN (' . join(',', array_map(function ($pFormID) {
                return '\'' . $pFormID . '\'';
            }, $vFormIDs)) . ')';
        } else {
            $vFormIDs = [];
        }

        $vPeriodRequest = '';

        if (!empty($params['minDate'])) {
            $vPeriodRequest .= 'time >= "' . mysqli_real_escape_string($this->wiki->dblink, $params['minDate']) . '"';
        }

        $vUserRequest = '';

        if (!empty($params['user'])) {
            $vUserRequest .= 'owner = _utf8\'' . mysqli_real_escape_string($this->wiki->dblink, $params['user']) . '\'';
        }

        $vKeywordsFields = [];
        $vQueriesFields = [];

        if ($vKeywords != '') {
            $vSearchFields = isset($params['searchfields'])
                                ? is_array($params['searchfields'])
                                    ? $params['searchfields']
                                    : explode(',', $params['searchfields'])
                                : [];

            $vSearchFields[] = 'bf_titre';

            $vKeywordsFields = array_unique(array_filter(array_map('trim', $vSearchFields), [$this, 'isFieldName']));
        }

        foreach ($vQueries as $vQuery) {
            $vQueriesFields[] = $vQuery['name'];
        }

        $vNecessaryFields = array_unique(array_merge($vKeywordsFields, $vQueriesFields));

        $vFields = [];

        $vFieldDescriptor = ['_mode_' => 'single', '_type_' => 'string'];

        $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

        foreach (self::ENTRY_METADATA_FIELDS as $vMetadataField) {
            if ($vMetadataField !== 'id_fiche' && !in_array($vMetadataField, $vNecessaryFields, true)) {
                continue;
            }
            $vFields[$vMetadataField]
            = [
                'needSplit' => false,
                'hasMultipleStructures' => false,
                'isExtracted' => $vMetadataField === 'id_typeannonce',
                'isSplitted' => false,
                'descriptors' => [$vHash => array_merge($vFieldDescriptor, ['_ids_' => $vFormIDs])],
            ];
        }

        $vFormManager = $this->wiki->services->get(FormManager::class);

        $vForms = $vFormManager->getMany($vFormIDs);

        foreach ($vNecessaryFields as $vField) {
            if (isset($vFields[$vField])) {
                continue;
            }

            if (!isset($vFields[$vField]['descriptors'])) {
                $vFields[$vField]['descriptors'] = [];
            }
            if (!isset($vFields[$vField]['needSplit'])) {
                $vFields[$vField]['needSplit'] = false;
            }

            foreach ($vForms as $vFormID => $vForm) {
                $vPropertyFound = false;
                if (!isset($vForm['prepared'])) {
                    continue;
                }
                foreach ($vForm['prepared'] as $vFieldObject) {
                    $vJSONPath = explode('.', $vField);

                    $vPropertyName = $vJSONPath[0] ?? '';

                    if ($vFieldObject->getPropertyName() == $vPropertyName) {
                        $vPropertyFound = true;

                        $vStructure = $vFieldObject->getValueStructure();

                        $vCurrentArray = $vStructure;

                        $vFieldFound = true;

                        foreach ($vJSONPath as $vJSONPathSegment) {
                            if (is_array($vCurrentArray) && array_key_exists($vJSONPathSegment, $vCurrentArray)) {
                                $vCurrentArray = $vCurrentArray[$vJSONPathSegment];
                            } else {
                                $vFieldFound = false;
                            }
                        }

                        if ($vFieldFound) {
                            $vFieldDescriptor = $vCurrentArray;
                        } else {
                            $vFieldDescriptor = ['_mode_' => self::MISSING_FIELD, '_type_' => self::MISSING_FIELD];
                        }

                        $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

                        if (isset($vFields[$vField]['descriptors'][$vHash])) {
                            $vFields[$vField]['descriptors'][$vHash]['_ids_'][] = $vFormID;
                        } else {
                            $vFields[$vField]['descriptors'][$vHash] = ['_mode_' => $vFieldDescriptor['_mode_'], '_type_' => $vFieldDescriptor['_type_'], '_ids_' => [$vFormID]];
                        }

                        if ($vFieldDescriptor['_mode_'] == 'multiple') {
                            $vFields[$vField]['needSplit'] = true;
                        }

                        break;
                    }
                }

                if (!$vPropertyFound) {
                    $vFieldDescriptor = ['_mode_' => self::MISSING_PROPERTY, '_type_' => self::MISSING_PROPERTY];

                    $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

                    if (isset($vFields[$vField]['descriptors'][$vHash])) {
                        $vFields[$vField]['descriptors'][$vHash]['_ids_'][] = $vFormID;
                    } else {
                        $vFields[$vField]['descriptors'][$vHash] = ['_mode_' => $vFieldDescriptor['_mode_'], '_type_' => $vFieldDescriptor['_type_'], '_ids_' => [$vFormID]];
                    }
                }
            }

            $vFields[$vField]['hasMultipleStructures'] = count(array_keys($vFields[$vField]['descriptors'])) > 1;

            $vFields[$vField]['isExtracted'] = false;

            $vFields[$vField]['isSplitted'] = false;
        }

        $vSelectRequest
        = [
            'p.*',
            'JSON_UNQUOTE(JSON_EXTRACT(body, \'$.id_typeannonce\')) AS ' . $this->column('id_typeannonce'),
        ];

        foreach ($vFields as $vFieldName => $vField) {
            if (!$vField['isExtracted']) {
                $vSelectRequest[] = 'JSON_UNQUOTE(JSON_EXTRACT(body, \'' . mysqli_real_escape_string($this->wiki->dblink, $this->jsonPath($vFieldName)) . '\')) AS ' . $this->column($vFieldName);

                $vField['isExtracted'] = true;
            }
        }

        $vSelectRequest = implode(', ', $vSelectRequest);

        $vSplitteds = [];
        $vSplittedsRequest = '';

        foreach ($vFields as $vFieldName => $vField) {
            if (!$vField['needSplit'] || $vField['isSplitted']) {
                continue;
            }

            $vSplitteds[] = 'SELECT id, champ, elt FROM ' . $this->column($vFieldName, '_multiple');

            $vSplittedsRequest
                        .= ', ' . $this->column($vFieldName, '_multiple') . ' AS '
                        . '( '
                            . 'SELECT '
                                . 'id, '
                                . '\'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AS champ, '
                                . 'TRIM(SUBSTRING_INDEX(' . $this->column($vFieldName) . ', \',\', 1)) AS elt, '
                                . 'CASE '
                                    . 'WHEN INSTR(' . $this->column($vFieldName) . ', \',\') = 0 THEN \'\' '
                                    . 'ELSE SUBSTR(' . $this->column($vFieldName) . ', INSTR(' . $this->column($vFieldName) . ', \',\') + 1) '
                                . 'END AS rest '
                            . 'FROM filteredPages '
                            . 'UNION ALL '
                            . 'SELECT '
                                . 'id, '
                                . 'champ, '
                                . 'TRIM(SUBSTRING_INDEX(rest, \',\', 1)) AS elt, '
                                . 'CASE '
                                    . 'WHEN INSTR(rest, \',\') = 0 THEN \'\' '
                                    . 'ELSE SUBSTR(rest, INSTR(rest, \',\') + 1) '
                                . 'END AS rest '
                            . 'FROM ' . $this->column($vFieldName, '_multiple') . ' '
                            . 'WHERE rest <> \'\''
                        . ')';

            $vField['isSplitted'] = true;
        }

        $vSplittedsCount = count($vSplitteds);

        if ($vSplittedsCount > 0) {
            $vSplittedsRequest
                        .= ', all_multiples AS '
                        . '( '
                            . implode(' UNION ALL ', $vSplitteds)
                        . ') ';
        }

        $vWhereRequest = '';

        $vMinSearchKeywordLength = $this->getMinSearchKeywordLength();

        $vKeywordsConditions = $this->buildKeywordsConditions(
            $vKeywords,
            array_filter(
                $vFields,
                function ($vFieldName) use ($vKeywordsFields) {
                    return in_array($vFieldName, $vKeywordsFields);
                },
                ARRAY_FILTER_USE_KEY,
            ),
            $vMinSearchKeywordLength,
        );

        $vWhereRequest .= $vKeywordsConditions;

        $vQueriesConditions = trim($this->buildQueriesConditions($vQueries, $vFields));

        if (str_contains($vQueriesConditions, '((FALSE))')) {
            return '';
        }

        if ($vQueriesConditions != '') {
            $vWhereRequest .= ($vWhereRequest != '' ? ' AND ' : '') . $vQueriesConditions;
        }

        if (!$this->wiki->UserIsAdmin() && $filterOnReadACL) {
            $vWhereRequest .= ($vWhereRequest != '' ? ' AND ' : '') . $this->aclService->updateRequestWithACL();
        }

        $vCompleteRequest = 'WITH RECURSIVE '
                                . 'filteredPages AS '
                                . '( '
                                    . 'SELECT '
                                        . $vSelectRequest . ' '
                                    . 'FROM ' . $this->dbService->prefixTable('pages') . ' p '
                                    . 'JOIN ' . $this->dbService->prefixTable('triples') . ' t ON '
                                        . 't.resource = p.tag AND '
                                        . 't.value = \'' . $this->wiki->services->get(EntryManager::class)::TRIPLES_ENTRY_ID . '\' AND '
                                        . 't.property = \'http://outils-reseaux.org/_vocabulary/type\' '
                                    . 'WHERE '
                                        . ($applyOnAllRevisions ? '' : 'latest=\'Y\' AND ')
                                        . 'p.comment_on = \'\''
                                        . ($vUserRequest !== '' ? ' AND ' . $vUserRequest : '')
                                        . ($vPeriodRequest !== '' ? ' AND ' . $vPeriodRequest : '')
                                        . ($vIDsRequest !== '' ? ' AND ' . $vIDsRequest : '')
                                . ')'
                                . ($vSplittedsRequest != '' ? $vSplittedsRequest . ' ' : ' ')
                                . 'SELECT DISTINCT f.* '
                                . 'FROM filteredPages f '
                                . ($vSplittedsCount > 0 ? 'LEFT JOIN all_multiples s ON s.id = f.id ' : '')
                                . ($vWhereRequest != '' ? 'WHERE ' . $vWhereRequest : '');

        if (isset($_GET['showreq'])) {
            echo '<hr><code style="width:100%;height:100px;">' . $vCompleteRequest . '</code><hr>';
        }

        return $vCompleteRequest;
    }

    /**
     * Return an array of fiches based on search parameters.
     *
     * @param array $params
     *
     * @return mixed
     */
    public function search($params = [], bool $filterOnReadACL = false, bool $useGuard = false): array
    {
        $requete = $this->prepareSearchRequest($params, $filterOnReadACL);

        $searchResults = [];
        if ($requete === '') {
            return $searchResults;
        }
        $results = $this->dbService->loadAll($requete);
        $debug = ($this->wiki->GetConfigValue('debug') == 'yes');

        $vPageManager = $this->wiki->services->get(PageManager::class);
        $vEntryManager = $this->wiki->services->get(EntryManager::class);

        foreach ($results as $page) {
            $vPageManager->cacheOwner($page);
            $filteredPage = (!$this->wiki->UserIsAdmin() && $useGuard)
                ? $this->wiki->services->get(Guard::class)->checkAcls($page, $page['tag'])
                : $page;
            $data = $vEntryManager->getDataFromPage($filteredPage, false, $debug, $params['correspondance'] ?? '');
            $data['-is-external-'] = '0';
            $searchResults[$data['id_fiche']] = $data;
        }

        return $searchResults;
    }

    /**
     * Parse a keywords search string
     * Keywords search string are composed of tokens
     * Tokens can be single words (without space) or expression composed of several words seperated by spaces enclosed in quote or double quote.
     * Tokens may be separated by |
     * | stands for logical AND
     * A token may be prefixed with - to exclude the results containing the token
     * The position of excluded tokens is not relevant
     * Ex : cat "my dog" -parrot | bulldog "small bird" -"cocker spaniel"
     *    will match result that contain ("cat" or "my dog") and ("bulldog" or "small bird)
     *    excluding results containing "parrot" or "cocker spaniel".
     *
     * @param pKeywords <string> : the keywords search string
     *
     * @return <array> : an associative array containing the keys :
     * 	- CNF =	the Conjonctive Normal Form (= [a OR b] AND [d or e]) of the keywords search string
     *			(ie : an AND-array of OR-arrays)
     *	- excludeds = <array> an array of excluded tokens
     */
    private function parseKeywords($pKeywords, $pMinKeywordLength = null)
    {
        if ($pMinKeywordLength == null) {
            $vMinKeywordLength = $this->getMinSearchKeywordLength();
        } else {
            $vMinKeywordLength = $pMinKeywordLength;
        }

        $vResults = ['CNF' => [], 'excludeds' => []];

        if (!(is_string($pKeywords) && trim($pKeywords) != '' && $pKeywords != _t('BAZ_MOT_CLE'))) {
            return $vResults;
        }

        $vANDs = array_filter(array_unique(array_map('trim', explode('|', $pKeywords))), function ($pKeyword) use ($vMinKeywordLength) {
            return strlen($pKeyword) >= $vMinKeywordLength;
        });

        foreach ($vANDs as $vAND) {
            preg_match_all(
                '/(-)?("(?:\\\\.|[^"\\\\])*"|'
                . '\'(?:\\\\.|[^\'\\\\])*\'|'
                . '\S+)/u',
                $vAND,
                $vTokens,
                PREG_SET_ORDER,
            );

            $vORs = [];

            foreach ($vTokens as $vToken) {
                if ($vToken[1] == '-') {
                    $vResults['excludeds'][] = trim($vToken[2], '"\'');
                } else {
                    $vORs[] = trim($vToken[2], '"\'');
                }
            }

            if (count($vORs) > 0) {
                $vResults['CNF'][] = $vORs;
            }
        }

        return $vResults;
    }

    /**
     * Parse a query string.
     *
     * @param $pQuery
     *                <string> : the query string
     *                <array> : the already parsed array
     *
     * @return <array> of [
     * "name" => <string>,
     * "operator" => <string>,
     * "values" => [ <string> ... ]
     * ];
     */
    public function parseQuery($pQuery)
    {
        if (is_array($pQuery)) {
            $vQuery = $this->queryToString($pQuery);
        } else {
            $vQuery = $pQuery;
        }

        if (trim($vQuery ?? '') == '') {
            return [];
        }

        return array_filter(
            array_map(
                function ($pValue) {
                    preg_match_all("/\s*([^=!<>]*)\s*(==|!=|<=|>=|=|<|>)(.*)/", $pValue, $pMatches);
                    $vName = isset($pMatches[1][0]) ? trim($pMatches[1][0]) : null;

                    $vOperator = isset($pMatches[2][0]) ? trim($pMatches[2][0]) : null;

                    if ($vOperator == '=') {
                        $vOperator = '==';
                    }

                    $vUniqueValues = [];
                    if (isset($pMatches[3][0])) {
                        foreach (explode(',', trim($pMatches[3][0])) as $vValue) {
                            if (preg_match('/^\[(.*)\]$/', $vValue, $matches)) {
                                switch ($matches[1]) {
                                    case 'user.name':
                                        $vValue = $this->wiki->getUserName();
                                        break;
                                    case 'user.entry.id_fiche':
                                        $vUserManager = $this->wiki->services->get(UserManager::class);
                                        $entry = $vUserManager->getAssociatedEntry();
                                        if (!empty($entry)) {
                                            $vValue = $entry['id_fiche'];
                                        }
                                        break;
                                }
                            }
                            if (!in_array($vValue, $vUniqueValues, true)) {
                                $vUniqueValues[] = $vValue;
                            }
                        }
                    }

                    return
                        [
                            'name' => $vName,
                            'operator' => $vOperator,
                            'values' => $vUniqueValues,
                        ];
                },
                array_filter(
                    array_unique(explode('|', $vQuery)),
                    function ($pValue) {
                        return trim($pValue) != '';
                    },
                ),
            ),
            function ($pValue) {
                return isset($pValue['name']) && trim($pValue['name']) != '';
            },
        );
    }

    /**
     * Get the minimum search keywords length to be use in the search methods.
     *
     * @return <integer> the mininum search keywords length
     */
    public function getMinSearchKeywordLength()
    {
        $vMinimumSearchKeywordLength = $this->wiki->GetConfigValue('min_search_keyword_length');

        if (empty($vMinimumSearchKeywordLength)) {
            $vMinimumSearchKeywordLength = MIN_SEARCH_KEYWORD_LENGTH;
        }

        $vMinimumSearchKeywordLength = intval($vMinimumSearchKeywordLength);

        return $vMinimumSearchKeywordLength;
    }

    public function paramsToURLSearchParams($pParameters)
    {
        $vParameters = [];

        if (isset($pParameters['queries'])) {
            $vQuery = trim($this->queryToString($pParameters['queries']));

            if ($vQuery != '') {
                $vParameters[] = 'query=' . urlencode($vQuery);
            }
        }

        if (isset($pParameters['keywords'])) {
            $vKeywords = $this->keywordsToString($pParameters['keywords']);

            if ($vKeywords != '') {
                $vParameters[] = 'keywords=' . urlencode($vKeywords);
            }
        }

        if (isset($pParameters['searchfields'])) {
            $vSearchFields = is_string($pParameters['searchfields'])
                                ? $pParameters['searchfields']
                                : implode(',', array_map(function ($pField) {
                                    return trim($pField);
                                }, $pParameters['searchfields']));

            $vParameters[] = 'searchfields=' . $vSearchFields;
        }

        if (isset($pParameters['correspondance'])) {
            $vCorrespondances = $pParameters['correspondance'];

            $vParameters[] = 'correspondance=' . urlencode(is_array($vCorrespondances)
                    ? implode(',', array_map(function ($pName) use ($vCorrespondances) {
                        return $pName . '=' . trim($vCorrespondances[$pName]);
                    }, array_keys($vCorrespondances)))
                    : $vCorrespondances);
        }

        if (isset($pParameters['datefilter'])) {
            $vParameters[] = 'datefilter=' . trim($pParameters['datefilter']);
        }

        if (isset($pParameters['nb'])) {
            $vParameters[] = 'nb=' . trim($pParameters['nb']);
        }

        if (isset($pParameters['period'])) {
            $vParameters[] = 'period=' . trim($pParameters['period']);
        }

        if (isset($pParameters['ordre'])) {
            $vParameters[] = 'ordre=' . trim($pParameters['ordre']);
        }

        if (isset($pParameters['champ'])) {
            $vParameters[] = 'champ=' . trim($pParameters['champ']);
        }

        return implode('&', array_filter($vParameters, function ($pParameter) {
            return !empty($pParameter);
        }));
    }

    /**
     * Transform a query to a string.
     *
     * @param $pQuery array|string|null the query in different format :
     *                new array format [ [ "name" => "bf_field", "operator" => "==" , values [ "toto", ... ] ], ... ]
     *                OR
     *                old array format : [ "bf_field" => "toto", "bf_field2!" => "tata" ]
     *                OR
     *                new string format : bf_field == toto1 | bf_field2 <= tata
     *                OR
     *                old string format bf_field=toto1|bf_field2!=tata
     *
     * @return the string representing the query
     */
    public function queryToString($pQuery)
    {
        if ($pQuery === null) {
            return '';
        }

        if (is_array($pQuery)) {
            return implode(
                '|',
                array_map(
                    function ($pKey) use ($pQuery) {
                        if (is_int($pKey)) {
                            return $pQuery[$pKey]['name'] . $pQuery[$pKey]['operator'] . (is_array($pQuery[$pKey]['values']) ? implode(',', $pQuery[$pKey]['values']) : $pQuery[$pKey]['values']);
                        }

                        return $pKey . '=' . $pQuery[$pKey];
                    },
                    array_keys($pQuery),
                ),
            );
        } elseif (is_string($pQuery)) {
            return $pQuery;
        } else {
            return '';
        }
    }

    public function keywordsToString($pKeywords)
    {
        if (is_string($pKeywords)) {
            return $pKeywords;
        }

        $vResult = [];

        $vResult[] = implode('|', array_map(function ($pORs) {
            return implode(',', $pORs);
        }, $pKeywords['CNF']));

        $vResult[] = trim(implode(' ', array_map(function ($pExcluded) {
            return implode('-', $pORs);
        }, $pKeywords['excluded'])));

        return implode(' ', $vResult);
    }

    /**
     * Aggregate keywords.
     *
     * @param $pArguments : list of <argument>
     *                    <argument> as
     *                    <string> keywords specification
     *                    OR
     *                    null
     *
     * @return <string> aggregated keywords
     */
    public function aggregateKeywords(...$pArguments): string
    {
        $vKeywords = [];

        foreach ($pArguments as $vArgument) {
            if (isset($vArgument)) {
                $vKeywords[] = $vArgument;
            }
        }

        $vMinSearchKeywordLength = $this->getMinSearchKeywordLength();

        $vResult = implode(
            '|',
            array_unique(
                array_filter(
                    explode('|', implode('|', $vKeywords)),
                    function ($vValue) use ($vMinSearchKeywordLength) {
                        return trim($vValue) != '' && strlen($vValue) >= $vMinSearchKeywordLength;
                    },
                ),
            ),
        );

        if (isset($vResult)) {
            return $vResult;
        }

        return '';
    }

    /**
     * Aggregate queries.
     *
     * @param $pArguments : list of <argument>
     *                    <argument> as
     *                    <array> argument array containing "query"
     *                    <string> a query string
     *                    null
     *
     * @return <string> aggregated queries
     */
    public function aggregateQueries(...$pArguments): string
    {
        $vQueries = [];

        foreach ($pArguments as $vArgument) {
            if (isset($vArgument)) {
                if (is_array($vArgument)) {
                    $vQuery = $this->queryToString($vArgument['query'] ?? $vArgument['queries'] ?? null);
                } elseif (is_string($vArgument)) {
                    $vQuery = urldecode($vArgument);
                }

                if (trim($vQuery) != '') {
                    $vQueries[] = $vQuery;
                }
            }
        }

        $vResult = implode(
            '|',
            array_unique(
                array_filter(
                    $vQueries,
                    function ($vValue) {
                        return trim($vValue) != '';
                    },
                ),
            ),
        );

        if (isset($vResult)) {
            return $vResult;
        }

        return '';
    }

    /**
     * Normalise une chaîne :
     *   - met en minuscules (Unicode-safe)
     *   - transforme les caractères accentués en leur équivalent non accentué
     *   - gère les ligatures courantes (œ, æ, ß, etc.).
     *
     * @param <string> : chaîne d'entrée (n'importe quel encodage détectable)
     *
     * @return <string> : chaîne lowercase, sans accents
     */
    private function toLowerCaseWithoutAccent(string $s): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'auto');
        }

        $s = mb_strtolower($s, 'UTF-8');

        $replacements = [
            'œ' => 'oe',
            'æ' => 'ae',
            'ß' => 'ss',
            'ø' => 'o',
            'ð' => 'd',
            'þ' => 'th',
        ];
        $s = str_replace(array_keys($replacements), array_values($replacements), $s);

        if (class_exists('Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        }

        $s = preg_replace('/\p{M}/u', '', $s);

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($translit !== false) {
            $s = $translit;
        }

        return $s;
    }

    /**
     * Test if a string represents a regexp
     * A string is considered as a regexp :
     * 	if it contains at least one ".*"
     * 		or
     *	if it begins and ends with "/".
     *
     * @param pString <string> : the string to test
     *
     * @return <integer> :
     *	0 if the string doesn't represent a regexp
     *	1 if the string represent a regexp in the old YesWiki format : ex: .*toto.*
     *  2 if the string represent a regexp in MYSQL format /<regexp>/ : ex: / .*toto.* /
     */
    private function isRegExp($pString)
    {
        if (mb_substr($pString, 0, 1) == '/' && mb_substr($pString, -1, 1) == '/') {
            return 2;
        } elseif (preg_match('/\.\*/', $pString) == 1) {
            return 1;
        }

        return 0;
    }

    /**
     * Extract and transform a regexp string from a string recognized by isRegExp as a regexp
     * + It removes beginning and ending "/" if it exists
     * + Optionnaly, it add alternatives for each character that has an accented version.
     *
     * @param pString : <string> a regexp string recognized by isRegExp as a regexp
     * @param pAccentInsensitive : <boolean> true to make the regexp accent insensitive
     *
     * @return <string> : the transformed regexp string
     */
    private function extractRegExp($pString, $pAccentInsensitive = true)
    {
        $vString = $pString;

        switch ($this->isRegExp($pString)) {
            case 0:
                throw new Exception($pString . ' is not a regexp');

                return '';
                break;
            case 1:
                $vString = '^' . $pString . '$';
                break;
            case 2:
                $vString = mb_substr($pString, 1, mb_strlen($pString) - 2);
                break;
        }

        if ($pAccentInsensitive) {
            $vString = $this->toLowerCaseWithoutAccent($vString);

            $vString = str_replace(
                [
                    'a',
                    'c',
                    'e',
                    'i',
                    'n',
                    'o',
                    'u',
                    'y',
                ],
                [
                    '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)',
                    '(c|ç|C|Ç)',
                    '(e|è|é|ê|ë|E|È|É|Ê|Ë)',
                    '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)',
                    '(n|ñ|N|Ñ)',
                    '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)',
                    '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)',
                    '(y|ý|ÿ|Y|Ý)',
                ],
                $vString,
            );
        }

        return $vString;
    }

    /**
     * Build a hash from structure definition
     * The hash is a facility for associative array search.
     *
     * @param pStructure <array> : the structure as
     * 	[
     * ]
     *
     * @return <string> : the hash
     */
    private function buildFieldDescriptorHash($pStructure)
    {
        return $pStructure['_mode_'] ?? '#|' . $pStructure['_type_'] ?? '#';
    }

    /**
     * Whether a requested name can be a form field or a JSON path into one, and so be used as an SQL identifier.
     */
    private function isFieldName(mixed $pName): bool
    {
        if (!is_string($pName)) {
            return false;
        }
        foreach (explode('.', $pName) as $vSegment) {
            if (preg_match(BazarField::PROPERTY_NAME_PATTERN, $vSegment) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The column a requested field is extracted into, quoted as an SQL identifier.
     */
    private function column(string $pFieldName, string $pSuffix = ''): string
    {
        return '`' . str_replace('`', '``', $this->renameJSONPathVariable($pFieldName) . $pSuffix) . '`';
    }

    /**
     * The JSON path to a requested field with every key quoted, e.g. $."geolocation"."bf_latitude".
     */
    private function jsonPath(string $pFieldName): string
    {
        return '$.' . implode('.', array_map(fn ($pKey) => '"' . addcslashes($pKey, '"\\') . '"', explode('.', $pFieldName)));
    }

    /**
     * Rename a JSON path variable (ex : "geolocation.bf_latitude") in order to be exploitable in SQL request.
     *
     * @param string $pPath
     *
     * @return string the transformed path
     */
    protected function renameJSONPathVariable($pPath)
    {
        return str_replace('.', '__', $pPath);
    }
}

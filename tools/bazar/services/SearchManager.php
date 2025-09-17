<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

class SearchManager
{
    protected $wiki;
    protected $dbService;
    protected $aclService;

    public const MISSING_PROPERTY = '_MISSING_PROPERTY_';
    public const MISSING_FIELD = '_MISSING_FIELD_';

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        AclService $aclService
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
     * @return array ['needle 1'=>[], // when not in list
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
        // catch "exact text" and rest separated by space
        if (!empty($phrase) && preg_match_all('/^([^" ]+)|(?:")([^"]+)(?:")|([^" ]+)$|(?: )([^" ]+)(?: )/', $phrase, $matches)) {
            // find needles
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

            // find needle in lists
            // search in list values
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
                                $option = implode(' ', $option); // rare cases with arrays, ex: usernames
                            }
                            // mb_strtolower instead of strtolower to manage utf 8 characters
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
        // be careful to ( and )
        $needle = str_replace(['(', ')', '/'], ['\\(', '\\)', '\\/'], $needle);

        // remove accents
        $needle = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'],
            ['a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y'],
            $needle
        );

        // add for regexp
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
            $needle
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
        // Let's parse the given keywords search string...

        $vParsedKeywords = $this->parseKeywords($pKeywords, $pMinKeywordsLength);

        // if there is nothing to do, there is nothing to do

        if ((count($vParsedKeywords['CNF']) == 0 && count($vParsedKeywords['excludeds']) == 0) || count($pSearchFields) == 0) {
            return '';
        }

        // ... and let's analyse it

        // Analyses ANDs clauses

        // We will merge ANDs later

        $vANDs = [];

        foreach ($vParsedKeywords['CNF'] as $vAND) {
            // We will merge ORs later

            $vORs = [];

            // Analyse ORs clauses

            foreach ($vAND as $vOR) {
                // Remember if the token value is a regexp

                $vIsRegExp = $this->isRegExp($vOR);

                // For each ORs token, we will build a condition that apply on each search field

                foreach ($pSearchFields as $vFieldName => $vField) {
                    // We need to build a specific condition for each field structure

                    foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                        $vORRequest = '';

                        switch ($vFieldDescriptor['mode']) {
                            // If this field instance in is intended to store a single value...

                            case 'single':
                                // Add a field condition adapted to a regexp or not

                                if ($vIsRegExp) {
                                    $vORRequest = $this->renameJSONPathVariable($vFieldName) . ' COLLATE utf8mb4_unicode_ci REGEXP \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vOR)) . '\'';
                                } else {
                                    $vORRequest = $this->renameJSONPathVariable($vFieldName) . ' COLLATE utf8mb4_unicode_ci LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vOR) . '%\'';
                                }

                            break;

                            // If this field instance is intended to store multiple values separated by comma...

                            case 'multiple':
                                // Add a field condition adapted to a regexp or not

                                if ($vIsRegExp) {
                                    $vORRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE utf8mb4_unicode_ci REGEXP \'^' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vOR)) . '$\')';
                                } else {
                                    $vORRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE utf8mb4_unicode_ci LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vOR) . '%\')';
                                }

                            break;
                        }

                        // If the field can have multiple structures, we need to specify the form IDs to which the condition apply

                        if ($vField['hasMultipleStructures']) {
                            if ($vORRequest != '') {
                                $vORRequest = '( ' . $this->renameJSONPathVariable('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) { return '\'' . $pFormID . '\''; }, $vFieldDescriptor['ids'])) . ') AND ' . $vORRequest . ')';
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
            // Remember if the excluded token value is a regexp

            $vIsRegExp = $this->isRegExp($vExcluded);

            // For each excluded token, we will build a condition that apply on each search field

            foreach ($pSearchFields as $vFieldName => $vField) {
                // The condition we will construct

                $vExcludedRequest = '';

                // We need to build a specific condition for each field structure

                foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                    switch ($vFieldDescriptor['mode']) {
                        // If this field instance is intended to store a single value...

                        case 'single':
                            // Add a field condition adapted to a regexp or not

                            if ($vIsRegExp) {
                                $vExcludedRequest = mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci NOT REGEXP \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vExcluded)) . '\'';
                            } else {
                                $vExcludedRequest = mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci NOT LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vExcluded) . '%\'';
                            }

                        break;

                        // If this field instance is intended to store multiple values separated by comma...

                        case 'multiple':
                            // Add a field condition adapted to a regexp or not

                            if ($vIsRegExp) {
                                $vExcludedRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE utf8mb4_unicode_ci NOT REGEXP \'^' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vExcluded)) . '$\')';
                            } else {
                                $vExcludedRequest = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE utf8mb4_unicode_ci NOT LIKE \'%' . mysqli_real_escape_string($this->wiki->dblink, $vExcluded) . '%\')';
                            }

                        break;
                    }

                    // If the field can have multiple structures, we need to specify the form IDs to which the condition apply

                    if ($vField['hasMultipleStructures']) {
                        if ($vExcludedRequest != '') {
                            $vExcludedRequest = '( ' . $this->renameJSONPathVariable('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) { return '\'' . $pFormID . '\''; }, $vFieldDescriptor['ids'])) . ') AND ' . $vExcludedRequest . ')';
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
            array_unique($vANDs)
        );
    }

    /**
     * Build the SQL fields conditions for queries.
     *
     * @param $pQueries : <array> of <query>
     *			   <query> = [ "name" => <string>, "operator" => <string>, "values" => <array of strings> ]
     *
     * @return = <string> fields conditions for queries
     */
    public function buildQueriesConditions($pQueries, $pFields)
    {
        // The conditions we are going to build

        $vQueriesConditions = [];

        // For each field query

        foreach ($pQueries as $vQuery) {
            // Build the query condition for this field :

            // Name of the field

            $vFieldName = $vQuery['name'];

            // operator to be applied to the field

            $vOperator = $vQuery['operator'];

            // Get the field structure for later use

            $vField = $pFields[$vFieldName];

            // We will store individual field conditions in an array to facilitate merging later

            $vQueryConditions = [];

            // Let's check what is the operator and store helpers to know what to apply in the request

            switch ($vOperator) {
                // "is equal" and "is different"

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
                    $vRegExpOperator = 'REGEXP'; // Should not be used or not yet implemented
                    $vComparisonOperator = '<';
                    $vFindInSetOperator = 'FIND_IN_SET'; // Should not be used or not yet implemented
                break;
                case '>':
                    $vRegExpOperator = 'REGEXP'; // Should not be used or not yet implemented
                    $vComparisonOperator = '>';
                    $vFindInSetOperator = 'FIND_IN_SET'; // Should not be used or not yet implemented
                break;
                case '<=':
                    $vRegExpOperator = 'REGEXP'; // Should not be used or not yet implemented
                    $vComparisonOperator = '<=';
                    $vFindInSetOperator = 'FIND_IN_SET'; // Should not be used or not yet implemented
                break;
                case '>=':
                    $vRegExpOperator = 'REGEXP'; // Should not be used or not yet implemented
                    $vComparisonOperator = '>=';
                    $vFindInSetOperator = 'FIND_IN_SET'; // Should not be used or not yet implemented
                break;
                default:
                    throw new Exception($vOperator . ' is not recognized');

                    return [];
            }

            // We need to add conditions that take into account all the possible structures
            // that may have the field depending on which form it belongs

            // So, for each structure...

            foreach ($vField['descriptors'] as $vHash => $vDescriptor) {
                // Build the condition for each value specified in the request ("comma separated values")

                $vValueConditions = [];

                foreach ($vQuery['values'] as $vValue) {
                    // Remember if the value is a regexp

                    $vIsRegExp = $this->isRegExp($vValue);

                    switch ($vDescriptor['mode']) {
                        // If the field is intended to store a single value...

                        case 'single':
                            // It the value is a regexp, let's build a condition that match (or NOT) the regexp

                            if ($vIsRegExp) {
                                $vValueConditions[] = mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci ' . $vRegExpOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vValue)) . '\'';
                            }

                            // else let's just compare using the appropriated comparison operator

                            else {
                                if ($vDescriptor['type'] == 'number') {
                                    if (isset($vValue) && trim($vValue) !== '') {
                                        $vValueConditions[] = 'CAST(' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' AS DOUBLE) ' . $vComparisonOperator . ' ' . mysqli_real_escape_string($this->wiki->dblink, $vValue);
                                    } else {
                                        $vValueConditions[] = '(' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci ' . $vComparisonOperator . ' \'\' )';
                                    }
                                } else {
                                    $vValueConditions[] = mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci ' . $vComparisonOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $vValue) . '\'';
                                }
                            }

                        break;

                        // If the field is intended to store multiple values separated by comma...

                        case 'multiple':
                            // It the value is a regexp, let's build a condition that match (or NOT) the regexp in the list of values extracted in temporary tables earlier

                            if ($vIsRegExp) {
                                $vValueConditions[] = '(s.champ = \'' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . '\' AND s.elt COLLATE utf8mb4_unicode_ci ' . $vRegExpOperator . ' \'' . mysqli_real_escape_string($this->wiki->dblink, $this->extractRegExp($vValue)) . '\')';
                            } else { // else let's just check in the value belongs (or NOT) to the set of values
                                $vValueConditions[] = $vFindInSetOperator . ' (\'' . mysqli_real_escape_string($this->wiki->dblink, $vValue) . '\' COLLATE utf8mb4_unicode_ci, ' . mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName)) . ' COLLATE utf8mb4_unicode_ci)';
                            }

                        break;

                        // The field is missing : we need to add a specific condition

                        case self::MISSING_FIELD:
                        case self::MISSING_PROPERTY:
                            $vValueConditions[] = 'FALSE';

                        break;
                    }
                }

                $vDescriptorCondition = '';

                // Merge all value conditions with a logical OR and add it to the descripted field condition

                if (count($vValueConditions) > 0) {
                    $vDescriptorCondition = implode(' OR ', $vValueConditions);

                    // if we had remembered that this field can have multiple structures
                    // we need to specify the form IDs that use this structure in the condition request

                    if ($vField['hasMultipleStructures']) {
                        if (vDescriptorCondition != '') {
                            $vDescriptorCondition = $this->renameJSONPathVariable('id_typeannonce') . ' IN (' . implode(',', array_map(function ($pFormID) { return '\'' . $pFormID . '\''; }, $vDescriptor['ids'])) . ') AND (' . vDescriptorCondition . ')';
                        }
                    }
                }

                // Add the structure conditions to the field conditions

                if ($vDescriptorCondition != '') {
                    $vQueryConditions[] = '(' . $vDescriptorCondition . ')';
                }
            }

            // Merge all the field conditions with a logical OR

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
        // Merge default parameters with given parameters

        $params = array_merge(
            [
                'queries' => [], // array of [ name => <string>, operator => <string> , values => [ <string>, ... ] ]
                'formsIds' => [], // Types de fiches (par ID de formulaire)
                'user' => '', // N'affiche que les fiches d'un utilisateur
                'minDate' => '', // Date minimale des fiches
                'correspondance' => '',
            ],
            $params
        );

        // Get Keywords

        $vKeywords = $params['keywords'] ?? '';

        // Parse queries is correctly formated

        $vQueries = $this->parseQuery($params['queries']);

        // Limit the request to the specified form ids

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
                            } else {
                                return null;
                            }
                        }

                        return null;
                    },
                    $vFormIDs
                );

            $vFormIDs = array_filter(
                    $vFormIDs,
                    function ($pID) {
                        return $pID !== null;
                    }
                );

            $vIDsRequest .= 'JSON_UNQUOTE(JSON_EXTRACT(body, \'$.id_typeannonce\')) IN (' . join(',', array_map(function ($pFormID) { return '\'' . $pFormID . '\''; }, $vFormIDs)) . ')';
        } else {
            $vFormIDs = [];
        }

        // Limit the request depending on the date

        $vPeriodRequest = '';

        if (!empty($params['minDate'])) {
            $vPeriodRequest .= 'time >= "' . mysqli_real_escape_string($this->wiki->dblink, $params['minDate']) . '"';
        }

        // Limit the request to a user if specified

        $vUserRequest = '';

        if (!empty($params['user'])) {
            $vUserRequest .= 'owner = _utf8\'' . mysqli_real_escape_string($this->wiki->dblink, $params['user']) . '\'';
        }

        // Determine the necessary fields from searchfields and queries

        $vKeywordsFields = [];
        $vQueriesFields = [];

        if ($vKeywords != '') {
            $vSearchFields = isset($params['searchfields'])
                                ? is_array($params['searchfields'])
                                    ? $params['searchfields']
                                    : explode(',', $params['searchfields'])
                                : [];

            $vSearchFields[] = 'bf_titre';

            $vKeywordsFields = array_unique(array_map('trim', $vSearchFields));
        }

        foreach ($vQueries as $vQuery) {
            $vQueriesFields[] = $vQuery['name'];
        }

        $vNecessaryFields = array_unique(array_merge($vKeywordsFields, $vQueriesFields));

        // Build necessary fields infos (structures, ...)

        $vFields = [];

        // Each field can have differents value structures (handling mode : "single"|"multiple", and type "boolean"|"number"|"string")
        // depending on the form it belongs to
        // ex : form1 -> bf_myfield = single text value
        // 		form2 -> bf_myfield = multiple text values separated by commas
        // We need to handle it differently

        // So, first, let's get all the forms used in the request for later use

        $vFormManager = $this->wiki->services->get(FormManager::class);

        $vForms = $vFormManager->getMany($vFormIDs);

        // For each necessary field, let's retrieve the value structures of all forms...

        foreach ($vNecessaryFields as $vField) {
            if (isset($vFields[$vField])) { // value structures already retrieved for this field, let's ignore it
                continue;
            }

            // We will store the field structure associated with form IDs, so create a place for it

            if (!isset($vFields[$vField]['descriptors'])) {
                $vFields[$vField]['descriptors'] = [];
            }
            if (!isset($vFields[$vField]['needSplit'])) {
                $vFields[$vField]['needSplit'] = false;
            }

            // For each form...

            foreach ($vForms as $vFormID => $vForm) {
                // ... we try to find the field by property name if it exists ...
                // ex :"geolocation" in geolocation.bf_latitude

                $vPropertyFound = false;

                foreach ($vForm['prepared'] as $vFieldObject) {
                    // Extract the JSON path of the field

                    $vJSONPath = explode('.', $vField);

                    // Get the property name

                    $vPropertyName = $vJSONPath[0] ?? '';

                    if ($vFieldObject->getPropertyName() == $vPropertyName) {
                        // We found it

                        $vPropertyFound = true;

                        // We need to find the field mode and type associated to the complete field name (ex : "geolocation.bf_latitude")

                        // So, let's get the field structure

                        $vStructure = $vFieldObject->getValueStructure();

                        // and try to find inside the complete field name

                        $vCurrentArray = $vStructure;

                        $vFieldFound = true;

                        foreach ($vJSONPath as $vJSONPathSegment) {
                            if (is_array($vCurrentArray) && array_key_exists($vJSONPathSegment, $vCurrentArray)) {
                                if (is_array($vCurrentArray) && array_key_exists($vJSONPathSegment, $vCurrentArray)) {
                                    $vCurrentArray = $vCurrentArray[$vJSONPathSegment];
                                } else {
                                    $vFieldFound = false;
                                }
                            }
                        }

                        if ($vFieldFound) {
                            // We found it : we know the mode and type of the field

                            $vFieldDescriptor = $vCurrentArray;
                        } else {
                            // We do not found it : the complete field name is missing in the form

                            $vFieldDescriptor = ['_mode_' => self::MISSING_FIELD, '_type_' => self::MISSING_FIELD];
                        }

                        // Remember that the field can have this mode and type in this the form :

                        // Build a hash for fast access...

                        $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

                        // and remember it.

                        if (isset($vFields[$vField]['descriptors'][$vHash])) {
                            $vFields[$vField]['descriptors'][$vHash]['ids'][] = $vFormID;
                        } else {
                            $vFields[$vField]['descriptors'][$vHash] = ['mode' => $vFieldDescriptor['_mode_'], 'type' => $vFieldDescriptor['_type_'], 'ids' => [$vFormID]];
                        }

                        // If the "mode" of this field in this form Id is "multiple", let's remember we have to split it

                        if ($vFieldDescriptor['_mode_'] == 'multiple') {
                            $vFields[$vField]['needSplit'] = true;
                        }

                        break; // We found it, so we can stop searching
                    }

                    // else we continue searching...
                }

                // If we do not found the property in this form, let's memorize it

                if (!$vPropertyFound) {
                    $vFieldDescriptor = ['_mode_' => self::MISSING_PROPERTY, '_type_' => self::MISSING_PROPERTY];

                    $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

                    if (isset($vFields[$vField]['descriptors'][$vHash])) {
                        $vFields[$vField]['descriptors'][$vHash]['ids'][] = $vFormID;
                    } else {
                        $vFields[$vField]['descriptors'][$vHash] = ['mode' => $vFieldDescriptor['_mode_'], 'type' => $vFieldDescriptor['_type_'], 'ids' => [$vFormID]];
                    }
                }
            }

            // We will remember if the field can have different kind of structures so that we can optimize SQL request.

            $vFields[$vField]['hasMultipleStructures'] = count(array_keys($vFields[$vField]['descriptors'])) > 1;

            // Let's remember that the field has not been yet extracted

            $vFields[$vField]['isExtracted'] = false;

            // ...neither is has been yet splitted if necessary

            $vFields[$vField]['isSplitted'] = false;
        }

        // Build the SELECT part of the request :

        // - Retrieves all columns and extract id_typeannonce

        $vSelectRequest =
        [
            'p.*',
            'JSON_UNQUOTE(JSON_EXTRACT(body, \'$.id_typeannonce\')) AS `' . $this->renameJSONPathVariable('id_typeannonce') . '`',
        ];

        // - Extract all fields ("single" and "multiple" mode)

        foreach ($vFields as $vFieldName => $vField) {
            // Extract one field

            // Check that it was not already extracted

            if (!$vField['isExtracted']) {
                // Extract it if it is not yet done

                $vSQLNom = mysqli_real_escape_string($this->wiki->dblink, $vFieldName);
                $vRenamedSQLNom = mysqli_real_escape_string($this->wiki->dblink, $this->renameJSONPathVariable($vFieldName));

                $vSelectRequest[] = 'JSON_UNQUOTE(JSON_EXTRACT(body, \'$.' . $vSQLNom . '\')) AS `' . $vRenamedSQLNom . '`';

                // rembember it was extracted

                $vField['isExtracted'] = true;
            }
        }

        // - Finaly, concatenate the SELECT request

        $vSelectRequest = implode(', ', $vSelectRequest);

        // Split fields that may be in multiple mode :

        // - We will concatenate splitted fields later

        $vSplitteds = [];
        $vSplittedsRequest = '';

        // - Let's check each field :

        foreach ($vFields as $vFieldName => $vField) {
            // If the field doesn't have to be splitted (= it is always in single value mode)
            // or it was already splitted then we can ignore it.

            if (!$vField['needSplit'] || $vField['isSplitted']) {
                continue;
            }

            // else we split it

            $vSplitteds[] = 'SELECT id, champ, elt FROM ' . $this->renameJSONPathVariable($vFieldName) . '_multiple';

            $vSplittedsRequest .=
                        ', ' . $this->renameJSONPathVariable($vFieldName) . '_multiple AS ' .
                        '( ' .
                            'SELECT ' .
                                'id, ' .
                                '\'' . $this->renameJSONPathVariable($vFieldName) . '\' AS champ, ' .
                                'TRIM(SUBSTRING_INDEX(' . $vFieldName . ', \',\', 1)) AS elt, ' .
                                'CASE ' .
                                    'WHEN INSTR(' . $this->renameJSONPathVariable($vFieldName) . ', \',\') = 0 THEN \'\' ' .
                                    'ELSE SUBSTR(' . $this->renameJSONPathVariable($vFieldName) . ', INSTR(' . $this->renameJSONPathVariable($vFieldName) . ', \',\') + 1) ' .
                                'END AS rest ' .
                            'FROM filteredPages ' .
                            'UNION ALL ' .
                            'SELECT ' .
                                'id, ' .
                                'champ, ' .
                                'TRIM(SUBSTRING_INDEX(rest, \',\', 1)) AS elt, ' .
                                'CASE ' .
                                    'WHEN INSTR(rest, \',\') = 0 THEN \'\' ' .
                                    'ELSE SUBSTR(rest, INSTR(rest, \',\') + 1) ' .
                                'END AS rest ' .
                            'FROM ' . $this->renameJSONPathVariable($vFieldName) . '_multiple ' .
                            'WHERE rest <> \'\'' .
                        ')';

            // And we remember it has been done

            $vField['isSplitted'] = true;
        }

        // Union of all splitted fields

        $vSplittedsCount = count($vSplitteds);

        if ($vSplittedsCount > 0) {
            $vSplittedsRequest .=
                        ', all_multiples AS ' .
                        '( ' .
                            implode(' UNION ALL ', $vSplitteds) .
                        ') ';
        }

        // Construct WHERE part with queries and keywords conditions

        $vWhereRequest = '';

        // Keywords conditions

        // Let's retrieve the minimum search keyword length

        $vMinSearchKeywordLength = $this->getMinSearchKeywordLength();

        $vKeywordsConditions = $this->buildKeywordsConditions(
            $vKeywords,  // the keywords search string
            array_filter // apply only to search fields
            (
                $vFields,
                function ($vFieldName) use ($vKeywordsFields) {
                    return in_array($vFieldName, $vKeywordsFields);
                },
                ARRAY_FILTER_USE_KEY
            ),
            $vMinSearchKeywordLength
        );

        $vWhereRequest .= $vKeywordsConditions;

        // Queries conditions

        $vQueriesConditions = trim($this->buildQueriesConditions($vQueries, $vFields));

        if ($vQueriesConditions != '') {
            $vWhereRequest .= ($vWhereRequest != '' ? ' AND ' : '') . $vQueriesConditions;
        }

        // Optionnaly, filter on read ACL

        if (!$this->wiki->UserIsAdmin() && $filterOnReadACL) {
            $vWhereRequest .= ($vWhereRequest != '' ? ' AND ' : '') . $this->aclService->updateRequestWithACL();
        }

        // Construct full request

        $vCompleteRequest = 'WITH RECURSIVE ' .
                                'filteredPages AS ' .
                                '( ' .
                                    'SELECT ' .
                                        $vSelectRequest . ' ' .
                                    'FROM ' . $this->dbService->prefixTable('pages') . ' p ' .
                                    'JOIN ' . $this->dbService->prefixTable('triples') . ' t ON ' .
                                        't.resource = p.tag AND ' .
                                        't.value = \'' . $this->wiki->services->get(EntryManager::class)::TRIPLES_ENTRY_ID . '\' AND ' .
                                        't.property = \'http://outils-reseaux.org/_vocabulary/type\' ' .
                                    'WHERE ' .
                                        ($applyOnAllRevisions ? '' : 'latest=\'Y\' AND ') .
                                        'p.comment_on = \'\'' .
                                        ($vUserRequest !== '' ? ' AND ' . $vUserRequest : '') .
                                        ($vPeriodRequest !== '' ? ' AND ' . $vPeriodRequest : '') .
                                        ($vIDsRequest !== '' ? ' AND ' . $vIDsRequest : '') .
                                ')' .
                                ($vSplittedsRequest != '' ? $vSplittedsRequest . ' ' : ' ') .
                                'SELECT DISTINCT f.* ' .
                                'FROM filteredPages f ' .
                                ($vSplittedsCount > 0 ? 'LEFT JOIN all_multiples s ON s.id = f.id ' : '') .
                                ($vWhereRequest != '' ? 'WHERE ' . $vWhereRequest : '');

        /*
                // requete de jointure : reprend la requete precedente et ajoute des criteres
                if (isset($_GET['joinquery'])) {
                    $join = $this->dbService->escape($_GET['joinquery']);
                    $joinrequeteSQL = '';
                    $tableau = [];
                    $tab = explode('|', $join);
                    //découpe la requete autour des |
                    foreach ($tab as $req) {
                        $tabdecoup = explode('=', $req, 2);
                        $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
                    }
                    $first = true;

                    foreach ($tableau as $nom => $val) {
                        if (!empty($nom) && !empty($val)) {
                            $valcrit = explode(',', $val);
                            if (is_array($valcrit) && count($valcrit) > 1) {
                                foreach ($valcrit as $critere) {
                                    if (!$first) {
                                        $joinrequeteSQL .= ' AND ';
                                    } else {
                                        $first = false;
                                    }
                                    $rawCriteron = $this->convertToRawJSONStringForREGEXP($critere);
                                    $joinrequeteSQL .=
                                        '(body REGEXP \'"' . $nom . '":"[^"]*' . $rawCriteron .
                                        '[^"]*"\')';
                                }
                                $joinrequeteSQL .= ')';
                            } else {
                                if (!$first) {
                                    $joinrequeteSQL .= ' AND ';
                                } else {
                                    $first = false;
                                }
                                $rawCriteron = $this->convertToRawJSONStringForREGEXP($val);
                                if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                                    $joinrequeteSQL .=
                                        '(body REGEXP \'"' . $nom . '":"' . $rawCriteron . '"\')';
                                } else {
                                    $joinrequeteSQL .=
                                        '(body REGEXP \'"' . $nom . '":("' . $rawCriteron .
                                        '"|"[^"]*,' . $rawCriteron . '"|"' . $rawCriteron . ',[^"]*"|"[^"]*,'
                                        . $rawCriteron . ',[^"]*")\')';
                                }
                            }
                        }
                    }
                    if ($requeteSQL != '') {
                        $requeteSQL .= ' UNION ' . $requete . ' AND (' . $joinrequeteSQL . ')';
                    } else {
                        $requeteSQL .= ' AND (' . $joinrequeteSQL . ')';
                    }
                    $requete .= $requeteSQL;
                } elseif ($requeteSQL != '') {
                    $requete .= $requeteSQL;
                }
        */

        // debug

        if (isset($_GET['showreq'])) {
            echo '<hr><code style="width:100%;height:100px;">' . $vCompleteRequest . '</code><hr>';
        }

        return $vCompleteRequest;
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
    private function parseKeywords($pKeywords, $pMinKeywordLength)
    {
        // The default results : nothing recognized

        $vResults = ['CNF' => [], 'excludeds' => []];

        // Check if the $pKeywords parameter is valid for parsing

        if (!(is_string($pKeywords) && trim($pKeywords) != '' && $pKeywords != _t('BAZ_MOT_CLE'))) {
            return $vResults;
        }

        // Let's analyse the keywords to build a structure representing the CNF and to extract the excludeds tokens

        // Separates AND clauses

        $vANDs = array_filter(array_unique(array_map('trim', explode('|', $pKeywords))), function ($pKeyword) use ($pMinKeywordLength) {
            return strlen($pKeyword) >= $pMinKeywordLength;
        });

        foreach ($vANDs as $vAND) {
            // Extract tokens

            preg_match_all(
                '/(-)?("(?:\\\\.|[^"\\\\])*"|' .	// double quoted with optional backslash escapes
                '\'(?:\\\\.|[^\'\\\\])*\'|' .   	// single quoted
                '\S+)/u',                      	  	// or unquoted token
                 $vAND,
                $vTokens,
                PREG_SET_ORDER
            );

            // Update the CNF and the excludeds token

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

        // Return the parsed keywords array

        return $vResults;
    }

    /**
     * Parse a query string.
     *
     * @param $pQuery
     *		<string> : the query string
     *		<array> : the already parsed array
     *
     * @return <array> of [
                            "name" => <string>,
                            "operator" => <string>,
                            "values" => [ <string> ... ]
                        ];
     */
    public function parseQuery($pQuery)
    {
        if (is_array($pQuery)) {
            $vQuery = $this->queryToString($pQuery);
        } else {
            $vQuery = $pQuery;
        }

        if (trim($vQuery) == '') {
            return [];
        }

        return array_filter(
            array_map(
                // For each query in queries

                function ($pValue) {
                    // Extract name, operator and values

                    preg_match_all("/\s*([^=!<>]*)\s*(==|!=|<=|>=|=|<|>)(.*)/", $pValue, $pMatches);

                    $vName = trim($pMatches[1][0]);

                    $vOperator = trim($pMatches[2][0]);

                    // Convert old operator format to new refactored format

                    if ($vOperator == '=') {
                        $vOperator = '==';
                    }

                    // Transform comma separated values list to an array eliminating duplicates

                    $vUniqueValues = [];

                    foreach (explode(',', trim($pMatches[3][0])) as $vValue) {
                        if (!in_array($vValue, $vUniqueValues, true)) {
                            $vUniqueValues[] = $vValue;
                        }
                    }

                    // Return the queries structure

                    return
                        [
                            'name' => $vName,
                            'operator' => $vOperator,
                            'values' => $vUniqueValues,
                        ];
                },

                // Use the agregated query where empty element are removed

                array_filter(
                    array_unique(explode('|', $vQuery)),
                    function ($pValue) {
                        return trim($pValue) != '';
                    }
                )
            ),

            // Remove query with no parameter name

            function ($pValue) {
                return trim($pValue['name']) != '';
            }
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

    /**
     * Transform a query to a string.
     *
     * @param $pQuery array|string|null the query in different format :
     * 		new array format [ [ "name" => "bf_field", "operator" => "==" , values [ "toto", ... ] ], ... ]
     *			OR
     *   	old array format : [ "bf_field" => "toto", "bf_field2!" => "tata" ]
     *			OR
     *		new string format : bf_field == toto1 | bf_field2 <= tata
     *			OR
     *		old string format bf_field=toto1|bf_field2!=tata
     *
     * @return the string representing the query
     */
    public function queryToString($pQuery)
    {
        if ($pQuery === null) {
            return '';
        }

        if (is_array($pQuery)) {
            // format [ [ "name" => "bf_field", "operator" => "==" , values [ "toto", ... ] ], ... ]
            // OR
            // old array format : [ "bf_field" => "toto", "bf_field2!" => "tata" ]

            return implode(
                '|',
                array_map(
                    function ($pKey) use ($pQuery) {
                        if (is_int($pKey)) {
                            // format [ [ "name" => "bf_field", "operator" => "==" , values => "toto, tata" ] ]

                            return $pQuery[$pKey]['name'] . $pQuery[$pKey]['operator'] . (is_array($pQuery[$pKey]['values']) ? implode(',', $pQuery[$pKey]['values']) : $pQuery[$pKey]['values']);
                        } else {
                            // format [ "bf_field" => "toto", "bf_field2!" => "tata" ]

                            return $pKey . '=' . $pQuery[$pKey];
                        }
                    },
                    array_keys($pQuery)
                )
            );
        } elseif (is_string($pQuery)) {
            // old format : bf_field=toto1|bf_field2!=tata
            // 	OR
            // new format : bf_field == toto1 | bf_field2 <= tata

            // It is already the string representation of the query

            return $pQuery;
        } else {
            // Unknown format
            return '';
        }
    }

    /**
     * Aggregate keywords.
     *
     * @param $pArguments : list of <argument>
     *		<argument> as
     *     		<string> keywords specification
     *				OR
     *			null
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
                    }
                )
            )
        );

        if (isset($vResult)) {
            return $vResult;
        } else {
            return '';
        }
    }

    /**
     * Aggregate queries.
     *
     * @param $pArguments : list of <argument>
     *		<argument> as
     *			<array> argument array containing "query"
     *			<string> a query string
     *			null
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
                    }
                )
            )
        );

        if (isset($vResult)) {
            return $vResult;
        } else {
            return '';
        }
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
        // 1. Assurer que c'est en UTF-8
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'auto');
        }

        // 2. Mettre en lowercase Unicode
        $s = mb_strtolower($s, 'UTF-8');

        // 3. Remplacer les ligatures avant translitération
        $replacements = [
            'œ' => 'oe',
            'æ' => 'ae',
            'ß' => 'ss', // allemand
            'ø' => 'o',
            'ð' => 'd',
            'þ' => 'th',
        ];
        $s = str_replace(array_keys($replacements), array_values($replacements), $s);

        // 4. Décomposer les caractères Unicode (NFD) pour séparer base + accent si possible
        if (class_exists('Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        }

        // 5. Supprimer les marques diacritiques (accents)
        $s = preg_replace('/\p{M}/u', '', $s);

        // 6. En dernier recours : translitération ASCII pour les restes (ex: ñ -> n)
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($translit !== false) {
            $s = $translit;
        }

        // 7. Nettoyage : retirer ce qui ne soit pas lettre/nombre si besoin (optionnel)
        // $s = preg_replace('/[^a-z0-9]+/', '', $s);

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
    private function isRegExp($pString) // return true is $pString is a regular expression
    {
        if ((mb_substr($pString, 0, 1) == '/' && mb_substr($pString, -1, 1) == '/')) {
            return 2;
        } elseif (preg_match('/\.\*/', $pString) == 1) {
            return 1;
        } else {
            return 0;
        }
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
                $vString
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
         ]
     * @return <string> : the hash
     */
    private function buildFieldDescriptorHash($pStructure)
    {
        return $pStructure['_mode_'] . '|' . $pStructure['_type_'];
    }

    /**
     * Rename a JSON path variable (ex : "geolocation.bf_latitude") in order to be exploitable in SQL request.
     *
     * @param string $pPath
     *
     * @return string the transformed path
     */
    public function renameJSONPathVariable($pPath)
    {
        return str_replace('.', '__', $pPath);
    }
}

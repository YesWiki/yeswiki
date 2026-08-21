<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\CheckboxField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

class SearchManager
{
    protected ContainerInterface $container;
    protected DbService $dbService;
    protected AclService $aclService;

    public const MISSING_PROPERTY = '_MISSING_PROPERTY_';
    public const MISSING_FIELD = '_MISSING_FIELD_';

    /** What `p.*` brings into the search CTE, and therefore the names a field's column may not take. */
    private const PAGES_COLUMNS = [
        'id', 'tag', 'time', 'body', 'owner', 'user', 'latest', 'type',
        'parent', 'metadata',
    ];

    /** Given to a field whose name collides with one of those. */
    private const FIELD_COLUMN_PREFIX = 'yw_field__';

    public function __construct(
        ContainerInterface $container,
        DbService $dbService,
        AclService $aclService,
    ) {
        $this->container = $container;
        $this->dbService = $dbService;
        $this->aclService = $aclService;
    }

    /**
     * prepare searches.
     *
     * @param array<array-key, array<string, mixed>> $forms (needed to filter only on concerned forms)
     *
     * @return array<string, list<array<string, mixed>>> ['needle 1'=>[], // when not in list
     *                                                   'needle 2'=>[$result1,$result2]
     *                                                   ,...]  // each $result= [
     *                                                   'propertyName' => 'bf_...',
     *                                                   'key' => 'bf_...',
     *                                                   'isCheckBox' => true,
     *                                                   ]
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
     *
     * @param array<string, mixed> $needles keyed by needle; only the keys are read
     * @param array<string, mixed> $form
     *
     * @return list<array<string, mixed>>
     */
    private function searchInFormOptions(array $needles, array $form): array
    {
        $results = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof EnumField) {
                foreach ($field->getOptions() as $key => $option) {
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

        return $results;
    }

    /** prepare needle by removing accents and define string for regexp. */
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
     * @param string                                                                                              $pKeywords          the keywords search string in the format:
     *                                                                                                                                <keywords>       = ( <token> | <exluded token> )+ [ "|" <keywords> ]
     *                                                                                                                                <token>          = <string without space>	|
     *                                                                                                                                "'" <string with spaces between single quotes> "'" |
     *                                                                                                                                '"' <string with spaces between double quotes> '"'
     *                                                                                                                                <excluded token> = "-" <token>
     *                                                                                                                                example : toto -"tata tutu" | "titi tutu" tete -tyty
     *                                                                                                                                =
     *                                                                                                                                "toto" AND ("titi tutu" OR "tete") AND NOT "tata tutu" AND NOT "tyty"
     *                                                                                                                                NOTE : position of excluded fields has no signification
     * @param array<string, array{descriptors: array<string, array<string, mixed>>, hasMultipleStructures: bool}> $pSearchFields
     *                                                                                                                                field name => its descriptors. The shape was undocumented and the tag
     *                                                                                                                                that stood here was unparseable, so nothing downstream of it was checked
     *                                                                                                                                (ticket 40).
     * @param int|null                                                                                            $pMinKeywordsLength
     *                                                                                                                                the shortest keyword worth searching for; null asks the configuration
     *
     * @return SqlFragment fields conditions for keywords, and the values they bind
     */
    public function buildKeywordsConditions($pKeywords, $pSearchFields, $pMinKeywordsLength): SqlFragment
    {
        $vParsedKeywords = $this->parseKeywords($pKeywords, $pMinKeywordsLength);

        if ((count($vParsedKeywords['CNF']) == 0 && count($vParsedKeywords['excludeds']) == 0) || count($pSearchFields) == 0) {
            return SqlFragment::empty();
        }

        $vANDs = [];

        foreach ($vParsedKeywords['CNF'] as $vAND) {
            $vORs = [];

            foreach ($vAND as $vOR) {
                $vIsRegExp = $this->isRegExp($vOR) !== 0;

                foreach ($pSearchFields as $vFieldName => $vField) {
                    foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                        $vORRequest = SqlFragment::empty();

                        switch ($vFieldDescriptor['_mode_']) {
                            case 'single':
                                $vORRequest = $this->termCondition(
                                    $this->renameJSONPathVariable($vFieldName),
                                    $vOR,
                                    $vIsRegExp,
                                    false
                                );

                                break;

                            case 'multiple':
                                $vORRequest = $this->splitValueCondition(
                                    $this->renameJSONPathVariable($vFieldName),
                                    $vOR,
                                    $vIsRegExp,
                                    false
                                );

                                break;
                        }

                        if ($vField['hasMultipleStructures']) {
                            $vORRequest = $this->restrictedToForms($vORRequest, $vFieldDescriptor['_ids_']);
                        }

                        if (!$vORRequest->isEmpty()) {
                            $vORs[] = $vORRequest;
                        }
                    }
                }
            }

            if (count($vORs) > 0) {
                $vANDs[] = SqlFragment::all(' OR ', ...$vORs)->wrappedIn('(', ')');
            }
        }

        foreach ($vParsedKeywords['excludeds'] as $vExcluded) {
            $vIsRegExp = $this->isRegExp($vExcluded) !== 0;

            foreach ($pSearchFields as $vFieldName => $vField) {
                $vExcludedRequest = SqlFragment::empty();

                foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                    switch ($vFieldDescriptor['_mode_']) {
                        case 'single':
                            $vExcludedRequest = $this->termCondition(
                                $this->renameJSONPathVariable($vFieldName),
                                $vExcluded,
                                $vIsRegExp,
                                true
                            );

                            break;

                        case 'multiple':
                            $vExcludedRequest = $this->splitValueCondition(
                                $this->renameJSONPathVariable($vFieldName),
                                $vExcluded,
                                $vIsRegExp,
                                true
                            );

                            break;
                    }

                    if ($vField['hasMultipleStructures']) {
                        $vExcludedRequest = $this->restrictedToForms($vExcludedRequest, $vFieldDescriptor['_ids_']);
                    }

                    if (!$vExcludedRequest->isEmpty()) {
                        $vANDs[] = $vExcludedRequest;
                    }
                }
            }
        }

        return self::uniqueFragments(' AND ', $vANDs);
    }

    /** `<column> <op> <term>` for a single-valued field -- the leaf of a keyword condition. */
    private function termCondition(string $column, string $term, bool $isRegExp, bool $negated): SqlFragment
    {
        $collate = $this->dbService->collateClause();

        if ($isRegExp) {
            return SqlFragment::of(
                "{$column} {$collate} " . $this->dbService->regexpOperator($negated) . ' ?',
                [$this->extractRegExp($term)]
            );
        }

        return SqlFragment::of(
            "{$column} {$collate} " . ($negated ? 'NOT LIKE' : 'LIKE') . ' ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains($term)]
        );
    }

    /**
     * The same test against a comma-separated field, which the CTEs above have split into one `(champ, elt)` row per value -- so the column name is matched as a *value* here.
     */
    private function splitValueCondition(string $column, string $term, bool $isRegExp, bool $negated): SqlFragment
    {
        $collate = $this->dbService->collateClause();

        if ($isRegExp) {
            return SqlFragment::of(
                "(s.champ = ? AND s.elt {$collate} " . $this->dbService->regexpOperator($negated) . ' ?)',
                [$column, '^' . $this->extractRegExp($term) . '$']
            );
        }

        return SqlFragment::of(
            "(s.champ = ? AND s.elt {$collate} " . ($negated ? 'NOT LIKE' : 'LIKE')
            . ' ?' . SqlParameters::LIKE_CLAUSE_SUFFIX . ')',
            [$column, SqlParameters::likeContains($term)]
        );
    }

    /**
     * Narrow a condition to the forms whose structure it was built for.
     *
     * @param list<int|string> $formIds
     */
    private function restrictedToForms(SqlFragment $condition, array $formIds): SqlFragment
    {
        if ($condition->isEmpty() || $formIds === []) {
            return $condition;
        }

        $ids = implode(', ', array_map(static fn ($id): string => (string)(int)$id, $formIds));

        return SqlFragment::all(
            ' AND ',
            SqlFragment::of($this->renameJSONPathVariable('form_id') . " IN ({$ids})"),
            $condition
        )->wrappedIn('( ', ')');
    }

    /**
     * Join fragments, dropping duplicates.
     *
     * @param list<SqlFragment> $fragments
     */
    private static function uniqueFragments(string $glue, array $fragments): SqlFragment
    {
        $seen = [];
        $kept = [];
        foreach ($fragments as $fragment) {
            $key = $fragment->sql . '|' . serialize($fragment->params);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = $fragment;
        }

        return SqlFragment::all($glue, ...$kept);
    }

    /**
     * Build the SQL fields conditions for queries.
     *
     * @param array<int|string, mixed>                                                                            $pQueries
     *                                                                                                                      <query> = [ "name" => <string>, "operator" => <string>, "values" => <array of strings> ]
     * @param array<string, array{descriptors: array<string, array<string, mixed>>, hasMultipleStructures: bool}> $pFields
     *                                                                                                                      field name => its descriptors, as prepareSearchRequest() builds them
     *
     * @return SqlFragment fields conditions for queries, and the values they bind
     */
    public function buildQueriesConditions($pQueries, $pFields): SqlFragment
    {
        $vQueriesConditions = [];

        foreach ($pQueries as $vQuery) {
            $vFieldName = $vQuery['name'];

            $vOperator = $vQuery['operator'];

            $vField = $pFields[$vFieldName];

            $vQueryConditions = [];

            switch ($vOperator) {
                case '==':
                    $vRegExpOperator = $this->dbService->regexpOperator();
                    $vComparisonOperator = '=';
                    $vFindInSetNot = false;
                    break;
                case '!=':
                    $vRegExpOperator = $this->dbService->regexpOperator(true);
                    $vComparisonOperator = '!=';
                    $vFindInSetNot = true;
                    break;
                case '<':
                    $vRegExpOperator = $this->dbService->regexpOperator();
                    $vComparisonOperator = '<';
                    $vFindInSetNot = false;
                    break;
                case '>':
                    $vRegExpOperator = $this->dbService->regexpOperator();
                    $vComparisonOperator = '>';
                    $vFindInSetNot = false;
                    break;
                case '<=':
                    $vRegExpOperator = $this->dbService->regexpOperator();
                    $vComparisonOperator = '<=';
                    $vFindInSetNot = false;
                    break;
                case '>=':
                    $vRegExpOperator = $this->dbService->regexpOperator();
                    $vComparisonOperator = '>=';
                    $vFindInSetNot = false;
                    break;
                default:
                    throw new \Exception($vOperator . ' is not recognized');
            }

            foreach ($vField['descriptors'] as $vHash => $vDescriptor) {
                $vValueConditions = [];

                foreach ($vQuery['values'] as $vValue) {
                    $vIsRegExp = $this->isRegExp($vValue) !== 0;

                    switch ($vDescriptor['_mode_']) {
                        case 'single':
                            if ($vIsRegExp) {
                                $vValueConditions[] = SqlFragment::of(
                                    $this->renameJSONPathVariable($vFieldName) . ' ' . $this->dbService->collateClause() . " {$vRegExpOperator} ?",
                                    [$this->extractRegExp($vValue)]
                                );
                            } else {
                                if ($vDescriptor['_type_'] == 'number') {
                                    if (isset($vValue) && trim($vValue) !== '') {
                                        $vValueConditions[] = SqlFragment::of(
                                            'CAST(' . $this->renameJSONPathVariable($vFieldName) . " AS DOUBLE) {$vComparisonOperator} ?",
                                            [(string)$vValue]
                                        );
                                    } else {
                                        $vValueConditions[] = SqlFragment::of(
                                            '(' . $this->renameJSONPathVariable($vFieldName) . ' ' . $this->dbService->collateClause() . " {$vComparisonOperator} '' )"
                                        );
                                    }
                                } else {
                                    $vValueConditions[] = SqlFragment::of(
                                        $this->renameJSONPathVariable($vFieldName) . ' ' . $this->dbService->collateClause() . " {$vComparisonOperator} ?",
                                        [$vValue]
                                    );
                                }
                            }

                            break;

                        case 'multiple':
                            if ($vIsRegExp) {
                                $vValueConditions[] = SqlFragment::of(
                                    '(s.champ = ? AND s.elt ' . $this->dbService->collateClause() . " {$vRegExpOperator} ?)",
                                    [$this->renameJSONPathVariable($vFieldName), $this->extractRegExp($vValue)]
                                );
                            } else {
                                $haystack = $this->renameJSONPathVariable($vFieldName);
                                $vValueConditions[] = SqlFragment::of(
                                    $this->dbService->findInSet('?', $haystack, $vFindInSetNot),
                                    [$vValue]
                                );
                            }

                            break;

                        case self::MISSING_FIELD:
                        case self::MISSING_PROPERTY:
                            $vValueConditions[] = SqlFragment::of(($vComparisonOperator === '!=') ? 'TRUE' : 'FALSE');

                            break;
                    }
                }

                $vDescriptorCondition = SqlFragment::all(
                    $vComparisonOperator === '!=' ? ' AND ' : ' OR ',
                    ...$vValueConditions
                );

                if ($vField['hasMultipleStructures'] && !$vDescriptorCondition->isEmpty()) {
                    $ids = implode(', ', array_map(static fn ($id): string => (string)(int)$id, $vDescriptor['_ids_']));
                    $vDescriptorCondition = SqlFragment::all(
                        ' AND ',
                        SqlFragment::of($this->renameJSONPathVariable('form_id') . " IN ({$ids})"),
                        $vDescriptorCondition->wrappedIn('(', ')')
                    );
                }

                if (!$vDescriptorCondition->isEmpty()) {
                    $vQueryConditions[] = $vDescriptorCondition->wrappedIn('(', ')');
                }
            }

            if (count($vQueryConditions) > 0) {
                $vQueriesConditions[] = SqlFragment::all(' OR ', ...$vQueryConditions)->wrappedIn('(', ')');
            }
        }

        return SqlFragment::all(' AND ', ...$vQueriesConditions);
    }

    /** A predicate matching the rows of a given PageType. */
    private function typedAs(string $type): SqlFragment
    {
        return SqlFragment::of('p.' . $this->dbService->quoteIdentifier('type') . ' = ?', [$type]);
    }

    /**
     * The predicate selecting the rows that belong to these forms.
     *
     * @param array<array-key, int> $formIds
     */
    private function rowsBelongingTo(array $formIds): SqlFragment
    {
        if (empty($formIds)) {
            return $this->typedAs(PageType::ENTRY);
        }

        $forms = $this->container->get(FormManager::class)->getMany($formIds);

        $idsByContentType = [];
        foreach ($formIds as $formId) {
            $contentType = $forms[$formId][ContentTypeSchema::CONTENT_TYPE] ?? ContentTypeSchema::TYPE_ENTRY;
            $idsByContentType[(string)$contentType][] = $formId;
        }

        $clauses = [];
        foreach ($idsByContentType as $contentType => $ids) {
            switch ($contentType) {
                case ContentTypeSchema::TYPE_PAGE:
                    $clauses[] = $this->typedAs(PageType::PAGE);
                    break;
                case ContentTypeSchema::TYPE_USER:
                    $clauses[] = $this->typedAs(PageType::USER);
                    break;
                case ContentTypeSchema::TYPE_FILE:
                    $clauses[] = $this->typedAs(PageType::FILE);
                    break;
                default:
                    $clauses[] = SqlFragment::all(
                        ' AND ',
                        $this->typedAs(PageType::ENTRY),
                        SqlFragment::of(
                            $this->dbService->jsonExtract('body', '$.form_id')
                            . ' IN (' . implode(', ', array_map(static fn ($formId): string => "'" . (int)$formId . "'", $ids)) . ')'
                        )
                    )->wrappedIn('(', ')');
                    break;
            }
        }

        return count($clauses) === 1
            ? $clauses[0]
            : SqlFragment::all(' OR ', ...$clauses)->wrappedIn('(', ')');
    }

    /**
     * Return the request for searching entries in database.
     *
     * @param array<string, mixed> &$params
     *
     * @return SqlFragment the whole statement and the values it binds (ticket 31). An empty
     *                     fragment means "this search cannot match anything" -- the caller must
     *                     not run it, exactly as the empty string used to mean.
     */
    public function prepareSearchRequest(&$params = [], bool $filterOnReadACL = false, bool $applyOnAllRevisions = false): SqlFragment
    {
        $params = array_merge(
            [
                'queries' => [],
                'formsIds' => [],
                'user' => '',
                'minDate' => '',
                'fieldmapping' => '',
            ],
            $params,
        );

        $vKeywords = $params['keywords'] ?? '';

        $vQueries = $this->parseQuery($params['queries']);

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
        } else {
            $vFormIDs = [];
        }

        $vIDsRequest = $this->rowsBelongingTo($vFormIDs);

        $vPeriodRequest = empty($params['minDate'])
            ? SqlFragment::empty()
            : SqlFragment::of($this->dbService->quoteIdentifier('time') . ' >= ?', [$params['minDate']]);

        $vUserRequest = empty($params['user'])
            ? SqlFragment::empty()
            : SqlFragment::of('owner = ?', [$params['user']]);

        $vKeywordsFields = [];
        $vQueriesFields = [];

        if ($vKeywords != '') {
            $vSearchFields = isset($params['searchfields'])
                                ? is_array($params['searchfields'])
                                    ? $params['searchfields']
                                    : explode(',', $params['searchfields'])
                                : [];

            $vSearchFields[] = PageBody::TITLE;
            $vSearchFields[] = 'bf_titre';

            $vKeywordsFields = array_unique(array_map('trim', $vSearchFields));
        }

        foreach ($vQueries as $vQuery) {
            $vQueriesFields[] = $vQuery['name'];
        }

        $vNecessaryFields = array_unique(array_merge($vKeywordsFields, $vQueriesFields));

        $vFields = [];

        $vFieldDescriptor = ['_mode_' => 'single', '_type_' => 'string'];

        $vHash = $this->buildFieldDescriptorHash($vFieldDescriptor);

        $vFields['tag']
        = [
            'needSplit' => false,
            'hasMultipleStructures' => false,
            'isExtracted' => false,
            'isSplitted' => false,
            'descriptors' => [$vHash => array_merge($vFieldDescriptor, ['_ids_' => $vFormIDs])],
        ];

        $vFormManager = $this->container->get(FormManager::class);

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
                                if (is_array($vCurrentArray) && array_key_exists($vJSONPathSegment, $vCurrentArray)) {
                                    $vCurrentArray = $vCurrentArray[$vJSONPathSegment];
                                } else {
                                    $vFieldFound = false;
                                }
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
            $this->dbService->jsonExtract('body', '$.form_id') . ' AS ' . $this->renameJSONPathVariable('form_id'),
        ];

        foreach ($vFields as $vFieldName => $vField) {
            if (!$vField['isExtracted']) {
                $vSQLNom = $vFieldName;
                $vRenamedSQLNom = $this->renameJSONPathVariable($vFieldName);

                $vSelectRequest[] = $this->dbService->jsonExtract('body', '$.' . $vSQLNom) . ' AS ' . $vRenamedSQLNom;

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

            $vSplitteds[] = 'SELECT id, champ, elt FROM ' . $this->renameJSONPathVariable($vFieldName) . '_multiple';

            $vSplittedsRequest
                        .= ', ' . $this->renameJSONPathVariable($vFieldName) . '_multiple AS '
                        . '( '
                            . 'SELECT '
                                . 'id, '
                                . '\'' . $this->renameJSONPathVariable($vFieldName) . '\' AS champ, '
                                . 'TRIM(SUBSTRING_INDEX(' . $this->renameJSONPathVariable($vFieldName) . ', \',\', 1)) AS elt, '
                                . 'CASE '
                                    . 'WHEN INSTR(' . $this->renameJSONPathVariable($vFieldName) . ', \',\') = 0 THEN \'\' '
                                    . 'ELSE SUBSTR(' . $this->renameJSONPathVariable($vFieldName) . ', INSTR(' . $this->renameJSONPathVariable($vFieldName) . ', \',\') + 1) '
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
                            . 'FROM ' . $this->renameJSONPathVariable($vFieldName) . '_multiple '
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

        $vWhereRequest = SqlFragment::empty();

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

        $vQueriesConditions = $this->buildQueriesConditions($vQueries, $vFields);

        if (str_contains($vQueriesConditions->sql, '((FALSE))')) {
            return SqlFragment::empty();
        }

        $vAclRequest = (!$this->aclService->isAdmin() && $filterOnReadACL)
            ? $this->aclService->updateRequestWithACL()
            : SqlFragment::empty();

        $vWhereRequest = SqlFragment::all(' AND ', $vKeywordsConditions, $vQueriesConditions, $vAclRequest);

        $vInnerFilters = SqlFragment::all(
            ' AND ',
            SqlFragment::of(($applyOnAllRevisions ? '' : "latest='Y' AND ") . "p.parent = ''"),
            $vUserRequest,
            $vPeriodRequest,
            $vIDsRequest
        );

        $vCompleteRequest = SqlFragment::all(
            '',
            SqlFragment::of('WITH RECURSIVE filteredPages AS ( SELECT ' . $vSelectRequest
                . ' FROM ' . $this->dbService->prefixTable('pages') . ' p '
                . 'WHERE '),
            $vInnerFilters,
            SqlFragment::of(')' . ($vSplittedsRequest != '' ? $vSplittedsRequest . ' ' : ' ')
                . 'SELECT DISTINCT f.* FROM filteredPages f '
                . ($vSplittedsCount > 0 ? 'LEFT JOIN all_multiples s ON s.id = f.id ' : '')),
            $vWhereRequest->wrappedIn('WHERE ', '')
        );

        if (isset($_GET['showreq'])) {
            echo '<hr><code style="width:100%;height:100px;">'
                . SqlParameters::interpolateForDisplay($vCompleteRequest->sql, $vCompleteRequest->params)
                . '</code><hr>';
        }

        return $vCompleteRequest;
    }

    /**
     * Return an array of fiches based on search parameters.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, array<string, mixed>> the matching entries, keyed by tag
     */
    public function search($params = [], bool $filterOnReadACL = false, bool $useGuard = false): array
    {
        $requete = $this->prepareSearchRequest($params, $filterOnReadACL);

        $searchResults = [];
        if ($requete->isEmpty()) {
            return $searchResults;
        }
        $results = $this->dbService->loadAll($requete->sql, $requete->params);
        $debug = (bool)$this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('debug');

        $vPageManager = $this->container->get(PageManager::class);
        $vEntryManager = $this->container->get(EntryManager::class);

        foreach ($results as $page) {
            $page['body'] = PageBody::decode($page['body'] ?? null);

            if (!isset($page['body']['form_id'])) {
                $resolver = $this->container->get(ContentTypeResolver::class);

                $shaped = $resolver->asEntry($page, $resolver->formBacked((string)($page['type'] ?? '')));
                if ($shaped === null) {
                    continue;
                }
                $page['body'] = $shaped;
            }

            $vPageManager->cacheOwner($page);

            $filteredPage = (!$this->aclService->isAdmin() && $useGuard)
                ? $this->container->get(Guard::class)->checkAcls($page, $page['tag'])
                : $page;
            $data = $vEntryManager->getDataFromPage($filteredPage, false, $debug, $params['fieldmapping'] ?? '');
            $data['-is-external-'] = '0';
            $searchResults[$data['tag']] = $data;
        }

        $this->fillMissingCreationTimes($searchResults);

        return $searchResults;
    }

    /**
     * When the Contents that do not say so in their body were created.
     *
     * @param array<string, array<string, mixed>> $entries keyed by tag, as search() builds them
     */
    private function fillMissingCreationTimes(array &$entries): void
    {
        $missing = [];
        foreach ($entries as $tag => $entry) {
            if (empty($entry['created_at'])) {
                $missing[] = (string)$tag;
            }
        }
        if ($missing === []) {
            return;
        }

        $timeCol = $this->dbService->quoteIdentifier('time');
        $placeholders = implode(', ', array_fill(0, count($missing), '?'));
        $rows = $this->dbService->loadAll(
            "SELECT tag, MIN({$timeCol}) AS created_at FROM " . $this->dbService->prefixTable('pages')
            . " WHERE tag IN ({$placeholders}) AND parent = '' GROUP BY tag",
            $missing
        );

        foreach ($rows as $row) {
            $tag = (string)($row['tag'] ?? '');
            if (isset($entries[$tag])) {
                $entries[$tag]['created_at'] = $row['created_at'];
            }
        }
    }

    /**
     * Parse a keywords search string Keywords search string are composed of tokens Tokens can be single words (without space) or expression composed of several words seperated by spaces enclosed in quote or double quote.
     *
     * @param mixed $pKeywords the keywords search string. Typed `mixed` and guarded below
     *                         rather than declared `string`: the only caller is the public
     *                         `buildKeywordsConditions()`, which is untyped and reached from
     *                         request data, so the guard is load-bearing (ticket 40)
     *
     * @return array<string, mixed> an associative array containing the keys:
     *                              - CNF =	the Conjonctive Normal Form (= [a OR b] AND [d or e]) of the keywords search string
     *                              (ie : an AND-array of OR-arrays)
     *                              - excludeds = <array> an array of excluded tokens
     */
    private function parseKeywords($pKeywords, ?int $pMinKeywordLength = null)
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
                '/(-)?("(?:\\\\.|[^"\\\\])*"|' .
                '\'(?:\\\\.|[^\'\\\\])*\'|' .
                '\S+)/u',
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
     * @param string|array<string, mixed> $pQuery the query string, or the already parsed array
     *
     * @return array<int, array<string, mixed>> one entry per query, each
     *                                          ["name" => string, "operator" => string, "values" => list<string>]
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
                                        $vValue = $this->container->get(AuthenticationService::class)->getLoggedUserName();
                                        break;
                                    case 'user.entry.tag':
                                        $vUserManager = $this->container->get(UserManager::class);
                                        $entry = $vUserManager->getAssociatedEntry();
                                        if (!empty($entry)) {
                                            $vValue = $entry['tag'];
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
     * @return int the minimum search keywords length
     */
    public function getMinSearchKeywordLength()
    {
        $vMinimumSearchKeywordLength = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('min_search_keyword_length');

        if (empty($vMinimumSearchKeywordLength)) {
            $vMinimumSearchKeywordLength = MIN_SEARCH_KEYWORD_LENGTH;
        }

        $vMinimumSearchKeywordLength = intval($vMinimumSearchKeywordLength);

        return $vMinimumSearchKeywordLength;
    }

    /**
     * @param array<string, mixed> $pParameters
     */
    public function paramsToURLSearchParams($pParameters): string
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

        if (isset($pParameters['fieldmapping'])) {
            $vFieldMappings = $pParameters['fieldmapping'];

            $vParameters[] = 'fieldmapping=' . urlencode(is_array($vFieldMappings)
                    ? implode(',', array_map(function ($pName) use ($vFieldMappings) {
                        return $pName . '=' . trim($vFieldMappings[$pName]);
                    }, array_keys($vFieldMappings)))
                    : $vFieldMappings);
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

        if (isset($pParameters['order'])) {
            $vParameters[] = 'order=' . trim($pParameters['order']);
        }

        if (isset($pParameters['field'])) {
            $vParameters[] = 'field=' . trim($pParameters['field']);
        }

        return implode('&', array_filter($vParameters, function ($pParameter) {
            return !empty($pParameter);
        }));
    }

    /**
     * Transform a query to a string.
     *
     * @param array<array-key, mixed>|string|null $pQuery the query in different format :
     *                                                    new array format [ [ "name" => "bf_field", "operator" => "==" , values [ "toto", ... ] ], ... ]
     *                                                    OR
     *                                                    old array format : [ "bf_field" => "toto", "bf_field2!" => "tata" ]
     *                                                    OR
     *                                                    new string format : bf_field == toto1 | bf_field2 <= tata
     *                                                    OR
     *                                                    old string format bf_field=toto1|bf_field2!=tata
     *
     * @return string the string representing the query
     */
    public function queryToString($pQuery): string
    {
        if ($pQuery === null) {
            return '';
        }

        if (!is_array($pQuery)) {
            return $pQuery;
        }

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
    }

    /**
     * @param array<string, mixed>|string $pKeywords the parsed keywords, or an already-formatted search string
     */
    public function keywordsToString($pKeywords): string
    {
        if (is_string($pKeywords)) {
            return $pKeywords;
        }

        $vResult = [];

        $vResult[] = implode('|', array_map(function ($pORs) {
            return implode(',', $pORs);
        }, $pKeywords['CNF']));

        $vResult[] = trim(implode(' ', array_map(function ($pExcluded) {
            return is_array($pExcluded) ? implode('-', $pExcluded) : (string)$pExcluded;
        }, $pKeywords['excludeds'] ?? [])));

        return implode(' ', $vResult);
    }

    /**
     * Aggregate keywords.
     *
     * @param mixed ...$pArguments
     *                             <argument> as
     *                             <string> keywords specification
     *                             OR
     *                             null
     *
     * @return string aggregated keywords
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
    }

    /**
     * Aggregate queries.
     *
     * @param mixed ...$pArguments
     *                             <argument> as
     *                             <array> argument array containing "query"
     *                             <string> a query string
     *                             null
     *
     * @return string aggregated queries
     */
    public function aggregateQueries(...$pArguments): string
    {
        $vQueries = [];

        foreach ($pArguments as $vArgument) {
            if (isset($vArgument)) {
                $vQuery = '';
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
    }

    /**
     * Normalise une chaîne : - met en minuscules (Unicode-safe) - transforme les caractères accentués en leur équivalent non accentué - gère les ligatures courantes (œ, æ, ß, etc.).
     *
     * @param string $s chaîne d'entrée (n'importe quel encodage détectable)
     *
     * @return string chaîne lowercase, sans accents
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
     * Test if a string represents a regexp A string is considered as a regexp : if it contains at least one ".*" or if it begins and ends with "/".
     *
     * @param string $pString the string to test
     *
     * @return int 0 if the string is not a regexp, 1 for the old YesWiki format (dot-star
     *             around the term), 2 for the MySQL format (the term wrapped in slashes)
     */
    private function isRegExp($pString): int
    {
        if (mb_substr($pString, 0, 1) == '/' && mb_substr($pString, -1, 1) == '/') {
            return 2;
        } elseif (preg_match('/\.\*/', $pString) == 1) {
            return 1;
        }

        return 0;
    }

    /**
     * Extract and transform a regexp string from a string recognized by isRegExp as a regexp + It removes beginning and ending "/" if it exists + Optionnaly, it add alternatives for each character that has an accented version.
     *
     * @param string $pString            a regexp string recognized by isRegExp as a regexp
     * @param bool   $pAccentInsensitive true to make the regexp accent insensitive
     *
     * @return string the transformed regexp string
     */
    private function extractRegExp($pString, $pAccentInsensitive = true)
    {
        $vString = $pString;

        switch ($this->isRegExp($pString)) {
            case 0:
                throw new \Exception($pString . ' is not a regexp');
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
     * Build a hash from structure definition The hash is a facility for associative array search.
     *
     * @param array<string, mixed> $pStructure the structure
     *
     * @return string the hash
     */
    private function buildFieldDescriptorHash($pStructure)
    {
        return $pStructure['_mode_'] ?? '#|' . $pStructure['_type_'] ?? '#';
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
        $renamed = self::asSafeIdentifier(str_replace('.', '__', (string)$pPath));

        return in_array(strtolower($renamed), self::PAGES_COLUMNS, true)
            ? self::FIELD_COLUMN_PREFIX . $renamed
            : $renamed;
    }

    /** A field name reduced to something that is certainly a SQL identifier. */
    private static function asSafeIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1) {
            return $name;
        }

        $reduced = (string)preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        if ($reduced === '' || preg_match('/^[0-9]/', $reduced) === 1) {
            $reduced = '_' . $reduced;
        }

        return substr($reduced, 0, 40) . '_' . substr(md5($name), 0, 8);
    }
}

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
    protected $dbService;
    protected $aclService;

    public const MISSING_PROPERTY = '_MISSING_PROPERTY_';
    public const MISSING_FIELD = '_MISSING_FIELD_';

    /**
     * What `p.*` brings into the search CTE, and therefore the names a field's column may not
     * take. See renameJSONPathVariable().
     */
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
            $needle,
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
            $needle,
        );

        return $needle;
    }

    /**
     * Build the SQL fields conditions for keywords.
     *
     * @param string $pKeywords the keywords search string in the format:
     *                          <keywords>       = ( <token> | <exluded token> )+ [ "|" <keywords> ]
     *                          <token>          = <string without space>	|
     *                          "'" <string with spaces between single quotes> "'" |
     *                          '"' <string with spaces between double quotes> '"'
     *                          <excluded token> = "-" <token>
     *
     * 	 example : toto -"tata tutu" | "titi tutu" tete -tyty
     *				=
     *            "toto" AND ("titi tutu" OR "tete") AND NOT "tata tutu" AND NOT "tyty"
     *
     *   NOTE : position of excluded fields has no signification
     * @param array<string, array{descriptors: array<string, array<string, mixed>>, hasMultipleStructures: bool}> $pSearchFields
     *                                                                                                                           field name => its descriptors. The shape was undocumented and the tag
     *                                                                                                                           that stood here was unparseable, so nothing downstream of it was checked
     *                                                                                                                           (ticket 40).
     *
     * @return SqlFragment fields conditions for keywords, and the values they bind
     */
    public function buildKeywordsConditions($pKeywords, $pSearchFields, $pMinKeywordsLength): SqlFragment
    {
        // Let's parse the given keywords search string...

        $vParsedKeywords = $this->parseKeywords($pKeywords, $pMinKeywordsLength);

        // if there is nothing to do, there is nothing to do

        if ((count($vParsedKeywords['CNF']) == 0 && count($vParsedKeywords['excludeds']) == 0) || count($pSearchFields) == 0) {
            return SqlFragment::empty();
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

                $vIsRegExp = $this->isRegExp($vOR) !== 0;

                // For each ORs token, we will build a condition that apply on each search field

                foreach ($pSearchFields as $vFieldName => $vField) {
                    // We need to build a specific condition for each field structure

                    foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                        $vORRequest = SqlFragment::empty();

                        switch ($vFieldDescriptor['_mode_']) {
                            // If this field instance in is intended to store a single value...

                            case 'single':
                                // Add a field condition adapted to a regexp or not

                                $vORRequest = $this->termCondition(
                                    $this->renameJSONPathVariable($vFieldName),
                                    $vOR,
                                    $vIsRegExp,
                                    false
                                );

                                break;

                                // If this field instance is intended to store multiple values separated by comma...

                            case 'multiple':
                                // Add a field condition adapted to a regexp or not

                                $vORRequest = $this->splitValueCondition(
                                    $this->renameJSONPathVariable($vFieldName),
                                    $vOR,
                                    $vIsRegExp,
                                    false
                                );

                                break;
                        }

                        // If the field can have multiple structures, we need to specify the form IDs to which the condition apply

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
            // Remember if the excluded token value is a regexp

            $vIsRegExp = $this->isRegExp($vExcluded) !== 0;

            // For each excluded token, we will build a condition that apply on each search field

            foreach ($pSearchFields as $vFieldName => $vField) {
                // The condition we will construct

                $vExcludedRequest = SqlFragment::empty();

                // We need to build a specific condition for each field structure

                foreach ($vField['descriptors'] as $vHash => $vFieldDescriptor) {
                    switch ($vFieldDescriptor['_mode_']) {
                        // If this field instance is intended to store a single value...

                        case 'single':
                            // Add a field condition adapted to a regexp or not

                            $vExcludedRequest = $this->termCondition(
                                $this->renameJSONPathVariable($vFieldName),
                                $vExcluded,
                                $vIsRegExp,
                                true
                            );

                            break;

                            // If this field instance is intended to store multiple values separated by comma...

                        case 'multiple':
                            // Add a field condition adapted to a regexp or not

                            $vExcludedRequest = $this->splitValueCondition(
                                $this->renameJSONPathVariable($vFieldName),
                                $vExcluded,
                                $vIsRegExp,
                                true
                            );

                            break;
                    }

                    // If the field can have multiple structures, we need to specify the form IDs to which the condition apply

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

    /**
     * `<column> <op> <term>` for a single-valued field -- the leaf of a keyword condition.
     *
     * $negated picks the NOT form. A regexp term is matched with the driver's REGEXP operator
     * and an ordinary one with LIKE, whose wildcards are defused: the term came from a search
     * box, so `100%` means those four characters (SqlParameters::likeContains).
     */
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
     * The same test against a comma-separated field, which the CTEs above have split into one
     * `(champ, elt)` row per value -- so the column name is matched as a *value* here.
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
     * The form ids are cast to int rather than bound: they index into `$vFields`' descriptors,
     * so they are this wiki's own numbering, and the cast is what makes that true rather than
     * assumed.
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
     * `array_unique()` did this while conditions were strings. Two fragments are the same
     * condition when both their SQL and their values match -- comparing the SQL alone would
     * collapse `tag = ?` against a different tag, which is how a deduplicating rewrite
     * silently changes what a query means.
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
     * @param array<int|string, mixed> $pQueries
     *                                           <query> = [ "name" => <string>, "operator" => <string>, "values" => <array of strings> ]
     *
     * @return SqlFragment fields conditions for queries, and the values they bind
     */
    public function buildQueriesConditions($pQueries, $pFields): SqlFragment
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
                    $vRegExpOperator = $this->dbService->regexpOperator(); // Should not be used or not yet implemented
                    $vComparisonOperator = '<';
                    $vFindInSetNot = false; // Should not be used or not yet implemented
                    break;
                case '>':
                    $vRegExpOperator = $this->dbService->regexpOperator(); // Should not be used or not yet implemented
                    $vComparisonOperator = '>';
                    $vFindInSetNot = false; // Should not be used or not yet implemented
                    break;
                case '<=':
                    $vRegExpOperator = $this->dbService->regexpOperator(); // Should not be used or not yet implemented
                    $vComparisonOperator = '<=';
                    $vFindInSetNot = false; // Should not be used or not yet implemented
                    break;
                case '>=':
                    $vRegExpOperator = $this->dbService->regexpOperator(); // Should not be used or not yet implemented
                    $vComparisonOperator = '>=';
                    $vFindInSetNot = false; // Should not be used or not yet implemented
                    break;
                default:
                    throw new \Exception($vOperator . ' is not recognized');
            }

            // We need to add conditions that take into account all the possible structures
            // that may have the field depending on which form it belongs

            // So, for each structure...

            foreach ($vField['descriptors'] as $vHash => $vDescriptor) {
                // Build the condition for each value specified in the request ("comma separated values")

                $vValueConditions = [];

                foreach ($vQuery['values'] as $vValue) {
                    // Remember if the value is a regexp

                    $vIsRegExp = $this->isRegExp($vValue) !== 0;

                    switch ($vDescriptor['_mode_']) {
                        // If the field is intended to store a single value...

                        case 'single':
                            // It the value is a regexp, let's build a condition that match (or NOT) the regexp

                            if ($vIsRegExp) {
                                $vValueConditions[] = SqlFragment::of(
                                    $this->renameJSONPathVariable($vFieldName) . ' ' . $this->dbService->collateClause() . " {$vRegExpOperator} ?",
                                    [$this->extractRegExp($vValue)]
                                );
                            }

                            // else let's just compare using the appropriated comparison operator

                            else {
                                if ($vDescriptor['_type_'] == 'number') {
                                    if (isset($vValue) && trim($vValue) !== '') {
                                        // bound as a *string* still: the column holds JSON text and the CAST
                                        // is what makes it numeric, so sending a PHP int here would change
                                        // which side of the comparison is converted
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

                            // If the field is intended to store multiple values separated by comma...

                        case 'multiple':
                            // It the value is a regexp, let's build a condition that match (or NOT) the regexp in the list of values extracted in temporary tables earlier

                            if ($vIsRegExp) {
                                $vValueConditions[] = SqlFragment::of(
                                    '(s.champ = ? AND s.elt ' . $this->dbService->collateClause() . " {$vRegExpOperator} ?)",
                                    [$this->renameJSONPathVariable($vFieldName), $this->extractRegExp($vValue)]
                                );
                            } else { // else let's just check in the value belongs (or NOT) to the set of values
                                $haystack = $this->renameJSONPathVariable($vFieldName);
                                $vValueConditions[] = SqlFragment::of(
                                    $this->dbService->findInSet('?', $haystack, $vFindInSetNot),
                                    [$vValue]
                                );
                            }

                            break;

                            // The field is missing : we need to add a specific condition

                        case self::MISSING_FIELD:
                        case self::MISSING_PROPERTY:
                            // For negative operators (!=), entries without this field trivially
                            // satisfy the condition (they can't have the excluded value)
                            $vValueConditions[] = SqlFragment::of(($vComparisonOperator === '!=') ? 'TRUE' : 'FALSE');

                            break;
                    }
                }

                // Merge all value conditions: OR for positive matches (==), AND for negative (!=)
                // For !=: "field NOT IN (A,B,C)" = "!=A AND !=B AND !=C", not OR which would always be true

                $vDescriptorCondition = SqlFragment::all(
                    $vComparisonOperator === '!=' ? ' AND ' : ' OR ',
                    ...$vValueConditions
                );

                // if we had remembered that this field can have multiple structures
                // we need to specify the form IDs that use this structure in the condition request

                if ($vField['hasMultipleStructures'] && !$vDescriptorCondition->isEmpty()) {
                    $ids = implode(', ', array_map(static fn ($id): string => (string)(int)$id, $vDescriptor['_ids_']));
                    $vDescriptorCondition = SqlFragment::all(
                        ' AND ',
                        SqlFragment::of($this->renameJSONPathVariable('form_id') . " IN ({$ids})"),
                        $vDescriptorCondition->wrappedIn('(', ')')
                    );
                }

                // Add the structure conditions to the field conditions

                if (!$vDescriptorCondition->isEmpty()) {
                    $vQueryConditions[] = $vDescriptorCondition->wrappedIn('(', ')');
                }
            }

            // Merge all the field conditions with a logical OR

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
     * Which rows a form owns is decided by its **Content type** (ticket 10), not by a
     * form_id: a bazar form owns the `entry` rows carrying its id, the User form owns the
     * rows typed `user`, the File form the ones typed `file`, and the Page form the ones
     * typed `page`. Reading a form's id out of `body.form_id` only ever worked for bazar
     * entries, which is why a list of Pages came back empty however it was written.
     *
     * With no form filter at all, a search means what it has always meant: every bazar
     * entry in the wiki.
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
     * @param array &$params
     *
     * @return SqlFragment the whole statement and the values it binds (ticket 31). An empty
     *                     fragment means "this search cannot match anything" -- the caller must
     *                     not run it, exactly as the empty string used to mean.
     */
    public function prepareSearchRequest(&$params = [], bool $filterOnReadACL = false, bool $applyOnAllRevisions = false): SqlFragment
    {
        // Merge default parameters with given parameters

        $params = array_merge(
            [
                'queries' => [], // array of [ name => <string>, operator => <string> , values => [ <string>, ... ] ]
                'formsIds' => [], // Types de fiches (par ID de formulaire)
                'user' => '', // N'affiche que les fiches d'un utilisateur
                'minDate' => '', // Date minimale des fiches
                'fieldmapping' => '',
            ],
            $params,
        );

        // Get Keywords

        $vKeywords = $params['keywords'] ?? '';

        // Parse queries is correctly formated

        $vQueries = $this->parseQuery($params['queries']);

        // Limit the request to the specified form IDs

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
        // Limit the request depending on the date

        // `time` was compared inside DOUBLE quotes, which MySQL reads as a string and
        // PostgreSQL as an identifier -- and `time` itself is a reserved word on some drivers,
        // so both halves of that expression were driver-dependent. Bound and quoted now.
        $vPeriodRequest = empty($params['minDate'])
            ? SqlFragment::empty()
            : SqlFragment::of($this->dbService->quoteIdentifier('time') . ' >= ?', [$params['minDate']]);

        // Limit the request to a user if specified

        $vUserRequest = empty($params['user'])
            ? SqlFragment::empty()
            : SqlFragment::of('owner = ?', [$params['user']]);

        // Determine the necessary fields from searchfields and queries

        $vKeywordsFields = [];
        $vQueriesFields = [];

        if ($vKeywords != '') {
            $vSearchFields = isset($params['searchfields'])
                                ? is_array($params['searchfields'])
                                    ? $params['searchfields']
                                    : explode(',', $params['searchfields'])
                                : [];

            // every Content carries the computed `title` (ADR-0010), so a keyword search
            // always looks there; bf_titre stays too, for forms that have it and whose
            // stored field value can differ from the computed title (ticket 11)
            $vSearchFields[] = PageBody::TITLE;
            $vSearchFields[] = 'bf_titre';

            $vKeywordsFields = array_unique(array_map('trim', $vSearchFields));
        }

        foreach ($vQueries as $vQuery) {
            $vQueriesFields[] = $vQuery['name'];
        }

        $vNecessaryFields = array_unique(array_merge($vKeywordsFields, $vQueriesFields));

        // Build necessary fields infos (structures, ...)

        $vFields = [];

        // Add ID Fiche field

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

        // Each field can have differents value structures (handling mode : "single"|"multiple", and type "boolean"|"number"|"string")
        // depending on the form it belongs to
        // ex : form1 -> bf_myfield = single text value
        // 		form2 -> bf_myfield = multiple text values separated by commas
        // We need to handle it differently

        // So, first, let's get all the forms used in the request for later use

        $vFormManager = $this->container->get(FormManager::class);

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
                if (!isset($vForm['prepared'])) {
                    continue;
                }
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
                            $vFields[$vField]['descriptors'][$vHash]['_ids_'][] = $vFormID;
                        } else {
                            $vFields[$vField]['descriptors'][$vHash] = ['_mode_' => $vFieldDescriptor['_mode_'], '_type_' => $vFieldDescriptor['_type_'], '_ids_' => [$vFormID]];
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
                        $vFields[$vField]['descriptors'][$vHash]['_ids_'][] = $vFormID;
                    } else {
                        $vFields[$vField]['descriptors'][$vHash] = ['_mode_' => $vFieldDescriptor['_mode_'], '_type_' => $vFieldDescriptor['_type_'], '_ids_' => [$vFormID]];
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

        // - Retrieves all columns and extract form_id

        $vSelectRequest
        = [
            'p.*',
            $this->dbService->jsonExtract('body', '$.form_id') . ' AS ' . $this->renameJSONPathVariable('form_id'),
        ];

        // - Extract all fields ("single" and "multiple" mode)

        foreach ($vFields as $vFieldName => $vField) {
            // Extract one field

            // Check that it was not already extracted

            if (!$vField['isExtracted']) {
                // Extract it if it is not yet done

                // raw: SqlDialect::jsonExtract() escapes the path for whichever syntax it
                // is building, and pre-escaping here would double it
                $vSQLNom = $vFieldName;
                $vRenamedSQLNom = $this->renameJSONPathVariable($vFieldName);

                $vSelectRequest[] = $this->dbService->jsonExtract('body', '$.' . $vSQLNom) . ' AS ' . $vRenamedSQLNom;

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

            // And we remember it has been done

            $vField['isSplitted'] = true;
        }

        // Union of all splitted fields

        $vSplittedsCount = count($vSplitteds);

        if ($vSplittedsCount > 0) {
            $vSplittedsRequest
                        .= ', all_multiples AS '
                        . '( '
                            . implode(' UNION ALL ', $vSplitteds)
                        . ') ';
        }

        // Construct WHERE part with queries and keywords conditions

        $vWhereRequest = SqlFragment::empty();

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
                ARRAY_FILTER_USE_KEY,
            ),
            $vMinSearchKeywordLength,
        );

        // Queries conditions

        $vQueriesConditions = $this->buildQueriesConditions($vQueries, $vFields);

        // a query on a field no form has cannot match: `((FALSE))` is how that arrives here,
        // and running the statement anyway would scan the whole table to return nothing
        if (str_contains($vQueriesConditions->sql, '((FALSE))')) {
            return SqlFragment::empty();
        }

        // Optionnaly, filter on read ACL

        $vAclRequest = (!$this->aclService->isAdmin() && $filterOnReadACL)
            ? $this->aclService->updateRequestWithACL()
            : SqlFragment::empty();

        $vWhereRequest = SqlFragment::all(' AND ', $vKeywordsConditions, $vQueriesConditions, $vAclRequest);

        // Construct full request

        // The inner CTE's filters come first in the text, so their values come first in the
        // list; SqlFragment keeps each composition's own order, and this is where the two
        // compositions meet.
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
                // no type join here: which rows belong to the searched forms is
                // $vIDsRequest's business, because it depends on their Content type --
                // see rowsBelongingTo()
                . 'WHERE '),
            $vInnerFilters,
            SqlFragment::of(')' . ($vSplittedsRequest != '' ? $vSplittedsRequest . ' ' : ' ')
                . 'SELECT DISTINCT f.* FROM filteredPages f '
                . ($vSplittedsCount > 0 ? 'LEFT JOIN all_multiples s ON s.id = f.id ' : '')),
            $vWhereRequest->wrappedIn('WHERE ', '')
        );
        // debug

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
     * @param array $params
     *
     * @return mixed
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
            // raw SQL rows, so the bodies are still the stored JSON text -- decode before
            // handing them to anything that expects the one shape (ticket 09)
            $page['body'] = PageBody::decode($page['body'] ?? null);
            // a page, a user or a file carries no form_id: which form describes it is
            // decided by its Content type (ticket 10). Say so here, once, so that
            // everything downstream reads a row the one way.
            if (!isset($page['body']['form_id'])) {
                $resolver = $this->container->get(ContentTypeResolver::class);
                // `p.*` already brought the row's type along, so this costs no query
                $shaped = $resolver->asEntry($page, $resolver->formBacked((string)($page['type'] ?? '')));
                if ($shaped === null) {
                    continue;
                }
                $page['body'] = $shaped;
            }
            // save owner to reduce sql calls
            $vPageManager->cacheOwner($page);
            // not possible to init the Guard in the constructor because of circular reference problem
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
     * An entry saved through `EntryManager::create()` carries `created_at`; a page, an
     * account and a file never have, so anything asking a list for it -- a card's date, a
     * `displayfields` mapping onto it -- got nothing at all. Where it is really written is
     * the oldest revision of the row, and that is one query for the whole list: asking per
     * entry would be one query per row of a list that may hold thousands.
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
    private function parseKeywords($pKeywords, $pMinKeywordLength = null)
    {
        if ($pMinKeywordLength == null) {
            $vMinKeywordLength = $this->getMinSearchKeywordLength();
        } else {
            $vMinKeywordLength = $pMinKeywordLength;
        }

        // The default results : nothing recognized

        $vResults = ['CNF' => [], 'excludeds' => []];

        // Check if the $pKeywords parameter is valid for parsing

        if (!(is_string($pKeywords) && trim($pKeywords) != '' && $pKeywords != _t('BAZ_MOT_CLE'))) {
            return $vResults;
        }

        // Let's analyse the keywords to build a structure representing the CNF and to extract the excludeds tokens

        // Separates AND clauses

        $vANDs = array_filter(array_unique(array_map('trim', explode('|', $pKeywords))), function ($pKeyword) use ($vMinKeywordLength) {
            return strlen($pKeyword) >= $vMinKeywordLength;
        });

        foreach ($vANDs as $vAND) {
            // Extract tokens

            preg_match_all(
                '/(-)?("(?:\\\\.|[^"\\\\])*"|' .	// double quoted with optional backslash escapes
                '\'(?:\\\\.|[^\'\\\\])*\'|' .   	// single quoted
                '\S+)/u',                      	  	// or unquoted token
                $vAND,
                $vTokens,
                PREG_SET_ORDER,
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
                // For each query in queries

                function ($pValue) {
                    // Extract name, operator and values

                    preg_match_all("/\s*([^=!<>]*)\s*(==|!=|<=|>=|=|<|>)(.*)/", $pValue, $pMatches);
                    $vName = isset($pMatches[1][0]) ? trim($pMatches[1][0]) : null;

                    $vOperator = isset($pMatches[2][0]) ? trim($pMatches[2][0]) : null;

                    // Convert old operator format to new refactored format

                    if ($vOperator == '=') {
                        $vOperator = '==';
                    }

                    // Transform comma separated values list to an array eliminating duplicates

                    $vUniqueValues = [];
                    if (isset($pMatches[3][0])) {
                        foreach (explode(',', trim($pMatches[3][0])) as $vValue) {
                            // replace tokens like [user.name] and [user.entry.tag]
                            // TODO: make it a service that could be used for any params
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
                    },
                ),
            ),

            // Remove query with no parameter name

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
     * @param $pQuery array|string|null the query in different format :
     *                new array format [ [ "name" => "bf_field", "operator" => "==" , values [ "toto", ... ] ], ... ]
     *                OR
     *                old array format : [ "bf_field" => "toto", "bf_field2!" => "tata" ]
     *                OR
     *                new string format : bf_field == toto1 | bf_field2 <= tata
     *                OR
     *                old string format bf_field=toto1|bf_field2!=tata
     *
     * @return string the string representing the query
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
                        }
                        // format [ "bf_field" => "toto", "bf_field2!" => "tata" ]

                        return $pKey . '=' . $pQuery[$pKey];
                    },
                    array_keys($pQuery),
                ),
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

    public function keywordsToString($pKeywords)
    {
        if (is_string($pKeywords)) {
            return $pKeywords;
        }

        $vResult = [];

        $vResult[] = implode('|', array_map(function ($pORs) {
            return implode(',', $pORs);
        }, $pKeywords['CNF']));

        // Two mistakes in three lines, and they cancelled into silence: the closure used
        // `$pORs`, which is the *other* closure's parameter and undefined here, and the key was
        // `excluded` where parseKeywords() writes `excludeds` everywhere else. So the excluded
        // half of a round-tripped keyword string was always empty (ticket 40).
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
                // reset per iteration: an argument that is neither an array nor a string left
                // this holding the PREVIOUS argument's query, so it was aggregated twice --
                // the same leak PointimageAction had with $marker (ticket 40)
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
     * Normalise une chaîne :
     *   - met en minuscules (Unicode-safe)
     *   - transforme les caractères accentués en leur équivalent non accentué
     *   - gère les ligatures courantes (œ, æ, ß, etc.).
     *
     * @param string $s chaîne d'entrée (n'importe quel encodage détectable)
     *
     * @return string chaîne lowercase, sans accents
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
     * The three values are not interchangeable with a boolean: `extractRegExp()` switches on
     * them to decide how to unwrap the pattern. Callers that only need "is it one at all"
     * compare against 0 explicitly, so the narrowing is visible rather than implied by PHP's
     * int-to-bool coercion.
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
     * Extract and transform a regexp string from a string recognized by isRegExp as a regexp
     * + It removes beginning and ending "/" if it exists
     * + Optionnaly, it add alternatives for each character that has an accented version.
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
     * Build a hash from structure definition
     * The hash is a facility for associative array search.
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

        // A field may be called whatever its author called it, and `pages` has short, ordinary
        // column names -- `tag`, `time`, `body`, `user`, `owner`. The CTE selects `p.*` and
        // then one alias per field, so a field named after any of them declares that column
        // twice. MySQL refuses the whole query ("SQLSTATE[42S21] ... Duplicate column name
        // 'tag'") and the wiki answers an error page; SQLite accepts it and quietly lets the
        // extracted field win, so `$page['tag']` becomes the field's value instead of the
        // page's and results end up keyed by it. An error on one driver, wrong data on
        // another, from the same query -- and the suite runs on SQLite, so neither showed.
        //
        // Every reference to a field's column goes through this function, so prefixing here
        // renames it in the SELECT, in the WHERE and in the multiple-value CTEs at once.
        return in_array(strtolower($renamed), self::PAGES_COLUMNS, true)
            ? self::FIELD_COLUMN_PREFIX . $renamed
            : $renamed;
    }

    /**
     * A field name reduced to something that is certainly a SQL identifier.
     *
     * This function is the reason the surrounding query builder is safe, so it is worth being
     * explicit about why quoting is not the answer here. A field name is *user data* -- the
     * form designer stores whatever the webmaster typed, with no validation anywhere -- and it
     * reaches the generated SQL in five different positions: a column alias (`... AS x`), a
     * bare column reference in a WHERE, a CTE name (with a `_multiple` suffix glued on), a
     * value inside `s.champ = '...'`, and a JSON path inside a string literal.
     *
     * `SqlDialect::quoteIdentifier()` cannot cover that. It wraps a name in backticks or double
     * quotes without escaping what is inside, so it is only safe for names the code already
     * trusts; and a quoted alias would have to be quoted identically at every one of the
     * reference sites, while `"name" . '_multiple'` is not even valid syntax. Constraining the
     * name once, here, covers all five positions at their source instead.
     *
     * A name that is already an identifier comes back untouched -- which is every field name in
     * every real wiki, so no alias changes and no result-set key a caller reads by name moves.
     * Anything else is reduced to one, with a short hash of the original appended so that two
     * different odd names cannot collapse onto the same column.
     */
    private static function asSafeIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1) {
            return $name;
        }

        $reduced = (string)preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        if ($reduced === '' || preg_match('/^[0-9]/', $reduced) === 1) {
            $reduced = '_' . $reduced;
        }

        // MySQL caps an identifier at 64 characters and the CTE adds `_multiple` to it, so the
        // reduced form is trimmed to leave room. Only this branch is trimmed: shortening a
        // long-but-valid name would change the key its caller reads it back by.
        return substr($reduced, 0, 40) . '_' . substr(md5($name), 0, 8);
    }
}

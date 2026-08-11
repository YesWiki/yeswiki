<?php

namespace YesWiki\Search\Service;

use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * Reads the search index (ticket 18 / ADR-0015).
 *
 * What makes this different from every previous YesWiki search is that **nothing is
 * filtered after the fact**. Page-level ACL, field-level ACL and the content-type filter
 * all become SQL, so a result count is a count and a page of results is a page -- rather
 * than an estimate that a differently-permissioned visitor silently erodes.
 *
 * The field-level half is the interesting one. A Content is indexed as one row per distinct
 * Field ACL guarding its fields, and the wiki has only a handful of distinct expressions in
 * total (one per field per form). So they are enumerated once, evaluated once against the
 * current user, and the ones that pass go into the query as `acl_hash IN (...)`.
 */
class SearchIndexQuery
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 200;

    /**
     * How far a result count is computed exactly before it becomes "this many or more".
     *
     * See countMatches() for why this exists. It is not a cap on *results* -- paging past it
     * works and returns the right rows; it caps only the number reported.
     */
    public const COUNT_CAP = 1000;

    private DbService $dbService;
    private AclService $aclService;
    private SearchIndexSchema $schema;
    private FormOptionTranslator $translator;

    /** @var list<string>|null md5s of the field ACLs this user passes, per request */
    private ?array $readableAclHashes = null;

    /** Whether the index holds a single (public) ACL bucket; see hasASingleAclBucket() */
    private ?bool $singleAclBucket = null;

    public function __construct(
        DbService $dbService,
        AclService $aclService,
        SearchIndexSchema $schema,
        FormOptionTranslator $translator,
    ) {
        $this->dbService = $dbService;
        $this->aclService = $aclService;
        $this->schema = $schema;
        $this->translator = $translator;
    }

    /**
     * @param string      $query       what the visitor typed
     * @param string|null $contentType restrict to one type ('entry', 'page', ...), null for all
     * @param int         $limit       page size
     * @param int         $offset      how far in
     *
     * @return array{results: list<array{tag: string, title: string, content_type: string, form_id: string, updated_at: string}>, total: int, capped: bool}
     *                                                                                                                                                      `total` is exact up to COUNT_CAP; `capped` says when it stopped there
     */
    public function search(string $query, ?string $contentType = null, int $limit = self::DEFAULT_LIMIT, int $offset = 0): array
    {
        $groups = $this->parseQuery($query);
        if ($groups === [] || !$this->schema->exists()) {
            return ['results' => [], 'total' => 0, 'capped' => false];
        }

        $where = $this->where($groups, $contentType);
        $table = $this->schema->table();
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);

        [$total, $capped] = $this->countMatches($where);
        if ($total === 0) {
            return ['results' => [], 'total' => 0, 'capped' => false];
        }

        // Ordering is deliberately NOT the engine's relevance score. MySQL, PostgreSQL and
        // SQLite each rank differently, and phpunit only ever sees the third -- so a ranked
        // order would be a behaviour the test suite cannot predict for the driver that
        // matters. ADR-0015 defers relevance to a tuning ticket; until then a title hit
        // outranks a body hit, and recency breaks the tie.
        $titleHit = $this->titleHitExpression($groups);

        // The GROUP BY collapses a Content's several ACL-bucket rows into one result. It is
        // also the single most expensive thing in this query -- measured at 500k index rows,
        // it costs as much as the result page itself, because grouping cannot stop at LIMIT
        // the way a plain scan can. So it is skipped entirely when the index holds only one
        // bucket, which is every wiki with no restricted fields: there is then exactly one
        // row per Content and nothing to collapse.
        // the title-hit expression is in the SELECT and the filter in the WHERE, so its values
        // come first -- placeholder order is textual, and SqlFragment only guarantees the
        // correspondence within a composition, not across two of them
        if ($this->hasASingleAclBucket()) {
            $rows = $this->dbService->loadAll(
                "SELECT tag, content_type, form_id, title, updated_at, {$titleHit->sql} AS title_hit"
                . " FROM {$table} WHERE {$where->sql}"
                . ' ORDER BY title_hit DESC, updated_at DESC, tag ASC'
                . ' LIMIT ? OFFSET ?',
                [...$titleHit->params, ...$where->params, $limit, $offset]
            );
        } else {
            $rows = $this->dbService->loadAll(
                'SELECT tag, MAX(content_type) AS content_type, MAX(form_id) AS form_id,'
                . ' MAX(title) AS title, MAX(updated_at) AS updated_at,'
                . " MAX({$titleHit->sql}) AS title_hit"
                . " FROM {$table} WHERE {$where->sql}"
                . ' GROUP BY tag'
                . ' ORDER BY title_hit DESC, MAX(updated_at) DESC, tag ASC'
                . ' LIMIT ? OFFSET ?',
                [...$titleHit->params, ...$where->params, $limit, $offset]
            );
        }

        // mapped rather than handed back raw: the surface (ticket 26) reads these by name,
        // and a result row is a contract of this service rather than a shape the SELECT
        // happens to have today
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'tag' => (string)$row['tag'],
                'title' => (string)$row['title'],
                'content_type' => (string)$row['content_type'],
                'form_id' => (string)$row['form_id'],
                'updated_at' => (string)$row['updated_at'],
            ];
        }

        return ['results' => $results, 'total' => $total, 'capped' => $capped];
    }

    /**
     * How many Contents match, exactly, up to COUNT_CAP.
     *
     * ADR-0015's promise is that nothing is filtered after the fact -- page ACLs, field ACLs
     * and the type filter are all in the WHERE clause, so no result is ever dropped and
     * pagination is never wrong. That promise is kept here in full.
     *
     * What is *not* promised is an exact number for an enormous result set, and this is
     * where that was decided. A count has to visit every match -- it cannot stop at LIMIT --
     * so on the 500k-row corpus `search:seed` builds, counting a query that matches
     * everything takes about 1.7 seconds, and at the millions this rewrite targets it is
     * worse. Nobody reads "483912" as information; what a visitor needs is "more than a
     * thousand, narrow it down". So the count runs against a capped subquery, which lets the
     * engine stop early, and `capped` tells the surface to render "1000+".
     *
     * @return array{0: int, 1: bool} the count, and whether it stopped at the cap
     */
    private function countMatches(SqlFragment $where): array
    {
        $table = $this->schema->table();
        $cap = self::COUNT_CAP + 1;

        // DISTINCT only where it can matter -- see the GROUP BY note in search()
        $inner = $this->hasASingleAclBucket()
            ? "SELECT 1 FROM {$table} WHERE {$where->sql} LIMIT {$cap}"
            : "SELECT tag FROM {$table} WHERE {$where->sql} GROUP BY tag LIMIT {$cap}";

        $counted = (int)$this->dbService->scalar("SELECT COUNT(*) FROM ({$inner}) yw_capped", 0, $where->params);

        return $counted > self::COUNT_CAP ? [self::COUNT_CAP, true] : [$counted, false];
    }

    /**
     * Whether every index row is the public bucket, so a Content has exactly one row.
     *
     * Cheap: `acl_hash` is indexed, and the answer is two rows at most. True on any wiki
     * that has never set a Field ACL, which is the overwhelming majority.
     */
    private function hasASingleAclBucket(): bool
    {
        if ($this->singleAclBucket === null) {
            $rows = $this->dbService->loadAll(
                "SELECT DISTINCT acl_hash FROM {$this->schema->table()} LIMIT 2"
            );
            $this->singleAclBucket = count($rows) <= 1;
        }

        return $this->singleAclBucket;
    }

    /**
     * How much Content each form holds, and when it last changed.
     *
     * Read off the search index rather than counted over `pages`: this table has one row
     * per Content and is indexed, while the authoritative version of the same question --
     * `GROUP BY json_extract(body, '$.form_id')` over every latest revision -- is a scan of
     * the whole wiki, on a screen anyone may open. The two were compared row by row on a
     * real wiki and agree; when they cannot, the index is what search itself answers from,
     * so this screen and a search agree about what exists.
     *
     * Keyed the way the caller asks: a webmaster's form owns rows carrying its `form_id`,
     * while the built-in Content types own every row of their `content_type` -- a page, an
     * account and a file carry no form_id at all. The form's own row is never counted: a
     * form is described by itself, and `form_id` on it means "I am this form".
     *
     * `total` is what tells "this wiki holds nothing of that kind" from "the index has not
     * been built yet", which read the same from a single form's row and must not.
     *
     * @return array{total: int, byForm: array<string, array{count: int, last: string}>, byType: array<string, array{count: int, last: string}>}
     */
    public function contentStats(): array
    {
        $empty = ['total' => 0, 'byForm' => [], 'byType' => []];
        if (!$this->schema->exists()) {
            return $empty;
        }

        // DISTINCT tag, like facets(): a Content whose form has restricted fields owns one
        // row per ACL bucket, and COUNT(*) would report it as several entries
        $rows = $this->dbService->loadAll(
            'SELECT content_type, form_id, COUNT(DISTINCT tag) AS total, MAX(updated_at) AS last'
            . ' FROM ' . $this->schema->table()
            . " WHERE content_type <> 'form'"
            . ' GROUP BY content_type, form_id'
        );

        $stats = $empty;
        foreach ($rows as $row) {
            $entry = ['count' => (int)$row['total'], 'last' => (string)($row['last'] ?? '')];
            $formId = (string)($row['form_id'] ?? '');
            if ($formId !== '') {
                $stats['byForm'][$formId] = $entry;
            }
            $type = (string)$row['content_type'];
            if (!isset($stats['byType'][$type])) {
                $stats['byType'][$type] = ['count' => 0, 'last' => ''];
            }
            $stats['byType'][$type]['count'] += $entry['count'];
            $stats['byType'][$type]['last'] = max($stats['byType'][$type]['last'], $entry['last']);
            $stats['total'] += $entry['count'];
        }

        return $stats;
    }

    /**
     * How many Contents of each type the query matches, for the facet row.
     *
     * Deliberately **not** filtered by the currently selected type: the point of a facet is
     * to show what else is there, so it counts across every type and the surface highlights
     * the chosen one.
     *
     * Capped like the total, and for the same reason -- these counts cannot stop early
     * either, and on a very broad query they would pay a second full pass over the match set
     * to produce numbers nobody reads past "lots".
     *
     * @return array<string, int> content type => count, only types with a match, biggest first
     */
    public function facets(string $query): array
    {
        $groups = $this->parseQuery($query);
        if ($groups === [] || !$this->schema->exists()) {
            return [];
        }

        $table = $this->schema->table();
        $where = $this->where($groups, null);
        $cap = self::COUNT_CAP + 1;

        // DISTINCT tag inside, so a Content with several ACL-bucket rows counts once
        $rows = $this->dbService->loadAll(
            'SELECT content_type, COUNT(*) AS total FROM ('
            . "SELECT DISTINCT tag, content_type FROM {$table} WHERE {$where->sql} LIMIT {$cap}"
            . ') yw_facets GROUP BY content_type',
            $where->params
        );

        $facets = [];
        foreach ($rows as $row) {
            $facets[(string)$row['content_type']] = (int)$row['total'];
        }
        arsort($facets);

        return $facets;
    }

    /**
     * The text of one Content as the index holds it, for building an excerpt.
     *
     * Only the buckets this user may read, so an excerpt can never quote a restricted field
     * back at someone the field is hidden from.
     */
    public function textFor(string $tag): string
    {
        if (!$this->schema->exists()) {
            return '';
        }

        $bucket = $this->fieldAclPredicate();
        $rows = $this->dbService->loadAll(
            "SELECT text FROM {$this->schema->table()}"
            . ' WHERE tag = ?'
            . ' AND ' . $bucket->sql,
            [$tag, ...$bucket->params]
        );

        return trim(implode(' ', array_map(static fn (array $row): string => (string)$row['text'], $rows)));
    }

    /**
     * The searched words, each with the enum option keys that word names.
     *
     * Reduces the query to what the full-text engines tokenise on -- letters, digits and
     * underscore -- which is also what makes the result safe to interpolate into a match
     * expression. This is the **only** thing that should ever build terms for
     * SqlDialect::searchMatchExpression(); see its docblock.
     *
     * @return list<list<string>> groups ANDed, alternatives within a group ORed
     */
    public function parseQuery(string $query): array
    {
        $groups = [];
        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $word) {
            $term = FormOptionTranslator::normalize($word);
            if ($term === '') {
                continue;
            }
            // the word itself, plus every option key whose label contains it
            $groups[] = array_values(array_unique(array_merge([$term], $this->translator->keysFor($word))));
        }

        return $groups;
    }

    /**
     * @param list<list<string>> $groups
     */
    private function where(array $groups, ?string $contentType): SqlFragment
    {
        $table = $this->schema->table();

        $clauses = [
            SqlFragment::of($this->dbService->dialect()->searchMatchExpression($table, $groups)),
            // field-level ACL: the buckets this user may read
            $this->fieldAclPredicate(),
            // page-level ACL: the same predicate `pages` is filtered with, over the
            // denormalised column (ADR-0015)
            $this->aclService->aclColumnPredicate("{$table}.page_read_acl", "{$table}.owner"),
        ];

        if ($contentType !== null && $contentType !== '') {
            $clauses[] = SqlFragment::of("{$table}.content_type = ?", [$contentType]);
        }

        // each clause keeps its own parentheses, and SqlFragment lines the values up with the
        // placeholders across all of them -- which is the whole reason this returns a fragment
        return SqlFragment::all(' AND ', ...array_map(
            static fn (SqlFragment $c): SqlFragment => $c->wrappedIn('(', ')'),
            $clauses
        ));
    }

    /**
     * `acl_hash IN (...)` over the field ACLs this user passes.
     *
     * The enumeration is what keeps this exact and cheap: there are as many distinct
     * expressions as there are differently-protected fields in the wiki, so evaluating all
     * of them costs a handful of AclService::check() calls, once. Hashes are hex, so the
     * list needs no escaping.
     */
    private function fieldAclPredicate(): SqlFragment
    {
        $hashes = $this->readableAclHashes();
        if ($hashes === []) {
            // cannot happen in practice -- the public bucket is readable by definition --
            // but a predicate that is never true beats one that is always true
            return SqlFragment::of('1 = 0');
        }

        // the hashes are hex md5s of this wiki's own ACL expressions, so they need no
        // quoting -- but they bind anyway, because a predicate that builds SQL from data is
        // one refactor away from binding data that is not hex
        return SqlFragment::of(
            "{$this->schema->table()}.acl_hash IN (" . SqlParameters::placeholders(count($hashes)) . ')',
            $hashes
        );
    }

    /** @return list<string> */
    private function readableAclHashes(): array
    {
        if ($this->readableAclHashes !== null) {
            return $this->readableAclHashes;
        }

        $rows = $this->dbService->loadAll(
            "SELECT DISTINCT acl, acl_hash FROM {$this->schema->table()}"
        );

        $this->readableAclHashes = [];
        foreach ($rows as $row) {
            $acl = trim((string)$row['acl']);
            // an empty Field ACL is public, exactly as BazarField::canRead() reads it
            if ($acl === '' || $this->aclService->check($acl)) {
                $this->readableAclHashes[] = (string)$row['acl_hash'];
            }
        }

        return $this->readableAclHashes;
    }

    /**
     * 1 when every searched word appears in the title, 0 otherwise.
     *
     * A plain LIKE, and cheap despite that: it is evaluated over the rows the full-text
     * index has already selected, not over the table.
     *
     * @param list<list<string>> $groups
     */
    private function titleHitExpression(array $groups): SqlFragment
    {
        $table = $this->schema->table();
        $collate = $this->dbService->collateClause();

        $tests = [];
        foreach ($groups as $alternatives) {
            $any = [];
            foreach ($alternatives as $term) {
                // The wildcards have to be defused deliberately: escape() and binding both
                // leave `%` and `_` alone, quite correctly -- inside a LIKE they are pattern
                // syntax, not data. Left alone, searching `100%` scored a title hit against
                // any title starting "100", and `a_b` against `aXb`.
                $any[] = SqlFragment::of(
                    "{$table}.title{$collate} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
                    [SqlParameters::likeContains($term)]
                );
            }
            $tests[] = SqlFragment::all(' OR ', ...$any)->wrappedIn('(', ')');
        }

        return SqlFragment::all(' AND ', ...$tests)->wrappedIn('(CASE WHEN ', ' THEN 1 ELSE 0 END)');
    }
}

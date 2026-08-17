<?php

namespace YesWiki\Search\Service;

use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/** Reads the search index (ticket 18 / ADR-0015). */
class SearchIndexQuery
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 200;

    /** How far a result count is computed exactly before it becomes "this many or more". */
    public const COUNT_CAP = 1000;

    private DbService $dbService;
    private AclService $aclService;
    private SearchIndexSchema $schema;
    private FormOptionTranslator $translator;

    /**
     * @var list<string>|null md5s of the field ACLs this user passes, per request
     */
    private ?array $readableAclHashes = null;

    /** Whether the index holds a single (public) ACL bucket; see hasASingleAclBucket(). */
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
     * @param string       $query       what the visitor typed
     * @param string|null  $contentType restrict to one type ('entry', 'page', ...), null for all
     * @param list<string> $tags        keywords the Content must ALL carry, [] for no filter
     * @param int          $limit       page size
     * @param int          $offset      how far in
     *
     * @return array{results: list<array{tag: string, title: string, content_type: string, form_id: string, updated_at: string}>, total: int, capped: bool}
     *                                                                                                                                                      `total` is exact up to COUNT_CAP; `capped` says when it stopped there
     */
    public function search(string $query, ?string $contentType = null, int $limit = self::DEFAULT_LIMIT, int $offset = 0, array $tags = []): array
    {
        $groups = $this->parseQuery($query);
        $tags = array_values(array_filter(array_map('trim', $tags), static fn (string $t): bool => $t !== ''));

        if (($groups === [] && $tags === []) || !$this->schema->exists()) {
            return ['results' => [], 'total' => 0, 'capped' => false];
        }

        $where = $this->where($groups, $contentType, $tags);
        $table = $this->schema->table();
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);

        [$total, $capped] = $this->countMatches($where);
        if ($total === 0) {
            return ['results' => [], 'total' => 0, 'capped' => false];
        }

        $titleHit = $this->titleHitExpression($groups);

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
     * @return array{0: int, 1: bool} the count, and whether it stopped at the cap
     */
    private function countMatches(SqlFragment $where): array
    {
        $table = $this->schema->table();
        $cap = self::COUNT_CAP + 1;

        $inner = $this->hasASingleAclBucket()
            ? "SELECT 1 FROM {$table} WHERE {$where->sql} LIMIT {$cap}"
            : "SELECT tag FROM {$table} WHERE {$where->sql} GROUP BY tag LIMIT {$cap}";

        $counted = (int)$this->dbService->scalar("SELECT COUNT(*) FROM ({$inner}) yw_capped", 0, $where->params);

        return $counted > self::COUNT_CAP ? [self::COUNT_CAP, true] : [$counted, false];
    }

    /** Whether every index row is the public bucket, so a Content has exactly one row. */
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
     * @return array{total: int, byForm: array<string, array{count: int, last: string}>, byType: array<string, array{count: int, last: string}>}
     */
    public function contentStats(): array
    {
        $empty = ['total' => 0, 'byForm' => [], 'byType' => []];
        if (!$this->schema->exists()) {
            return $empty;
        }

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

    /** The text of one Content as the index holds it, for building an excerpt. */
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

            $groups[] = array_values(array_unique(array_merge([$term], $this->translator->keysFor($word))));
        }

        return $groups;
    }

    /**
     * @param list<list<string>> $groups
     * @param list<string>       $tags
     */
    private function where(array $groups, ?string $contentType, array $tags = []): SqlFragment
    {
        $table = $this->schema->table();

        $clauses = [
            $this->fieldAclPredicate(),

            $this->aclService->aclColumnPredicate("{$table}.page_read_acl", "{$table}.owner"),
        ];

        if ($groups !== []) {
            array_unshift($clauses, SqlFragment::of($this->dbService->dialect()->searchMatchExpression($table, $groups)));
        }

        if ($contentType !== null && $contentType !== '') {
            $clauses[] = SqlFragment::of("{$table}.content_type = ?", [$contentType]);
        }

        $keywords = $this->schema->keywordsTable();
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') {
                continue;
            }
            $clauses[] = SqlFragment::of(
                "EXISTS (SELECT 1 FROM {$keywords} k WHERE k.tag = {$table}.tag AND k.keyword = ?)",
                [$tag]
            );
        }

        return SqlFragment::all(' AND ', ...array_map(
            static fn (SqlFragment $c): SqlFragment => $c->wrappedIn('(', ')'),
            $clauses
        ));
    }

    /** `acl_hash IN (...)` over the field ACLs this user passes. */
    private function fieldAclPredicate(): SqlFragment
    {
        $hashes = $this->readableAclHashes();
        if ($hashes === []) {
            return SqlFragment::of('1 = 0');
        }

        return SqlFragment::of(
            "{$this->schema->table()}.acl_hash IN (" . SqlParameters::placeholders(count($hashes)) . ')',
            $hashes
        );
    }

    /**
     * @return list<string>
     */
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

            if ($acl === '' || $this->aclService->check($acl)) {
                $this->readableAclHashes[] = (string)$row['acl_hash'];
            }
        }

        return $this->readableAclHashes;
    }

    /**
     * 1 when every searched word appears in the title, 0 otherwise.
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
                $any[] = SqlFragment::of(
                    "{$table}.title{$collate} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
                    [SqlParameters::likeContains($term)]
                );
            }
            $tests[] = SqlFragment::all(' OR ', ...$any)->wrappedIn('(', ')');
        }

        if ($tests === []) {
            return SqlFragment::of('0');
        }

        return SqlFragment::all(' AND ', ...$tests)->wrappedIn('(CASE WHEN ', ' THEN 1 ELSE 0 END)');
    }
}

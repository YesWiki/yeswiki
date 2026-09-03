<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Keywords: every question the wiki asks of them, answered from one index (ticket 62).
 *
 * `body.keywords` is the truth (ticket 09) and `{prefix}search_keywords` is its index, maintained
 * by `SearchIndexer` on every save. It used to be indexed twice, the second time as
 * `_vocabulary/tag` triples, and the two disagreed about what a keyword may be attached to: the
 * triples covered ordinary pages and bazar entries, `search_keywords` covers every Content there
 * is. Two indexes over one fact is the shape that produces a bug nobody can reproduce, and
 * `triples.value` is unindexed TEXT, so every keyword question used to be a table scan.
 *
 * Writing is not here at all. A keyword is changed by saving the page that carries it, and the
 * index follows -- which is why removing one from `/admin/keywords` edits bodies rather than rows.
 */
class TagsManager
{
    protected DbService $dbService;
    protected HibernationService $hibernationService;
    protected ParameterBagInterface $params;
    protected ContainerInterface $container;
    protected SearchIndexSchema $schema;

    public function __construct(
        DbService $dbService,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        SearchIndexSchema $schema,
        ContainerInterface $container
    ) {
        $this->dbService = $dbService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->schema = $schema;
        $this->container = $container;
    }

    private function readableFilter(): SqlFragment
    {
        return $this->container->get(AclService::class)->readableFilter();
    }

    /**
     * Split a user-typed, comma-separated keyword list into stored keywords: trimmed, blanks dropped, duplicates dropped, order preserved.
     *
     * @return list<string>
     */
    public static function parseList(?string $list): array
    {
        $keywords = [];
        foreach (explode(',', (string)$list) as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && !in_array($keyword, $keywords, true)) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    /**
     * The keywords of a decoded page body.
     *
     * @param array<string, mixed>|null $page
     *
     * @return list<string>
     */
    public static function keywordsOf(?array $page): array
    {
        $keywords = $page['body'][PageBody::KEYWORDS] ?? [];

        return is_array($keywords) ? array_values(array_filter($keywords, 'is_string')) : [];
    }

    /**
     * Whether there is an index to ask at all.
     *
     * A wiki whose search index was never created, or has been dropped, has no keywords to offer
     * -- where `triples` always had an answer. `/admin/health` says so out loud (ticket 52); every
     * reader here answers empty rather than throwing at a table that is not there.
     */
    private function indexed(): bool
    {
        return $this->schema->exists();
    }

    /**
     * @param string $page if empty, every distinct keyword in the whole wiki is returned
     *                     (from the index); otherwise the given page's own keywords, read
     *                     from its body
     *
     * @return list<array<string, mixed>> rows carrying at least a `value`
     */
    public function getAll(string $page = ''): array
    {
        if ($page == '') {
            if (!$this->indexed()) {
                return [];
            }

            return $this->dbService->loadAll(
                "SELECT DISTINCT keyword AS value FROM {$this->schema->keywordsTable()} ORDER BY keyword ASC"
            );
        }

        $stored = $this->container->get(PageManager::class)->getOne($page, null, true, true);

        return array_map(fn (string $keyword) => ['value' => $keyword, 'resource' => $page], self::keywordsOf($stored));
    }

    /**
     * The keywords used most.
     *
     * @return list<array{value: string, total: int}>
     */
    public function mostUsed(int $limit = 30): array
    {
        if (!$this->indexed()) {
            return [];
        }

        $rows = $this->dbService->loadAll(
            "SELECT keyword, COUNT(*) AS total FROM {$this->schema->keywordsTable()}"
            . ' GROUP BY keyword ORDER BY total DESC, keyword ASC LIMIT ?',
            [max(1, $limit)]
        );

        return array_map(
            fn (array $row): array => ['value' => (string)$row['keyword'], 'total' => (int)$row['total']],
            $rows
        );
    }

    /**
     * Live-search: distinct keywords matching $search (substring, case-insensitive), paginated.
     *
     * @return array{tags: string[], total: int}
     */
    public function search(string $search = '', int $limit = 20, int $offset = 0): array
    {
        if (!$this->indexed()) {
            return ['tags' => [], 'total' => 0];
        }

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE keyword LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX;
            $params[] = SqlParameters::likeContains($search);
        }
        $baseQuery = "SELECT DISTINCT keyword FROM {$this->schema->keywordsTable()}{$where}";

        return [
            'tags' => array_column($this->dbService->loadAll(
                $baseQuery . ' ORDER BY keyword ASC LIMIT ? OFFSET ?',
                [...$params, $limit, $offset]
            ), 'keyword'),
            'total' => $this->dbService->countRows($baseQuery, $params),
        ];
    }

    /**
     * Every (keyword, tag) pair the visitor may read, for `/admin/keywords` and `{{tagcloud}}`.
     *
     * The pair *is* the identity -- `search_keywords`' primary key is `(tag, keyword)` -- which is
     * what the screens address now. They used to address a triple's surrogate id, and giving this
     * table one would have been adding a column to carry a habit (ticket 62).
     *
     * @param list<string> $only restrict to these keywords; every one when empty
     *
     * @return list<array{keyword: string, tag: string}>
     */
    public function pairs(array $only = []): array
    {
        if (!$this->indexed()) {
            return [];
        }

        $chosen = $only === []
            ? SqlFragment::empty()
            : SqlFragment::of('keyword IN (' . SqlParameters::placeholders(count($only)) . ')', $only);
        $readable = $this->container->get(AclService::class)->readableResourceFilter('tag');
        $filter = SqlFragment::all(' AND ', $chosen, $readable);

        $rows = $this->dbService->loadAll(
            "SELECT keyword, tag FROM {$this->schema->keywordsTable()}"
            . ($filter->isEmpty() ? '' : ' WHERE ' . $filter->sql)
            . ' ORDER BY keyword ASC, tag ASC',
            $filter->params
        );

        return array_map(
            static fn (array $row): array => ['keyword' => (string)$row['keyword'], 'tag' => (string)$row['tag']],
            $rows
        );
    }

    /**
     * Take these keywords off these Contents, by editing the bodies that carry them.
     *
     * The index is not touched: saving the page re-indexes it, so the cache follows the truth
     * instead of being edited beside it.
     *
     * @param list<array{keyword: string, tag: string}> $pairs
     */
    public function remove(array $pairs): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $byContent = [];
        foreach ($pairs as $pair) {
            $tag = trim($pair['tag']);
            $keyword = trim($pair['keyword']);
            if ($tag !== '' && $keyword !== '') {
                $byContent[$tag][] = $keyword;
            }
        }

        $pageManager = $this->container->get(PageManager::class);
        foreach ($byContent as $tag => $keywords) {
            $stored = $pageManager->getOne((string)$tag, null, false, true);
            if (!$stored) {
                continue;
            }
            $remaining = array_values(array_diff(self::keywordsOf($stored), $keywords));
            $body = $stored['body'] ?? [];
            if (empty($remaining)) {
                unset($body[PageBody::KEYWORDS]);
            } else {
                $body[PageBody::KEYWORDS] = $remaining;
            }
            $pageManager->save((string)$tag, $body, '', true);
        }
    }

    /**
     * The pages carrying every one of these keywords, or -- with no keyword -- every page.
     *
     * @param string     $tags comma-separated keywords, all of which a page must carry
     * @param string     $type '' for any page, 'wiki' for ordinary pages, 'bazar' for entries
     * @param int|string $nb   accepted for backwards compatibility, ignored
     * @param string     $sort '' to keep the database order, 'alpha' by tag, 'date' newest first
     *
     * @return list<array<string, mixed>>
     */
    public function getPagesByTags(string $tags = '', string $type = '', $nb = '', string $sort = ''): array
    {
        if (!empty($tags)) {
            if (!$this->indexed()) {
                return [];
            }

            $keywords = self::parseList($tags);
            $wanted = count($keywords);
            $keywordsTable = $this->schema->keywordsTable();

            $pages = trim($this->dbService->prefixTable('pages'));
            $req = " AND EXISTS (SELECT k.tag FROM {$keywordsTable} k WHERE k.tag = {$pages}.tag"
                . ' AND k.keyword IN (' . SqlParameters::placeholders($wanted) . ')'
                . ' GROUP BY k.tag HAVING COUNT(k.tag) = ?) ';

            $params = [...$keywords, $wanted];

            if ($sort == 'alpha') {
                $req .= ' ORDER BY tag ASC ';
            } elseif ($sort == 'date') {
                $req .= ' ORDER BY ' . $this->dbService->quoteIdentifier('time') . ' DESC ';
            }

            $readable = $this->readableFilter();
            $requete = 'SELECT * FROM ' . $this->dbService->prefixTable('pages') . " WHERE latest = 'Y' and parent = '' "
                . ($readable->isEmpty() ? '' : ' AND ' . $readable->sql) . $req;

            return $this->dbService->loadAll($requete, [...$readable->params, ...$params]);
        }

        $readable = $this->readableFilter();
        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('pages');
        $sql .= " WHERE latest='Y' AND parent='' ";

        $params = [];
        if (!$readable->isEmpty()) {
            $sql .= ' AND ' . $readable->sql . ' ';
            $params = $readable->params;
        }
        if ($type == 'wiki') {
            $sql .= ' AND ' . $this->dbService->quoteIdentifier('type') . ' = ? ';
            $params[] = PageType::PAGE;
        } elseif ($type == 'bazar') {
            $sql .= ' AND ' . $this->dbService->quoteIdentifier('type') . ' = ?';
            $params[] = PageType::ENTRY;
        }

        $sql .= ' ORDER BY tag ASC';

        return $this->dbService->loadAll($sql, $params);
    }
}

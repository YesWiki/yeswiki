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
use YesWiki\Kernel\Service\TripleStore;

/** Page keywords. */
class TagsManager
{
    public const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    protected DbService $dbService;
    protected HibernationService $hibernationService;
    protected TripleStore $tripleStore;
    protected ParameterBagInterface $params;
    protected ContainerInterface $container;

    public function __construct(
        DbService $dbService,
        TripleStore $tripleStore,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ContainerInterface $container
    ) {
        $this->dbService = $dbService;
        $this->tripleStore = $tripleStore;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
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

    /** Drop every index row for a page, without touching its body. */
    public function deleteAll(string $page): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->tripleStore->delete($page, self::TAG_PROPERTY, null, '', '');
    }

    /**
     * Replace a page's keywords.
     *
     * @param string $liste_tags comma-separated, as typed in the edit form
     */
    public function save(string $page, string $liste_tags): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $keywords = self::parseList($liste_tags);
        $pageManager = $this->container->get(PageManager::class);
        $stored = $pageManager->getOne($page, null, false, true);
        if ($stored) {
            $body = $stored['body'] ?? [];
            if (self::keywordsOf($stored) !== $keywords) {
                if (empty($keywords)) {
                    unset($body[PageBody::KEYWORDS]);
                } else {
                    $body[PageBody::KEYWORDS] = $keywords;
                }
                $pageManager->save($page, $body, '', true);
            }
        }

        $this->reindex($page, $keywords);
    }

    /**
     * Rewrite one page's entries in the reverse index to match the given keywords.
     *
     * @param list<string> $keywords
     */
    public function reindex(string $page, array $keywords): void
    {
        $this->tripleStore->delete($page, self::TAG_PROPERTY, null, '', '');
        foreach ($keywords as $keyword) {
            $this->tripleStore->create($page, self::TAG_PROPERTY, $keyword, '', '');
        }
    }

    /**
     * Rebuild the reverse index for ordinary wiki pages from the current revision of each.
     *
     * @return int the number of pages indexed
     */
    public function reindexAll(): int
    {
        $pages = trim($this->dbService->prefixTable('pages'));
        $typeCol = $this->dbService->quoteIdentifier('type');

        $rows = $this->dbService->loadAll(
            "SELECT tag, body FROM {$pages}"
            . " WHERE latest = 'Y' AND parent = '' AND {$typeCol} = ?",
            [PageType::PAGE]
        );

        $triples = trim($this->dbService->prefixTable('triples'));
        $this->dbService->query(
            "DELETE FROM {$triples} WHERE property = ?"
            . " AND resource IN (SELECT tag FROM {$pages}"
            . " WHERE latest = 'Y' AND {$typeCol} = ?)",
            [self::TAG_PROPERTY, PageType::PAGE]
        );

        $indexed = 0;
        foreach ($rows as $row) {
            $keywords = self::keywordsOf(['body' => PageBody::decode($row['body'] ?? null)]);
            if (empty($keywords)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                $this->tripleStore->create((string)$row['tag'], self::TAG_PROPERTY, $keyword, '', '');
            }
            $indexed++;
        }

        return $indexed;
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
            $sql = 'SELECT DISTINCT value FROM' . $this->dbService->prefixTable('triples') . 'WHERE property = ?';

            return $this->dbService->loadAll($sql, [self::TAG_PROPERTY]);
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
        $rows = $this->dbService->loadAll(
            'SELECT value, COUNT(*) AS total FROM' . $this->dbService->prefixTable('triples')
            . 'WHERE property = ? GROUP BY value ORDER BY total DESC, value ASC LIMIT ?',
            [self::TAG_PROPERTY, max(1, $limit)]
        );

        return array_map(
            fn (array $row): array => ['value' => (string)$row['value'], 'total' => (int)$row['total']],
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
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $table = $this->dbService->prefixTable('triples');
        $where = 'property = ?';
        $params = [self::TAG_PROPERTY];
        if ($search !== '') {
            $where .= ' AND value LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX;
            $params[] = SqlParameters::likeContains($search);
        }
        $baseQuery = "SELECT DISTINCT value FROM $table WHERE $where";

        return [
            'tags' => array_column($this->dbService->loadAll(
                $baseQuery . ' ORDER BY value ASC LIMIT ? OFFSET ?',
                [...$params, $limit, $offset]
            ), 'value'),
            'total' => $this->dbService->countRows($baseQuery, $params),
        ];
    }

    /**
     * Every (id, value, resource) index row, for the admin keyword-management page (AdminTagAction) -- unlike getAll()/search(), this exposes the row id, which is how that page targets individual page/keyword pairs for removeByIds().
     *
     * @return list<array<string, mixed>>
     */
    public function getAllTriples(): array
    {
        $readable = $this->container->get(AclService::class)->readableResourceFilter();

        return $this->dbService->loadAll(
            'SELECT id, value, resource FROM ' . $this->dbService->prefixTable('triples')
            . " WHERE property='" . self::TAG_PROPERTY . "'"
            . ($readable->isEmpty() ? '' : ' AND ' . $readable->sql)
            . ' ORDER BY value ASC, resource ASC',
            $readable->params
        );
    }

    /**
     * Remove the page/keyword pairs identified by these index-row ids (AdminTagAction's bulk "delete from all pages").
     *
     * @param array<array-key, int|string> $ids
     */
    public function removeByIds(array $ids): void
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }

        $rows = $this->dbService->loadAll(
            'SELECT resource, value FROM ' . $this->dbService->prefixTable('triples')
            . " WHERE property='" . self::TAG_PROPERTY . "' AND id IN (" . implode(',', $ids) . ')'
        );

        $toRemove = [];
        foreach ($rows as $row) {
            $toRemove[(string)$row['resource']][] = (string)$row['value'];
        }

        $pageManager = $this->container->get(PageManager::class);
        foreach ($toRemove as $page => $keywords) {
            $stored = $pageManager->getOne($page, null, false, true);
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
            $pageManager->save($page, $body, '', true);
            $this->reindex($page, $remaining);
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
            $req = ' AND EXISTS (select resource FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource=tag';

            $tab_tags = explode(',', trim($tags));
            $nbdetags = count($tab_tags);
            $req .= ' AND value IN (' . SqlParameters::placeholders($nbdetags) . ') ';
            $req .= ' AND property = ?';
            $req .= ' GROUP BY resource ';
            $req .= ' HAVING COUNT(resource) = ?) ';

            $params = [...$tab_tags, self::TAG_PROPERTY, $nbdetags];

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

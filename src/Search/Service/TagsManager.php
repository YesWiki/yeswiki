<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Page keywords.
 *
 * Since ticket 09 the keywords of a page live in that page's own row, under
 * `body.keywords` -- they were the last "fact about a page" stored outside the page,
 * which made a page's data non-atomic with the page and forced every reader to join
 * `triples`.
 *
 * The `TAG_PROPERTY` triples are still written, but they are now a **derived reverse
 * index** (keyword -> pages), rebuilt from the body on every write and never the source
 * of truth. They stay because four things genuinely need an index rather than a scan:
 * the tag cloud, `getPagesByTags()`'s AND-semantics across several keywords, the admin
 * content table's SQL-side aggregation, and the whole-wiki keyword vocabulary. If the
 * index and a body ever disagree, the body wins -- `reindexAll()` rebuilds the lot.
 */
class TagsManager
{
    public const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    protected $dbService;
    protected $hibernationService;
    protected $tripleStore;
    protected $params;
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

    /**
     * Split a user-typed, comma-separated keyword list into stored keywords: trimmed,
     * blanks dropped, duplicates dropped, order preserved.
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

    public function deleteAll($page)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // the body goes away with the page; only the derived index needs clearing
        $this->tripleStore->delete($page, self::TAG_PROPERTY, null, '', '');
    }

    /**
     * Replace a page's keywords. Writes the page's body -- the source of truth -- and
     * then rebuilds that page's slice of the reverse index.
     *
     * @param string $liste_tags comma-separated, as typed in the edit form
     */
    public function save($page, $liste_tags)
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
     * Rebuild the reverse index for ordinary wiki pages from the current revision of
     * each. The repair path when the index and the bodies disagree, and what the
     * ticket-09 migration calls once keywords have moved into the bodies.
     *
     * Scoped to untyped Content on purpose. A bazar entry's tags are an ordinary form
     * field whose name the webmaster chose, written to both the entry body and the index
     * by TagsField -- this method cannot reconstruct them without introspecting every
     * form, so it leaves their index rows alone rather than deleting what it can't rebuild.
     *
     * @return int the number of pages indexed
     */
    public function reindexAll(): int
    {
        $pages = trim($this->dbService->prefixTable('pages'));
        $triples = trim($this->dbService->prefixTable('triples'));
        $untyped = "NOT EXISTS (SELECT 1 FROM {$triples} t WHERE t.resource = p.tag"
            . " AND t.property = '" . $this->dbService->escape(TripleStore::TYPE_URI) . "')";

        $rows = $this->dbService->loadAll(
            "SELECT p.tag AS tag, p.body AS body FROM {$pages} p"
            . " WHERE p.latest = 'Y' AND p.comment_on = '' AND {$untyped}"
        );

        $this->dbService->query(
            "DELETE FROM {$triples} WHERE property = '" . $this->dbService->escape(self::TAG_PROPERTY) . "'"
            . " AND resource IN (SELECT p.tag FROM {$pages} p WHERE p.latest = 'Y' AND {$untyped})"
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
     */
    public function getAll($page = '')
    {
        if ($page == '') {
            $sql = 'SELECT DISTINCT value FROM' . $this->dbService->prefixTable('triples') . "WHERE property='" . self::TAG_PROPERTY . "'";

            return $this->dbService->loadAll($sql);
        }

        $stored = $this->container->get(PageManager::class)->getOne($page, null, true, true);

        // the row shape callers expect (they array_column(..., 'value') it)
        return array_map(fn (string $keyword) => ['value' => $keyword, 'resource' => $page], self::keywordsOf($stored));
    }

    /**
     * Live-search: distinct keywords matching $search (substring, case-insensitive),
     * paginated. Backs GET /api/tags -- unlike getAll(''), this never loads the whole
     * vocabulary at once. Served from the reverse index, which is what an index is for.
     *
     * @return array{tags: string[], total: int}
     */
    public function search(string $search = '', int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $table = $this->dbService->prefixTable('triples');
        $where = "property='" . self::TAG_PROPERTY . "'";
        if ($search !== '') {
            // single-quoted, not double-quoted: DbService::escape() (PDO::quote()) only
            // guarantees safety inside a single-quoted SQL literal (see
            // AclService::updateRequestWithACL() for the same class of driver-dependent bug)
            $where .= " AND value LIKE '%" . $this->dbService->escape($search) . "%'";
        }
        $baseQuery = "SELECT DISTINCT value FROM $table WHERE $where";

        return [
            'tags' => array_column($this->dbService->loadAll($baseQuery . " ORDER BY value ASC LIMIT $limit OFFSET $offset"), 'value'),
            'total' => $this->dbService->count($baseQuery),
        ];
    }

    /**
     * Every (id, value, resource) index row, for the admin keyword-management page
     * (AdminTagAction) -- unlike getAll()/search(), this exposes the row id, which is how
     * that page targets individual page/keyword pairs for removeByIds().
     */
    public function getAllTriples(): array
    {
        return $this->dbService->loadAll(
            'SELECT id, value, resource FROM ' . $this->dbService->prefixTable('triples')
            . " WHERE property='" . self::TAG_PROPERTY . "' ORDER BY value ASC, resource ASC"
        );
    }

    /**
     * Remove the page/keyword pairs identified by these index-row ids (AdminTagAction's
     * bulk "delete from all pages").
     *
     * Since ticket 09 this has to go through the pages themselves: deleting only the
     * index rows would leave the keywords in the bodies, and the next reindex would
     * bring them all back.
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

    public function getPagesByTags($tags = '', $type = '', $nb = '', $tri = '')
    {
        if (!empty($tags)) {
            $req = ' AND EXISTS (select resource FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource=tag';
            $tags = trim($tags);
            $tab_tags = explode(',', $tags);
            $nbdetags = count($tab_tags);
            $tags = implode(',', $tab_tags);
            $tags = "'" . str_replace(',', "','", $this->dbService->escape(addslashes($tags))) . "'";
            $req .= ' AND value IN (' . $tags . ') ';
            $req .= " AND property='" . self::TAG_PROPERTY . "'";
            $req .= ' GROUP BY resource ';
            $req .= ' HAVING COUNT(resource)=' . $nbdetags . ') ';

            // gestion du tri de l'affichage
            if ($tri == 'alpha') {
                $req .= ' ORDER BY tag ASC ';
            } elseif ($tri == 'date') {
                $req .= ' ORDER BY time DESC ';
            }

            $requete = 'SELECT * FROM ' . $this->dbService->prefixTable('pages') . " WHERE latest = 'Y' and comment_on = '' " . $req;

            return $this->dbService->loadAll($requete);
        }
        // recuperation des pages wikis
        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('pages');
        $sql .= " WHERE latest='Y' AND comment_on='' AND tag NOT LIKE 'LogDesActionsAdministratives%' ";

        if ($type == 'wiki') {
            $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') . "WHERE property='http://outils-reseaux.org/_vocabulary/type') ";
        } elseif ($type == 'bazar') {
            $sql .= ' AND tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') . "WHERE property='http://outils-reseaux.org/_vocabulary/type' AND value='fiche_bazar')";
        }

        $sql .= ' ORDER BY tag ASC';

        return $this->dbService->loadAll($sql);
    }
}

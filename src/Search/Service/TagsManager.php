<?php

namespace YesWiki\Search\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\TripleStore;

class TagsManager
{
    public const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    protected $wiki;
    protected $dbService;
    protected $hibernationService;
    protected $tripleStore;
    protected $params;

    public function __construct(Wiki $wiki, DbService $dbService, TripleStore $tripleStore, ParameterBagInterface $params, HibernationService $hibernationService)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->tripleStore = $tripleStore;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
    }

    public function deleteAll($page)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // on recupere les anciens tags de la page courante
        $tabtagsexistants = $this->tripleStore->getAll($page, self::TAG_PROPERTY, '', '');
        if (is_array($tabtagsexistants)) {
            foreach ($tabtagsexistants as $tab) {
                $this->tripleStore->delete($page, self::TAG_PROPERTY, $tab['value'], '', '');
            }
        }
    }

    public function save($page, $liste_tags)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // TODO check if we need to escape here, or if we can do that in the tripleStore methods
        $tags = explode(',', $this->dbService->escape($liste_tags));

        // on recupere les anciens tags de la page courante
        $tabtagsexistants = $this->tripleStore->getAll($page, self::TAG_PROPERTY, '', '');
        if (is_array($tabtagsexistants)) {
            foreach ($tabtagsexistants as $tab) {
                $tags_restants_a_effacer[] = $tab['value'];
            }
        }

        // on ajoute le tag s il n existe pas déjà
        foreach ($tags as $tag) {
            trim($tag);
            if ($tag != '') {
                if (!$this->tripleStore->exist($page, self::TAG_PROPERTY, htmlspecialchars($tag), '', '')) {
                    $this->tripleStore->create($page, self::TAG_PROPERTY, htmlspecialchars($tag), '', '');
                }
                // on supprime ce tag du tableau des tags restants a effacer
                if (isset($tags_restants_a_effacer)) {
                    unset($tags_restants_a_effacer[array_search($tag, $tags_restants_a_effacer)]);
                }
            }
        }

        // on supprime les tags restants a effacer
        if (isset($tags_restants_a_effacer)) {
            foreach ($tags_restants_a_effacer as $tag) {
                $this->tripleStore->delete($page, self::TAG_PROPERTY, $tag, '', '');
            }
        }
    }

    /**
     * @param string $page if empty, every distinct tag value in the whole wiki is
     *                     returned; otherwise only the given page's own tags
     */
    public function getAll($page = '')
    {
        if ($page == '') {
            // TODO use tripleStore service
            $sql = 'SELECT DISTINCT value FROM' . $this->dbService->prefixTable('triples') . "WHERE property='" . self::TAG_PROPERTY . "'";

            return $this->dbService->loadAll($sql);
        }

        return $this->tripleStore->getAll($page, self::TAG_PROPERTY, '', '');
    }

    /**
     * Live-search: distinct tag values matching $search (substring, case-insensitive),
     * paginated. Backs GET /api/tags -- unlike getAll(''), this never loads the whole
     * tag vocabulary at once.
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
     * Every (id, value, resource) tag triple in the wiki, for the admin tag-management
     * page (AdminTagAction) -- unlike getAll()/search(), this exposes the triple id,
     * needed to target individual rows for deleteByIds().
     */
    public function getAllTriples(): array
    {
        return $this->dbService->loadAll(
            'SELECT id, value, resource FROM ' . $this->dbService->prefixTable('triples')
            . " WHERE property='" . self::TAG_PROPERTY . "' ORDER BY value ASC, resource ASC"
        );
    }

    /**
     * Deletes specific tag triples by id (AdminTagAction's bulk "delete from all pages").
     * $ids is cast to int individually -- triples.id is always numeric, so this is both
     * simpler and safer than escaping a comma-joined string as a single SQL literal
     * (which is what silently broke multi-id deletes before this method existed: `id IN
     * ('3,5,7')` never matches more than one row).
     */
    public function deleteByIds(array $ids): void
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }
        $this->dbService->query(
            'DELETE FROM ' . $this->dbService->prefixTable('triples')
            . " WHERE property='" . self::TAG_PROPERTY . "' AND id IN (" . implode(',', $ids) . ')'
        );
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
        if (!empty($taglist)) {
            $sql .= ' INNER JOIN ' . $this->dbService->prefixTable('triples') . ' as tags ON tag=tags.resource';
        }
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

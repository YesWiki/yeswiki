<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Wiki;

class PageManager
{
    protected $wiki;
    protected $dbService;
    protected $ficheManager;
    protected $bazarGuard;
    protected $params;

    protected $pageCache;

    public function __construct(Wiki $wiki, DbService $dbService, FicheManager $ficheManager, Guard $bazarGuard, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->ficheManager = $ficheManager;
        $this->bazarGuard = $bazarGuard;
        $this->params = $params;

        $this->pageCache = [];
    }

    public function getOne($tag, $time = "", $cache = 1)
    {
        // retrieve from cache
        if (!$time && $cache && (($cachedPage = $this->getCached($tag)) !== false)) {
            if ($cachedPage and !isset($cachedPage["metadatas"])) {
                $cachedPage["metadatas"] = $this->wiki->GetMetaDatas($tag);
            }
            $page = $cachedPage;
        } else { // load page

            $sql = 'SELECT * FROM' . $this->dbService->prefixTable('pages') . "WHERE tag = '" . $this->dbService->escape($tag) . "' AND " . ($time ? "time = '" . $this->dbService->escape($time) . "'" : "latest = 'Y'") . " LIMIT 1";
            $page = $this->dbService->loadSingle($sql);

            // si la page existe, on charge les meta-donnees
            if ($page) {
                $page["metadatas"] = $this->wiki->GetMetaDatas($tag);
            }

            if ($this->ficheManager->isFiche($tag)) {
                $page = $this->bazarGuard->checkAcls($page, $tag);
            }

            // cache result
            if (!$time) {
                $this->cache($page, $tag);
            }
        }
        return $page;
    }

    /**
     * Retrieves the cached version of a page.
     *
     * Notice that this method null or false, use
     * $this->getCached($tag) === false
     * to check if a page is not in the cache.
     *
     * @return mixed The cached version of a page:
     *         - the page DB line if the page exists and is in cache
     *         - null if the cache knows that the page does not exists
     *         - false is the cache does not know the page
     */
    public function getCached($tag)
    {
        return (array_key_exists($tag, $this->pageCache) ? $this->pageCache[$tag] : false);
    }

    /**
     * Caches a page's DB line.
     *
     * @param array $page
     *            The page (full) DB line or null if the page does not exists
     * @param string $pageTag
     *            The tag of the page to cache. Defaults to $page['tag'] but is mandatory when $page === null
     */
    public function cache($page, $pageTag = null)
    {
        if ($pageTag === null) {
            $pageTag = $page['tag'];
        }
        $this->pageCache[$pageTag] = $page;
    }

    public function getById($id)
    {
        return $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('pages') . "where id = '" . $this->dbService->escape($id) . "' limit 1");
    }

    public function getRevisions($page)
    {
        return $this->dbService->loadAll('select * from' . $this->dbService->prefixTable('pages') . "where tag = '" . $this->dbService->escape($page) . "' order by time desc");
    }

    public function getLinkingTo($tag)
    {
        return $this->dbService->loadAll('select from_tag as tag from' . $this->dbService->prefixTable('links') . "where to_tag = '" . $this->dbService->escape($tag) . "' order by tag");
    }

    public function getRecentlyChanged($limit = 50, $minDate = '')
    {
        if (!empty($minDate)) {
            if ($pages = $this->dbService->loadAll('select id, tag, time, user, owner from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' and time >= '$minDate' order by time desc")) {
                //foreach ($pages as $page) {
                //    $this->cache($page);
                //}
                return $pages;
            }
        } else {
            $limit = (int) $limit;
            if ($pages = $this->dbService->loadAll('select id, tag, time, user, owner from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' order by time desc limit $limit")) {
                //foreach ($pages as $page) {
                //    $this->cache($page);
                //}
                return $pages;
            }
        }
    }

    public function getAll()
    {
        return $this->dbService->loadAll('select * from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' order by tag");
    }

    public function getCreateTime($pageTag)
    {
        $sql = 'SELECT time FROM'.$this->dbService->prefixTable('pages')
            .' WHERE tag = "'.$this->dbService->escape($pageTag).'"'
            .' AND comment_on = ""'
            .' ORDER BY `time` ASC LIMIT 1';
        $page = $this->dbService->loadSingle($sql);
        if ($page) {
            return $page['time'];
        }
        return null ;
    }

    public function searchFullText($phrase)
    {
        return $this->dbService->loadAll('select * from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and (body LIKE '%" . $this->dbService->escape($phrase) . "%' OR tag LIKE '%" . $this->dbService->escape($phrase) . "%')");
    }

    public function getWanted()
    {
        $r = "SELECT l.to_tag AS tag, COUNT(l.from_tag) AS count FROM ".$this->dbService->prefixTable('links')." as l LEFT JOIN ".$this->dbService->prefixTable('pages')." as p ON l.to_tag = p.tag WHERE p.tag IS NULL GROUP BY l.to_tag ORDER BY count DESC, tag ASC";
        return $this->dbService->loadAll($r);
    }

    public function getOrphaned()
    {
        return $this->dbService->loadAll('select distinct tag from ' . $this->dbService->prefixTable('pages') . 'as p left join ' . $this->dbService->prefixTable('links') . "as l on p.tag = l.to_tag where l.to_tag is NULL and p.comment_on = '' and p.latest = 'Y' order by tag");
    }

    public function isOrphaned($tag)
    {
        return $this->dbService->loadAll('select distinct tag from ' . $this->dbService->prefixTable('pages') . 'as p left join ' . $this->dbService->prefixTable('links') . "as l on p.tag = l.to_tag where l.to_tag is NULL and p.latest = 'Y' and tag = '" . $this->dbService->escape($tag) . "'");
    }

    public function deleteOrphaned($tag)
    {
        $this->dbService->query("DELETE FROM ".$this->dbService->prefixTable('pages')."WHERE tag='" . $this->dbService->escape($tag) . "' OR comment_on='" . $this->dbService->escape($tag) . "'");
        $this->dbService->query("DELETE FROM ".$this->dbService->prefixTable('links')."WHERE from_tag='" . $this->dbService->escape($tag) . "' ");
        $this->dbService->query("DELETE FROM ".$this->dbService->prefixTable('acls')."WHERE page_tag='" . $this->dbService->escape($tag) . "' ");
        $this->dbService->query("DELETE FROM ".$this->dbService->prefixTable('referrers')."WHERE page_tag='" . $this->dbService->escape($tag) . "' ");
    }

    /**
     * SavePage
     * Sauvegarde un contenu dans une page donnee
     *
     * @param string $body
     *            Contenu a sauvegarder dans la page
     * @param string $tag
     *            Nom de la page
     * @param string $comment_on
     *            Indication si c'est un commentaire
     * @param boolean $bypass_acls
     *            Indication si on bypasse les droits d'ecriture
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     */
    public function save($tag, $body, $comment_on = "", $bypass_acls = false)
    {
        // get current user
        $user = $this->wiki->GetUserName();

        // check bypass of rights or write privilege
        $rights = $bypass_acls || ($comment_on ? $this->wiki->HasAccess('comment', $comment_on) : $this->wiki->HasAccess('write', $tag));

        if ($rights) {
            // is page new?
            if (!$oldPage = $this->getOne($tag)) {
                // create default write acl. store empty write ACL for comments.
                $this->wiki->SaveAcl($tag, 'write', ($comment_on ? $user : $this->params->get('default_write_acl')));

                // create default read acl
                $this->wiki->SaveAcl($tag, 'read', $this->params->get('default_read_acl'));

                // create default comment acl.
                $this->wiki->SaveAcl($tag, 'comment', ($comment_on ? '' : $this->params->get('default_comment_acl')));

                // current user is owner; if user is logged in! otherwise, no owner.
                if ($this->wiki->GetUser()) {
                    $owner = $user;
                } else {
                    $owner = '';
                }
            } else {
                // aha! page isn't new. keep owner!
                $owner = $oldPage['owner'];

                // ...and comment_on, eventualy?
                if ($comment_on == '') {
                    $comment_on = $oldPage['comment_on'];
                }
            }

            // set all other revisions to old
            $this->dbService->query('update' . $this->dbService->prefixTable('pages') . "set latest = 'N' where tag = '" . $this->dbService->escape($tag) . "'");

            // add new revision
            $this->dbService->query('insert into' . $this->dbService->prefixTable('pages') . "set tag = '" . $this->dbService->escape($tag) . "', " . ($comment_on ? "comment_on = '" . $this->dbService->escape($comment_on) . "', " : "") . "time = now(), " . "owner = '" . $this->dbService->escape($owner) . "', " . "user = '" . $this->dbService->escape($user) . "', " . "latest = 'Y', " . "body = '" . $this->dbService->escape(chop($body)) . "', " . "body_r = ''");

            unset($this->pageCache[$tag]);

            return 0;
        } else {
            return 1;
        }
    }
}

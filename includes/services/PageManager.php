<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;
use YesWiki\Wiki;

class PageManager
{
    protected $aclService;
    protected $authController;
    protected $dbService;
    protected $eventDispatcher;
    protected $params;
    protected $securityController;
    protected $tagsManager;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;

    protected $ownersCache; // different cache because to set at the same time to prevent infinite loop
    protected $pageCache;

    public function __construct(
        AclService $aclService,
        AuthController $authController,
        DbService $dbService,
        EventDispatcher $eventDispatcher,
        ParameterBagInterface $params,
        SecurityController $securityController,
        TagsManager $tagsManager,
        TripleStore $tripleStore,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->authController = $authController;
        $this->dbService = $dbService;
        $this->eventDispatcher = $eventDispatcher;
        $this->params = $params;
        $this->securityController = $securityController;
        $this->tagsManager = $tagsManager;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;

        $this->ownersCache = [];
        $this->pageCache = [];
    }

    /**
     * @param string      $tag                    name of the page
     * @param string|null $time                   choose only the page's revision corresponding to time, null = latest revision
     * @param bool        $cache                  : use cache ?
     * @param bool        $bypassAcls             : do not check acl
     * @param string|null $userNameForCheckingACL userName used to check ACL, if empty uses the connected user
     */
    public function getOne($tag, $time = null, $cache = true, $bypassAcls = false, ?string $userNameForCheckingACL = null): ?array
    {
        // retrieve from cache
        if (!$bypassAcls && !$time && $cache && empty($userNameForCheckingACL) && (($cachedPage = $this->getCached($tag)) !== false)) {
            if ($cachedPage and !isset($cachedPage['metadatas'])) {
                $cachedPage['metadatas'] = $this->getMetadata($tag);
                // save page with metadatas
                $this->cache($cachedPage, $tag);
            }
            $page = $cachedPage;
        } else {
            // load page
            $timeQuery = $time ? "time = '{$this->dbService->escape($time)}'" : "latest = 'Y'";
            $page = $this->dbService->loadSingle("
                SELECT * FROM {$this->dbService->prefixTable('pages')} 
                WHERE tag = '{$this->dbService->escape($tag)}' AND {$timeQuery}
                LIMIT 1
            ");

            // set ownersCache before using guard
            $this->cacheOwner($page);

            if ($page) {
                $page['metadatas'] = $this->getMetadata($tag);
            }

            if (!$bypassAcls) {
                $page = $this->checkEntriesACL([$page], $tag, $userNameForCheckingACL)[0];
            }

            // cache result
            if (!$bypassAcls && !$time) {
                $this->cache($page, $tag);
            } else {
                // owner in pageCache could be different from ownersCache so unset
                $this->unsetCacheOwner($page);
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
     *               - the page DB line if the page exists and is in cache
     *               - null if the cache knows that the page does not exists
     *               - false is the cache does not know the page
     */
    public function getCached($tag)
    {
        return array_key_exists($tag, $this->pageCache) ? $this->pageCache[$tag] : false;
    }

    /**
     * Caches a page's DB line.
     *
     * @param array  $page
     *                        The page (full) DB line or null if the page does not exists
     * @param string $pageTag
     *                        The tag of the page to cache. Defaults to $page['tag'] but is mandatory when $page === null
     */
    public function cache($page, $pageTag = null)
    {
        if ($pageTag === null) {
            $pageTag = $page['tag'];
        }
        $this->pageCache[$pageTag] = $page;
    }

    public function cacheOwner($page)
    {
        if (!empty($page['tag']) && isset($page['owner'])) {
            $this->ownersCache[$page['tag']] = $page['owner'];
        }
    }

    private function unsetCacheOwner($page)
    {
        if (!empty($page['tag'])) {
            unset($this->ownersCache[$page['tag']]);
        }
    }

    public function getById($id): ?array
    {
        $page = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('pages') . "where id = '" . $this->dbService->escape($id) . "' limit 1");
        $page = $this->checkEntriesACL([$page], $page['tag'])[0];

        return $page;
    }

    public function getRevisions($pageTag, $limit = 10000)
    {
        return $this->checkEntriesACL($this->dbService->loadAll("
            SELECT id, time, user FROM {$this->dbService->prefixTable('pages')} 
            WHERE tag = '{$this->dbService->escape($pageTag)}' 
            ORDER BY time DESC
            LIMIT {$limit}
        "), $pageTag);
    }

    public function getPreviousRevision($page)
    {
        return $this->checkEntriesACL([$this->dbService->loadSingle("
            SELECT * FROM {$this->dbService->prefixTable('pages')} 
            WHERE tag = '{$this->dbService->escape($page['tag'])}' AND time < '{$page['time']}'
            ORDER BY time DESC
            LIMIT 1
        ")], $page['tag'])[0];
    }

    public function countRevisions($page)
    {
        return $this->dbService->count("
            SELECT * FROM {$this->dbService->prefixTable('pages')} 
            WHERE tag = '{$this->dbService->escape($page)}'
        ");
    }

    public function getLinkingTo($tag)
    {
        return $this->dbService->loadAll('select from_tag as tag from' . $this->dbService->prefixTable('links') . "where to_tag = '" . $this->dbService->escape($tag) . "' order by tag");
    }

    public function getRecentlyChanged($limit = 50, $minDate = ''): ?array
    {
        if (!empty($minDate)) {
            if ($pages = $this->dbService->loadAll('select id, tag, time, user, owner from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' and time >= '$minDate' order by time desc")) {
                //foreach ($pages as $page) {
                //    $this->cache($page);
                //}
                return $pages;
            }
        } else {
            $limit = (int)$limit;
            $limit = ($limit < 1) ? 50 : $limit;
            if ($pages = $this->dbService->loadAll('select id, tag, time, user, owner from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' order by time desc limit $limit")) {
                //foreach ($pages as $page) {
                //    $this->cache($page);
                //}
                return $pages;
            }
        }

        return null;
    }

    public function getAll(): array
    {
        $pages = $this->dbService->loadAll(<<<SQL
            SELECT * FROM {$this->dbService->prefixTable('pages')} WHERE LATEST = 'Y' ORDER BY tag
        SQL);
        $pages = $this->checkEntriesACL($pages);

        return $pages;
    }

    /**
     * get readable page tags
     * update page's owner to improve performances.
     *
     * @return string[] list of tags readble for current user
     */
    public function getReadablePageTags(): array
    {
        $sqlRequest = <<<SQL
            SELECT tag,owner FROM {$this->dbService->prefixTable('pages')} WHERE LATEST = 'Y' ORDER BY tag
        SQL;

        // append request to filter on acls during the request
        if (!$this->wiki->UserIsAdmin()) {
            $sqlRequest .= $this->aclService->updateRequestWithACL();
        }
        $pages = $this->dbService->loadAll($sqlRequest);

        return array_map(function ($page) {
            // cache page's owner to prevent reload of page from sql or infinite loop in some case
            $this->cacheOwner($page);

            return $page['tag'];
        }, $pages);
    }

    public function getCreateTime($pageTag)
    {
        $sql = 'SELECT time FROM' . $this->dbService->prefixTable('pages')
            . ' WHERE tag = "' . $this->dbService->escape($pageTag) . '"'
            . ' AND comment_on = ""'
            . ' ORDER BY `time` ASC LIMIT 1';
        $page = $this->dbService->loadSingle($sql);
        if ($page) {
            return $page['time'];
        }

        return null;
    }

    public function searchFullText($phrase): array
    {
        return $this->dbService->loadAll('select * from' . $this->dbService->prefixTable('pages') . "where latest = 'Y' and (body LIKE '%" . $this->dbService->escape($phrase) . "%' OR tag LIKE '%" . $this->dbService->escape($phrase) . "%')");
    }

    public function getWanted(): array
    {
        $r = 'SELECT l.to_tag AS tag, COUNT(l.from_tag) AS count FROM ' . $this->dbService->prefixTable('links') . ' as l LEFT JOIN ' . $this->dbService->prefixTable('pages') . ' as p ON l.to_tag = p.tag WHERE p.tag IS NULL GROUP BY l.to_tag ORDER BY count DESC, tag ASC';

        return $this->dbService->loadAll($r);
    }

    public function getOrphaned(): array
    {
        return $this->dbService->loadAll('select distinct tag from ' . $this->dbService->prefixTable('pages') . 'as p left join ' . $this->dbService->prefixTable('links') . "as l on p.tag = l.to_tag where l.to_tag is NULL and p.comment_on = '' and p.latest = 'Y' order by tag");
    }

    public function isOrphaned($tag): bool
    {
        return !is_null($this->dbService->loadSingle('select distinct tag from ' . $this->dbService->prefixTable('pages') . 'as p left join ' . $this->dbService->prefixTable('links') . "as l on p.tag = l.to_tag where l.to_tag is NULL and p.latest = 'Y' and tag = '" . $this->dbService->escape($tag) . "'"));
    }

    public function deleteOrphaned($tag)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        unset($this->ownersCache[$tag]);
        if (in_array($tag, $this->pageCache)) {
            unset($this->pageCache[$tag]);
        }
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('pages')} WHERE tag='{$this->dbService->escape($tag)}' OR comment_on='{$this->dbService->escape($tag)}'");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('links')} WHERE from_tag='{$this->dbService->escape($tag)}' ");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('acls')} WHERE page_tag='{$this->dbService->escape($tag)}' ");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('triples')} WHERE `resource`='{$this->dbService->escape($tag)}' and `property`='" . TripleStore::TYPE_URI . "' and `value`='" . EntryManager::TRIPLES_ENTRY_ID . "'");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('triples')} WHERE `resource`='{$this->dbService->escape($tag)}' and `property`='http://outils-reseaux.org/_vocabulary/metadata'");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('referrers')} WHERE page_tag='{$this->dbService->escape($tag)}' ");
        $this->tagsManager->deleteAll($tag);

        $errors = $this->eventDispatcher->yesWikiDispatch('page.deleted', [
            'id' => $tag,
        ]);
    }

    /**
     * SavePage
     * Sauvegarde un contenu dans une page donnee.
     *
     * @param string $body
     *                            Contenu a sauvegarder dans la page
     * @param string $tag
     *                            Nom de la page
     * @param string $comment_on
     *                            Indication si c'est un commentaire
     * @param bool   $bypass_acls
     *                            Indication si on bypasse les droits d'ecriture
     *
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     */
    public function save($tag, $body, $comment_on = '', $bypass_acls = false)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $user = $this->authController->getLoggedUserName();

        // check bypass of rights or write privilege
        $rights = $bypass_acls || ($comment_on ? $this->aclService->hasAccess(
            'comment',
            $comment_on
        ) : $this->aclService->hasAccess('write', $tag));

        if ($rights) {
            // is page new?
            if (!$oldPage = $this->getOne($tag)) {
                // LoadACL (if defined by acls)
                $defaultWrite = $this->aclService->load($tag, 'write', true)['list'];
                $defaultRead = $this->aclService->load($tag, 'read', true)['list'];
                $defaultComment = $this->aclService->load($tag, 'comment', true)['list'];

                // create default write acl. store empty write ACL for comments.
                $this->aclService->save($tag, 'write', ($comment_on ? $user : $defaultWrite));

                // create default read acl
                $this->aclService->save($tag, 'read', $defaultRead);

                // create default comment acl.
                $this->aclService->save($tag, 'comment', ($comment_on ? '' : $defaultComment));

                // current user is owner; if user is logged in! otherwise, no owner.
                if ($this->authController->getLoggedUser()) {
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
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = '" . $this->dbService->escape($tag) . "'");

            // add new revision
            $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('pages') . "SET tag = '" . $this->dbService->escape($tag) . "', " . ($comment_on ? "comment_on = '" . $this->dbService->escape($comment_on) . "', " : '') . 'time = now(), ' . "owner = '" . $this->dbService->escape($owner) . "', " . "user = '" . $this->dbService->escape($user) . "', " . "latest = 'Y', " . "body = '" . $this->dbService->escape(chop($body)) . "', " . "body_r = ''");

            unset($this->pageCache[$tag]);
            $this->ownersCache[$tag] = $owner;

            $errors = $this->eventDispatcher->yesWikiDispatch(empty($oldPage) ? 'page.created' : 'page.updated', [
                'id' => $tag,
                'data' => [
                    'tag' => $tag,
                    'body' => $body,
                    'comment_on' => $comment_on,
                    'owner' => $owner,
                    'user' => $user,
                ],
            ]);

            return 0;
        } else {
            return 1;
        }
    }

    public function getOwner($tag = '', $time = '')
    {
        if (!$tag = trim($tag)) {
            $tag = $this->wiki->GetPageTag();
        }

        if (!isset($this->ownersCache[$tag])) {
            if (empty($time) && isset($this->pageCache[$tag])) {
                $this->ownersCache[$tag] = $this->pageCache[$tag]['owner'] ?? null;
            } else {
                $timeQuery = $time ? "time = '{$this->dbService->escape($time)}'" : "latest = 'Y'";
                $page = $this->dbService->loadSingle(
                    "SELECT `owner` FROM {$this->dbService->prefixTable('pages')} " .
                    "WHERE tag = '{$this->dbService->escape($tag)}' AND {$timeQuery} " .
                    'LIMIT 1'
                );
                $this->ownersCache[$tag] = $page['owner'] ?? null;
            }
        }

        return $this->ownersCache[$tag];
    }

    public function setOwner($tag, $user)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->userManager->getOneByName($user)) {
            return;
        }

        $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . "SET owner = '" . $this->dbService->escape($user) . "' WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'Y' LIMIT 1");
        $this->ownersCache[$tag] = $user;
    }

    public function getMetadata($tag): ?array
    {
        $metadata = $this->tripleStore->getOne($tag, 'http://outils-reseaux.org/_vocabulary/metadata', '', '');

        if (!empty($metadata)) {
            if (YW_CHARSET != 'UTF-8') {
                return array_map(function ($value) {
                    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
                }, json_decode($metadata, true));
            } else {
                return json_decode($metadata, true);
            }
        } else {
            return null;
        }
    }

    public function setMetadata($tag, $metadata)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $previousMetadata = $this->getMetadata($tag);

        if ($previousMetadata) {
            $metadata = array_merge($previousMetadata, $metadata);
            $this->tripleStore->delete($tag, 'http://outils-reseaux.org/_vocabulary/metadata', null, '', '');
        }

        if (YW_CHARSET != 'UTF-8') {
            $metadata = json_encode(array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $metadata));
        } else {
            $metadata = json_encode($metadata);
        }

        return $this->tripleStore->create($tag, 'http://outils-reseaux.org/_vocabulary/metadata', $metadata, '', '');
    }

    /**
     * use Guard to checkACL for entries.
     *
     * @param string|null $userNameForCheckingACL if empty uses the connected user
     *
     * @return array $pages
     */
    private function checkEntriesACL(array $pages, ?string $tag = null, ?string $userNameForCheckingACL = null): array
    {
        if ($this->wiki->UserIsAdmin($userNameForCheckingACL)) {
            // do not check following tests to be faster because admins can see anything
            return $pages;
        }

        // affect cache before checking acls
        foreach ($pages as $page) {
            $this->cacheOwner($page);
        }

        // not possible to init the EntryManager or Guard in the constructor because of circular reference problem
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $guard = $this->wiki->services->get(Guard::class);
        $allEntriesTags = empty($tag) ? $entryManager->getAllEntriesTags()
            : ($entryManager->isEntry($tag) ? [$tag] : null);
        if (empty($allEntriesTags)) {
            return $pages;
        }
        $pages = array_map(function ($page) use ($guard, $allEntriesTags, $userNameForCheckingACL) {
            return (isset($page['tag']) &&
                    in_array($page['tag'], $allEntriesTags)
            ) ? $guard->checkAcls($page, $page['tag'], $userNameForCheckingACL)
                    : $page;
        }, $pages);

        return $pages;
    }
}

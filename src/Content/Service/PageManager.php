<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Wiki;

class PageManager
{
    protected $aclService;
    protected $authenticationService;
    protected $dbService;
    protected $eventDispatcher;
    protected $params;
    protected $hibernationService;
    protected $tagsManager;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;

    protected $ownersCache; // different cache because to set at the same time to prevent infinite loop
    protected $pageCache;
    /** lazily fetches AdministrativeLogService: it depends on PageManager, so injecting it directly would be a constructor cycle */
    protected ContainerInterface $container;

    public function __construct(
        AclService $aclService,
        AuthenticationService $authenticationService,
        DbService $dbService,
        EventDispatcher $eventDispatcher,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        TagsManager $tagsManager,
        TripleStore $tripleStore,
        UserManager $userManager,
        Wiki $wiki,
        ContainerInterface $container
    ) {
        $this->aclService = $aclService;
        $this->container = $container;
        $this->authenticationService = $authenticationService;
        $this->dbService = $dbService;
        $this->eventDispatcher = $eventDispatcher;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
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
                // metadata is versioned along with body: this revision's own column value,
                // not always-latest, so reverting/reading an old revision sees that
                // revision's metadata (ACLs, theme, ...), not the current one
                $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
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
     * Returns whether $tag is already in use anywhere in the global Content tag namespace --
     * type-agnostic by construction, since every Content type (pages, bazar entries today;
     * forms and users once tickets 05/06 land) is a row in this same `pages` table, so a
     * single check against the `tag` column covers all of them without needing to know which
     * type currently holds it.
     */
    public function tagExists(string $tag): bool
    {
        return (bool)$this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = '{$this->dbService->escape($tag)}' LIMIT 1");
    }

    /**
     * How many sequential numeric suffixes suggestFreeTag() tries (JohnDoe2, JohnDoe3, ...)
     * before giving up on a "nice" suggestion and falling back to a random one. Bounds the
     * number of serial existence-check queries a single call can make -- without this, a tag
     * with many pre-existing sequential collisions (JohnDoe2..JohnDoeN) would cost N queries.
     */
    private const MAX_SEQUENTIAL_SUFFIX_ATTEMPTS = 100;

    /**
     * Resolves a tag-creation collision (ADR-0001): if $desiredTag is free, returns it
     * unchanged; otherwise suggests the first numeric-suffixed alternative (JohnDoe ->
     * JohnDoe2, JohnDoe3, ...) that's itself confirmed free at suggestion time, rather than
     * failing with no path forward. Callers creating new Content (forms, users, ...) use this
     * to pick a tag; it doesn't reserve anything or touch the database itself, so a caller
     * still needs to actually create the row promptly to avoid a race with a concurrent
     * request picking the same suggestion.
     */
    public function suggestFreeTag(string $desiredTag): string
    {
        if (!$this->tagExists($desiredTag)) {
            return $desiredTag;
        }

        // slugs (generated tags, ADR-0010) suffix as `my-tag-2`; CamelCase-style
        // user-chosen tags keep the historical bare-number `MyTag2` convention
        $separator = str_contains($desiredTag, '-') || strtolower($desiredTag) === $desiredTag ? '-' : '';
        for ($suffix = 2; $suffix <= self::MAX_SEQUENTIAL_SUFFIX_ATTEMPTS + 1; $suffix++) {
            $candidate = $desiredTag . $separator . $suffix;
            if (!$this->tagExists($candidate)) {
                return $candidate;
            }
        }

        // pathological case: every sequential suffix up to the cap is already taken --
        // fall back to a short random one instead of continuing to query forever
        do {
            $candidate = $desiredTag . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        } while ($this->tagExists($candidate));

        return $candidate;
    }

    /**
     * Renames a Content row's identity: updates `tag` (and any `comment_on` referencing it)
     * across every revision, preserving history under the new identity. A generic `pages`
     * primitive -- deliberately narrow, it doesn't touch `links`/`referrers`/`triples`, since
     * whether/how those need updating is specific to the Content type doing the renaming
     * (e.g. a form moves its own TYPE_URI triple and records a former-tag alias itself, see
     * FormManager::renameTag()).
     */
    public function renameTag(string $oldTag, string $newTag): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->tagExists($oldTag)) {
            throw new \Exception("Cannot rename '$oldTag': no such page");
        }
        if ($this->tagExists($newTag)) {
            throw new \Exception("Cannot rename '$oldTag' to '$newTag': tag already taken");
        }

        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET tag = '{$this->dbService->escape($newTag)}' WHERE tag = '{$this->dbService->escape($oldTag)}'");
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET comment_on = '{$this->dbService->escape($newTag)}' WHERE comment_on = '{$this->dbService->escape($oldTag)}'");

        unset($this->pageCache[$oldTag]);
        unset($this->ownersCache[$oldTag]);
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

    /**
     * @param bool $bypassAcls do not check acl (and, since ticket 06, skip users-type Field
     *                         ACL redaction too) -- needed by revertToRevision(), which reads
     *                         a revision's TRUE stored data to write it back, not to display
     *                         it. Getting this wrong silently corrupts data rather than just
     *                         hiding it: getById() is also reachable from a genuine display
     *                         path (Wiki::LoadPageById(), used by the RSS revision-diff view),
     *                         which must keep bypassAcls=false so it still redacts sensitive
     *                         fields on that path.
     */
    public function getById($id, bool $bypassAcls = false): ?array
    {
        $page = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('pages') . "where id = '" . $this->dbService->escape($id) . "' limit 1");
        if ($page) {
            $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
        }
        if (!$bypassAcls) {
            $page = $this->checkEntriesACL([$page], $page['tag'])[0];
        }

        return $page;
    }

    /**
     * Revert a page to a prior revision (identified by its `id`, not `time` -- `time` has
     * only second-granularity and isn't guaranteed unique across revisions).
     *
     * Selective by default (ADR-0002): restores `body` only, leaving the current revision's
     * `metadata` (ACLs, theme, ...) untouched, so restoring old wording can't silently reopen
     * access that was deliberately tightened since. Pass $fullRevert=true for the separate,
     * explicit action that also restores that revision's exact metadata.
     */
    public function revertToRevision($tag, $revisionId, bool $fullRevert = false): int
    {
        // bypassAcls=true: this reads the revision's TRUE data to restore it, not to
        // display it -- redaction here (e.g. a users-type page's password, always hidden
        // even from admins) would silently persist a blanked value via save() below,
        // corrupting the account rather than just hiding it from view (found via ticket
        // 06's code review). The caller's actual write permission on $tag is still
        // enforced by save() itself, unaffected by this bypass.
        $target = $this->getById($revisionId, true);
        if (!$target || $target['tag'] !== $tag) {
            throw new \Exception("Revision '$revisionId' does not belong to page '$tag'");
        }

        $result = $this->save($tag, $target['body'], $target['comment_on'] ?? '');

        if ($fullRevert) {
            $this->replaceMetadata($tag, $target['metadatas']);
        }

        return $result;
    }

    /**
     * Overwrites the *current* latest revision's metadata in place (no new revision, no
     * merge) -- only meaningful as the second half of revertToRevision()'s full-revert case,
     * completing that one logical action on the row save() just created a moment ago.
     * Not a general-purpose replacement for setMetadata(), which merges and versions.
     */
    private function replaceMetadata($tag, ?array $metadata): void
    {
        $encoded = empty($metadata) ? 'NULL' : "'" . $this->dbService->escape($this->encodeMetadata($metadata)) . "'";
        $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET metadata = {$encoded} WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'Y'");
        unset($this->pageCache[$tag]);
    }

    public function getRevisions($pageTag, $limit = 10000)
    {
        $userCol = $this->dbService->quoteIdentifier('user');

        return $this->checkEntriesACL($this->dbService->loadAll("
            SELECT id, time, $userCol AS user FROM {$this->dbService->prefixTable('pages')}
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
        $userCol = $this->dbService->quoteIdentifier('user');
        if (!empty($minDate)) {
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' and time >= '$minDate' order by time desc")) {
                // foreach ($pages as $page) {
                //    $this->cache($page);
                // }
                return $pages;
            }
        } else {
            $limit = (int)$limit;
            $limit = ($limit < 1) ? 50 : $limit;
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and comment_on = '' order by time desc limit $limit")) {
                // foreach ($pages as $page) {
                //    $this->cache($page);
                // }
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
            SELECT tag,owner FROM {$this->dbService->prefixTable('pages')} WHERE LATEST = 'Y'
        SQL;

        // append request to filter on acls during the request
        if (!$this->aclService->isAdmin()) {
            $aclRequest = $this->aclService->updateRequestWithACL();
            $sqlRequest .= !empty($aclRequest) ? ' AND ' . $aclRequest : '';
        }
        $sqlRequest .= ' ORDER BY tag';
        $pages = $this->dbService->loadAll($sqlRequest);

        return array_map(function ($page) {
            // cache page's owner to prevent reload of page from sql or infinite loop in some case
            $this->cacheOwner($page);

            return $page['tag'];
        }, $pages);
    }

    public function getCreateTime($pageTag)
    {
        $sql = 'SELECT time FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE tag = '" . $this->dbService->escape($pageTag) . "'"
            . " AND comment_on = ''"
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
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        unset($this->ownersCache[$tag]);
        // was `in_array($tag, $this->pageCache)`: pageCache is keyed BY tag, so that searched
        // cached page arrays for a value equal to the tag string -- never true, meaning the
        // stale cached page always survived deletion (see PageManagerMetadataTest for the
        // regression this caused: a page recreated with the same tag right after deletion
        // returns the deleted page's data from cache instead of the fresh one)
        unset($this->pageCache[$tag]);
        // ACLs live in the pages row's own metadata column now, not a separate acls table --
        // deleting the row (below) already removes them, no separate ACL delete needed
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('pages')} WHERE tag='{$this->dbService->escape($tag)}' OR comment_on='{$this->dbService->escape($tag)}'");
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('links')} WHERE from_tag='{$this->dbService->escape($tag)}' ");
        $this->tripleStore->deleteAll($tag, '');
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
     * @param bool   $forcedDate
     *                            if null use current date for page creation time, otherwise use this value
     *
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     */
    public function save($tag, $body, $comment_on = '', $bypass_acls = false, $forcedDate = null): int
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $user = $this->authenticationService->getLoggedUserName();

        // check bypass of rights or write privilege
        $rights = $bypass_acls || ($comment_on ? $this->aclService->hasAccess(
            'comment',
            $comment_on
        ) : $this->aclService->hasAccess('write', $tag));

        if ($rights) {
            // is page new?
            $initialMetadata = null;
            if (!$oldPage = $this->getOne($tag)) {
                // Compute default ACLs (ACLs now live in metadata, a column on the `pages`
                // row itself -- which doesn't exist yet for a brand-new page, so this can't
                // go through AclService::save() the way an edit to an existing page's ACLs
                // does; AclService::load() is read-only and has no such ordering problem, so
                // it's still used for that half). Built directly into the first INSERT below.
                $defaultWrite = $this->aclService->load($tag, 'write', true)['list'];
                $defaultRead = $this->aclService->load($tag, 'read', true)['list'];
                $defaultComment = $this->aclService->load($tag, 'comment', true)['list'];

                $initialMetadata = ['acls' => [
                    // empty write ACL for comments: only the comment's author, via `owner`
                    // below, per the pre-existing comment-ACL convention
                    'write' => $comment_on ? $user : $defaultWrite,
                    'read' => $defaultRead,
                    'comment' => $comment_on ? '' : $defaultComment,
                ]];

                // current user is owner; if user is logged in! otherwise, no owner.
                if ($this->authenticationService->getLoggedUser()) {
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

                // don't save if body didn't change
                if (rtrim($oldPage['body']) == rtrim($body)) {
                    return 0;
                }
            }

            // set all other revisions to old
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = '" . $this->dbService->escape($tag) . "'");

            // use forcedDate if present
            $time = $this->dbService->now();
            if (!empty($forcedDate)) {
                $time = "'" . $this->dbService->escape($forcedDate) . "'";
            }

            // add new revision
            $userCol = $this->dbService->quoteIdentifier('user');
            $columns = ['tag', 'time', 'owner', $userCol, 'latest', 'body', 'body_r', 'metadata'];
            $values = [
                "'" . $this->dbService->escape($tag) . "'",
                $time,
                "'" . $this->dbService->escape($owner) . "'",
                "'" . $this->dbService->escape($user) . "'",
                "'Y'",
                "'" . $this->dbService->escape(chop($body)) . "'",
                "''",
                // metadata (ACLs, theme, ...) isn't part of this edit -- carry the previous
                // revision's value forward unchanged, same as owner/comment_on above (or the
                // freshly-computed default ACLs for a brand-new page, see above)
                $initialMetadata !== null
                    ? "'" . $this->dbService->escape($this->encodeMetadata($initialMetadata)) . "'"
                    : (empty($oldPage['metadata']) ? 'NULL' : "'" . $this->dbService->escape($oldPage['metadata']) . "'"),
            ];
            if ($comment_on) {
                $columns[] = 'comment_on';
                $values[] = "'" . $this->dbService->escape($comment_on) . "'";
            }
            $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('pages') . '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');

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
        }

        return 1;
    }

    public function getOwner($tag = '', $time = '')
    {
        if (!$tag = trim($tag)) {
            $tag = $this->wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
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
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->userManager->getOneByName($user)) {
            return;
        }

        $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . "SET owner = '" . $this->dbService->escape($user) . "' WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'Y'");
        $this->ownersCache[$tag] = $user;
    }

    public function getMetadata($tag): ?array
    {
        $page = $this->dbService->loadSingle("SELECT metadata FROM {$this->dbService->prefixTable('pages')} WHERE tag = '{$this->dbService->escape($tag)}' AND latest = 'Y' LIMIT 1");

        return $this->decodeMetadata($page['metadata'] ?? null);
    }

    /**
     * Metadata is versioned along with content (ADR-0002): changing it creates a new
     * `pages` revision, the same as an edit to body, carrying the current body forward
     * unchanged -- so permission/metadata history stays reconstructable and revertable
     * separately from content (see PageManager::save()'s revisioning, which this mirrors).
     */
    public function setMetadata($tag, $metadata)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $oldPage = $this->getOne($tag, null, false, true);
        if (!$oldPage) {
            throw new \Exception("Cannot set metadata on '$tag': no such page");
        }

        $previousMetadata = $oldPage['metadatas'] ?? null;
        if ($previousMetadata) {
            $metadata = array_merge($previousMetadata, $metadata);
        }

        // don't create a revision if nothing actually changed, same as save()'s
        // "don't save if body didn't change" guard
        if ($metadata == $previousMetadata) {
            return false;
        }

        $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = '" . $this->dbService->escape($tag) . "'");

        $userCol = $this->dbService->quoteIdentifier('user');
        $columns = ['tag', 'time', 'owner', $userCol, 'latest', 'body', 'body_r', 'metadata'];
        $values = [
            "'" . $this->dbService->escape($tag) . "'",
            $this->dbService->now(),
            "'" . $this->dbService->escape($oldPage['owner']) . "'",
            "'" . $this->dbService->escape($this->authenticationService->getLoggedUserName()) . "'",
            "'Y'",
            "'" . $this->dbService->escape($oldPage['body']) . "'",
            "''",
            "'" . $this->dbService->escape($this->encodeMetadata($metadata)) . "'",
        ];
        if (!empty($oldPage['comment_on'])) {
            $columns[] = 'comment_on';
            $values[] = "'" . $this->dbService->escape($oldPage['comment_on']) . "'";
        }
        $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('pages') . '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');

        unset($this->pageCache[$tag]);

        $this->eventDispatcher->yesWikiDispatch('page.updated', [
            'id' => $tag,
            'data' => [
                'tag' => $tag,
                'body' => $oldPage['body'],
                'comment_on' => $oldPage['comment_on'] ?? '',
                'owner' => $oldPage['owner'],
                'user' => $this->authenticationService->getLoggedUserName(),
            ],
        ]);

        return true;
    }

    private function decodeMetadata(?string $rawJson): ?array
    {
        if (empty($rawJson)) {
            return null;
        }
        if (YW_CHARSET != 'UTF-8') {
            return array_map(function ($value) {
                return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
            }, json_decode($rawJson, true));
        }

        return json_decode($rawJson, true);
    }

    private function encodeMetadata(array $metadata): string
    {
        if (YW_CHARSET != 'UTF-8') {
            return json_encode(array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $metadata));
        }

        return json_encode($metadata);
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
        if ($this->aclService->isAdmin($userNameForCheckingACL)) {
            // do not check following tests to be faster because admins can see anything
            return $pages;
        }

        // affect cache before checking acls
        foreach ($pages as $page) {
            $this->cacheOwner($page);
        }

        // not possible to init the EntryManager, UserManager or Guard in the constructor
        // because of circular reference problem
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $userManager = $this->wiki->services->get(UserManager::class);
        $guard = $this->wiki->services->get(Guard::class);
        $allEntriesTags = empty($tag) ? $entryManager->getAllEntriesTags()
            : ($entryManager->isEntry($tag) ? [$tag] : null);
        $allUserTags = empty($tag) ? $userManager->getAllUserTags()
            : ($userManager->isUserTag($tag) ? [$tag] : null);
        if (empty($allEntriesTags) && empty($allUserTags)) {
            return $pages;
        }
        $pages = array_map(function ($page) use ($guard, $allEntriesTags, $allUserTags, $userNameForCheckingACL) {
            if (!isset($page['tag'])) {
                return $page;
            }
            if (!empty($allEntriesTags) && in_array($page['tag'], $allEntriesTags)) {
                $page = $guard->checkAcls($page, $page['tag'], $userNameForCheckingACL);
            }
            if (!empty($allUserTags) && in_array($page['tag'], $allUserTags)) {
                $page = $guard->checkUserAcls($page, $page['tag'], $userNameForCheckingACL);
            }

            return $page;
        }, $pages);

        return $pages;
    }

    private function duplicate($sourceTag, $destinationTag): bool
    {
        $result = false;
        $this->container->get(AdministrativeLogService::class)->log($this->authenticationService->getLoggedUserName(), 'Duplication de la page ""' . $sourceTag . '"" vers la page ""' . $destinationTag . '""');

        return $result;
    }
}

<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Exception\ReservedTagException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\TagsManager;

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

    protected $ownersCache; // different cache because to set at the same time to prevent infinite loop
    protected $pageCache;
    /** @var array<string, string|null> tag => `pages`.`type`, or null for a tag with no row */
    protected array $typeCache = [];
    /**
     * tag => the latest revision as stored, before any Field ACL redaction.
     *
     * Deliberately separate from $pageCache, and **only ever served to a caller that asked
     * to bypass ACLs**. Those callers -- UserManager resolving an account, FormManager
     * loading a form, FileManager reading a file -- were re-reading the same row on every
     * call because the existing cache refuses to hold an unredacted row, which is right: a
     * display path must never be handed one. Two caches, one per shape, rather than one
     * cache that has to remember which shape it holds.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $rawPageCache = [];
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
        if ($bypassAcls && !$time && $cache && array_key_exists($tag, $this->rawPageCache)) {
            return $this->rawPageCache[$tag];
        }
        if (!$bypassAcls && !$time && $cache && empty($userNameForCheckingACL) && (($cachedPage = $this->getCached($tag)) !== false)) {
            $page = $cachedPage;
        } else {
            // load page
            // the revision clause is a *shape*, so it stays in the text; only its value binds
            $timeQuery = $time ? "{$this->dbService->quoteIdentifier('time')} = ?" : "latest = 'Y'";
            $page = $this->dbService->loadSingle("
                SELECT * FROM {$this->dbService->prefixTable('pages')}
                WHERE tag = ? AND {$timeQuery}
                LIMIT 1
            ", $time ? [$tag, $time] : [$tag]);

            // set ownersCache before using guard
            $this->cacheOwner($page);

            if ($page) {
                // metadata is versioned along with body: this revision's own column value,
                // not always-latest, so reverting/reading an old revision sees that
                // revision's metadata (ACLs, theme, ...), not the current one
                $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
                $page['body'] = PageBody::decode($page['body'] ?? null);
            }

            // remembered before redaction, and by both paths: a filtered read has already
            // paid for the row, so the next unredacted caller should not pay again
            if (!$time) {
                $this->rawPageCache[$tag] = $page;
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
        return (bool)$this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? LIMIT 1", [$tag]);
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
     *
     * A tag the router owns (ticket 20) is treated exactly like a taken one and suffixed
     * away from. That is what keeps every *generated* tag safe without any caller having to
     * know the reserved list: a user signing up as `api` keeps the username `api` and gets
     * the page tag `api2`, because a username is never slugified or rewritten -- only the
     * tag it is stored under moves.
     */
    public function suggestFreeTag(string $desiredTag): string
    {
        if (!$this->tagIsUnavailable($desiredTag)) {
            return $desiredTag;
        }

        // slugs (generated tags, ADR-0010) suffix as `my-tag-2`; CamelCase-style
        // user-chosen tags keep the historical bare-number `MyTag2` convention
        $separator = str_contains($desiredTag, '-') || strtolower($desiredTag) === $desiredTag ? '-' : '';
        for ($suffix = 2; $suffix <= self::MAX_SEQUENTIAL_SUFFIX_ATTEMPTS + 1; $suffix++) {
            $candidate = $desiredTag . $separator . $suffix;
            if (!$this->tagIsUnavailable($candidate)) {
                return $candidate;
            }
        }

        // pathological case: every sequential suffix up to the cap is already taken --
        // fall back to a short random one instead of continuing to query forever
        do {
            $candidate = $desiredTag . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        } while ($this->tagIsUnavailable($candidate));

        return $candidate;
    }

    /**
     * The two reasons a tag cannot be used: something already has it, or nobody may ever
     * have it. Deliberately not folded into tagExists(), which answers a narrower question
     * that callers legitimately ask on its own ("is there a row here?") -- a reserved tag
     * has no row.
     */
    private function tagIsUnavailable(string $tag): bool
    {
        return ReservedTags::isReserved($tag) || $this->tagExists($tag);
    }

    /**
     * Renames a Content row's identity: updates `tag` (and any `parent` referencing it)
     * across every revision, preserving history under the new identity. A generic `pages`
     * primitive -- deliberately narrow, it doesn't touch `triples`, since
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
        // renaming *off* a reserved tag is exactly what the ticket-20 migration does, so only
        // the destination is guarded
        if (ReservedTags::isReserved($newTag)) {
            throw new ReservedTagException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $newTag]));
        }

        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET tag = ? WHERE tag = ?", [$newTag, $oldTag]);
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET parent = ? WHERE parent = ?", [$newTag, $oldTag]);

        unset($this->pageCache[$oldTag]);
        unset($this->rawPageCache[$oldTag]);
        unset($this->typeCache[$oldTag]);
        // both names: the ACLs left the old tag and arrived at the new one, and anything
        // that asked about either before the rename now holds an answer about the wrong row
        $this->aclService->forget($oldTag);
        $this->aclService->forget($newTag);
        unset($this->ownersCache[$oldTag]);

        // a rename fires no page.* event -- nothing about the Content changed, only its
        // name -- so the search index is told directly. Without this the index keeps
        // answering under the old tag, and every result for the renamed Content 404s
        // (ticket 18).
        $this->container->get(SearchIndexer::class)->rename($oldTag, $newTag);
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
        // a loaded row already carries its type, so asking what kind of Content it is costs
        // nothing after this -- which is the point of the column (ticket 27): rendering a
        // list of fifty entries reads fifty rows and asks zero type questions of the database
        if (!empty($page['tag']) && isset($page['type'])) {
            $this->typeCache[$page['tag']] = (string)$page['type'];
        }
    }

    /**
     * What kind of Content this tag holds -- `PageType::PAGE`, `ENTRY`, `USER`, ... -- or
     * null when no row has that tag (ticket 27).
     *
     * ACL-blind on purpose: the *kind* of a Content is not a secret, and every caller
     * (EntryManager::isEntry(), UserManager::isUserTag(), the router) needs the answer before
     * it can decide who may see the thing. Reading it through getOne() would also make an
     * unreadable page indistinguishable from a missing one.
     */
    public function typeOf(string $tag): ?string
    {
        if ($tag === '') {
            return null;
        }
        if (!array_key_exists($tag, $this->typeCache)) {
            // a row already read this request answers this, and so does a row already found
            // to be missing: `cacheOwner()` can only remember a page that exists, so without
            // this a getOne() that came back empty was followed by a second query asking the
            // type of the same absent page -- which is what `checkEntriesACL()` does, inside
            // that very getOne()
            if (array_key_exists($tag, $this->rawPageCache)) {
                $known = $this->rawPageCache[$tag];
                $this->typeCache[$tag] = $known === null ? null : (string)($known['type'] ?? PageType::DEFAULT);
            } else {
                $row = $this->dbService->loadSingle(
                    "SELECT {$this->dbService->quoteIdentifier('type')} FROM {$this->dbService->prefixTable('pages')}"
                    . ' WHERE tag = ? AND latest = \'Y\' LIMIT 1',
                    [$tag]
                );
                $this->typeCache[$tag] = $row === null ? null : (string)$row['type'];
            }
        }

        return $this->typeCache[$tag];
    }

    public function isType(string $tag, string $type): bool
    {
        return $this->typeOf($tag) === $type;
    }

    /**
     * Every tag of a given type.
     *
     * @return list<string>
     */
    public function tagsOfType(string $type): array
    {
        $rows = $this->dbService->loadAll(
            "SELECT tag FROM {$this->dbService->prefixTable('pages')}"
            . " WHERE latest = 'Y' AND {$this->dbService->quoteIdentifier('type')} = ?"
            . ' ORDER BY tag',
            [$type]
        );

        return array_values(array_map(static fn (array $row): string => (string)$row['tag'], $rows));
    }

    /**
     * Forget everything remembered about this tag.
     *
     * For whoever writes a `pages` row without going through this service --
     * AclService::writeMetadataAcls() inserts its own revision, because PageManager depends
     * on AclService and the reverse would be a cycle. Each side tells the other when it
     * writes behind its back; this is that call in the other direction.
     */
    public function forget(string $tag): void
    {
        unset($this->pageCache[$tag], $this->rawPageCache[$tag], $this->typeCache[$tag], $this->ownersCache[$tag]);
    }

    /** Remembered so a freshly created row answers typeOf() without another query. */
    public function cacheType(string $tag, ?string $type): void
    {
        $this->typeCache[$tag] = $type;
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
        $page = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('pages') . 'where id = ? limit 1', [$id]);
        if ($page) {
            $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
            $page['body'] = PageBody::decode($page['body'] ?? null);
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

        $result = $this->save($tag, $target['body'], $target['parent'] ?? '');

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
        // `null` rather than the literal string 'NULL' spliced into the statement: a bound null
        // is a real SQL NULL, which is what "this row has no metadata" has to be. The ternary
        // existed because escape() casts through (string) and would have written '' instead.
        $encoded = empty($metadata) ? null : $this->encodeMetadata($metadata);
        $this->dbService->query(
            'UPDATE' . $this->dbService->prefixTable('pages') . "SET metadata = ? WHERE tag = ? AND latest = 'Y'",
            [$encoded, $tag]
        );
        unset($this->pageCache[$tag]);
        unset($this->rawPageCache[$tag]);
        unset($this->typeCache[$tag]);
        $this->aclService->forget($tag);
    }

    public function getRevisions($pageTag, $limit = 10000)
    {
        $userCol = $this->dbService->quoteIdentifier('user');

        // $limit is bound as an int rather than interpolated: it is a public parameter, so
        // "nobody passes anything odd today" is the only thing that made the old form safe.
        return $this->checkEntriesACL($this->dbService->loadAll("
            SELECT id, time, $userCol AS user FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ?
            ORDER BY time DESC
            LIMIT ?
        ", [$pageTag, (int)$limit]), $pageTag);
    }

    public function getPreviousRevision($page)
    {
        // `time` was interpolated with no escaping at all -- it comes off a row this code just
        // read, so it was never hostile, but it was one refactor away from being a value from
        // somewhere else. Both bind now, and `time` is a reserved word on some drivers.
        $timeCol = $this->dbService->quoteIdentifier('time');
        $previous = $this->dbService->loadSingle("
            SELECT * FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ? AND {$timeCol} < ?
            ORDER BY {$timeCol} DESC
            LIMIT 1
        ", [$page['tag'], $page['time']]);
        if ($previous) {
            $previous['metadatas'] = $this->decodeMetadata($previous['metadata'] ?? null);
            $previous['body'] = PageBody::decode($previous['body'] ?? null);
        }

        return $this->checkEntriesACL([$previous], $page['tag'])[0];
    }

    public function countRevisions($page)
    {
        return $this->dbService->countRows("
            SELECT * FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ?
        ", [$page]);
    }

    public function getRecentlyChanged($limit = 50, $minDate = ''): ?array
    {
        $userCol = $this->dbService->quoteIdentifier('user');
        if (!empty($minDate)) {
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and parent = '' and time >= '$minDate' order by time desc")) {
                // foreach ($pages as $page) {
                //    $this->cache($page);
                // }
                return $pages;
            }
        } else {
            $limit = (int)$limit;
            $limit = ($limit < 1) ? 50 : $limit;
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and parent = '' order by time desc limit $limit")) {
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
        $params = [];
        if (!$this->aclService->isAdmin()) {
            $aclRequest = $this->aclService->updateRequestWithACL();
            if (!$aclRequest->isEmpty()) {
                $sqlRequest .= ' AND ' . $aclRequest->sql;
                $params = $aclRequest->params;
            }
        }
        $sqlRequest .= ' ORDER BY tag';
        $pages = $this->dbService->loadAll($sqlRequest, $params);

        return array_map(function ($page) {
            // cache page's owner to prevent reload of page from sql or infinite loop in some case
            $this->cacheOwner($page);

            return $page['tag'];
        }, $pages);
    }

    public function getCreateTime($pageTag)
    {
        $timeCol = $this->dbService->quoteIdentifier('time');
        $sql = "SELECT {$timeCol} FROM " . $this->dbService->prefixTable('pages')
            . ' WHERE tag = ?'
            . " AND parent = ''"
            . " ORDER BY {$timeCol} ASC LIMIT 1";
        $page = $this->dbService->loadSingle($sql, [$pageTag]);
        if ($page) {
            return $page['time'];
        }

        return null;
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
        unset($this->rawPageCache[$tag]);
        unset($this->typeCache[$tag]);
        $this->aclService->forget($tag);
        // ACLs live in the pages row's own metadata column now, not a separate acls table --
        // deleting the row (below) already removes them, no separate ACL delete needed
        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? OR parent = ?", [$tag, $tag]);
        $this->tripleStore->deleteAll($tag, '');
        $this->tagsManager->deleteAll($tag);

        $errors = $this->eventDispatcher->yesWikiDispatch('page.deleted', [
            'id' => $tag,
        ]);
    }

    /**
     * SavePage
     * Sauvegarde un contenu dans une page donnee.
     *
     * @param string                  $tag         Nom de la page
     * @param array<array-key, mixed> $body        decoded body -- one shape for every Content type
     *                                             (ticket 09). Wiki markup goes under `content`.
     * @param string                  $parent      the page this one comments on, if any
     * @param bool                    $bypass_acls Indication si on bypasse les droits d'ecriture
     * @param string|null             $forcedDate  if null use current date for page creation time,
     *                                             otherwise use this value
     * @param string|null             $type        the row's PageType (ticket 27). Null means
     *                                             "whatever it already is" -- so an ordinary edit
     *                                             cannot silently retype a Content, and only the
     *                                             manager that creates a kind of row names it.
     *                                             A brand-new row with no type named is a page,
     *                                             or a comment when it has a parent.
     *
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     */
    public function save($tag, array $body, $parent = '', $bypass_acls = false, $forcedDate = null, ?string $type = null): int
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // Backstop for ticket 20. Every creation helper already picks its tag through
        // suggestFreeTag(), which skips reserved names, and the paths where a human types a
        // tag refuse one before getting here -- so this only fires when a caller invents a
        // tag without asking. Writing the row anyway would produce Content that nothing can
        // ever reach, which is worse than the failure.
        if (ReservedTags::isReserved((string)$tag)) {
            throw new ReservedTagException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $tag]));
        }
        $user = $this->authenticationService->getLoggedUserName();

        // check bypass of rights or write privilege
        $rights = $bypass_acls || ($parent ? $this->aclService->hasAccess(
            'comment',
            $parent
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
                    'write' => $parent ? $user : $defaultWrite,
                    'read' => $defaultRead,
                    'comment' => $parent ? '' : $defaultComment,
                ]];

                // current user is owner; if user is logged in! otherwise, no owner.
                if ($this->authenticationService->getLoggedUser()) {
                    $owner = $user;
                } else {
                    $owner = '';
                }

                $type ??= $parent ? PageType::COMMENT : PageType::DEFAULT;
            } else {
                // aha! page isn't new. keep owner!
                $owner = $oldPage['owner'];

                // ...and parent, eventualy?
                if ($parent == '') {
                    $parent = $oldPage['parent'];
                }

                // an edit is not a retyping: every revision of a Content is the same kind of
                // thing, so the type rides forward with the owner rather than being
                // recomputed from whatever this particular caller happened to pass
                $type ??= (string)($oldPage['type'] ?? PageType::DEFAULT);

                // don't save if body didn't change. Compared decoded and key-order-blind:
                // a string compare on JSON would both invent revisions out of a re-encode
                // that only moved keys around, and miss genuine no-ops.
                if (PageBody::equals($oldPage['body'], $body)) {
                    return 0;
                }
            }

            // Demoting the current revision and inserting the new one are one act: between the
            // two the row has no `latest = 'Y'` revision, and every read filters on that -- so a
            // failure in between does not damage the page, it makes it vanish while keeping all
            // its history. The cache updates and the event dispatch below stay OUTSIDE the
            // scope: neither is undone by a rollback, and a listener that sends mail has no
            // business running inside a transaction.
            $this->dbService->transactional(function () use (
                $tag,
                $owner,
                $user,
                $body,
                $type,
                $initialMetadata,
                $oldPage,
                $parent,
                $forcedDate
            ): void {
                // set all other revisions to old
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = ?", [$tag]);

                $this->insertRevision($tag, $owner, $user, $body, $type, $initialMetadata, $oldPage, $parent, $forcedDate);
            });

            unset($this->pageCache[$tag]);
            unset($this->rawPageCache[$tag]);
            unset($this->typeCache[$tag]);
            $this->aclService->forget($tag);
            $this->ownersCache[$tag] = $owner;

            $errors = $this->eventDispatcher->yesWikiDispatch(empty($oldPage) ? 'page.created' : 'page.updated', [
                'id' => $tag,
                'data' => [
                    'tag' => $tag,
                    'body' => $body,
                    'parent' => $parent,
                    'owner' => $owner,
                    'user' => $user,
                ],
            ]);

            return 0;
        }

        return 1;
    }

    /**
     * The INSERT half of save()'s revisioning, extracted so the transaction above reads as the
     * two statements it is.
     *
     * @param array<string, mixed>      $body
     * @param array<string, mixed>|null $initialMetadata
     * @param array<string, mixed>|null $oldPage
     */
    private function insertRevision(
        string $tag,
        ?string $owner,
        ?string $user,
        array $body,
        ?string $type,
        ?array $initialMetadata,
        ?array $oldPage,
        string $parent,
        ?string $forcedDate
    ): void {
        // add new revision.
        //
        // Column => value, bound. The (string) casts are deliberate rather than tidy-up:
        // escape() used to apply them, so dropping them would turn an absent owner or an
        // anonymous user from '' into a real NULL -- a behaviour change smuggled in under
        // a refactor. `metadata` is the one value that DOES become a real NULL, because
        // the old code spliced the literal string 'NULL' in for exactly that case.
        $columns = [
            'tag' => (string)$tag,
            'owner' => (string)$owner,
            $this->dbService->quoteIdentifier('user') => (string)$user,
            'latest' => 'Y',
            'body' => PageBody::encode($body),
            $this->dbService->quoteIdentifier('type') => (string)$type,
            // metadata (ACLs, theme, ...) isn't part of this edit -- carry the previous
            // revision's value forward unchanged, same as owner/parent above (or the
            // freshly-computed default ACLs for a brand-new page, see above)
            'metadata' => $initialMetadata !== null
                ? $this->encodeMetadata($initialMetadata)
                : (empty($oldPage['metadata']) ? null : $oldPage['metadata']),
        ];
        if ($parent) {
            $columns['parent'] = (string)$parent;
        }

        $names = array_keys($columns);
        $placeholders = array_fill(0, count($columns), '?');
        $params = array_values($columns);

        // `time` is the one column whose slot is not always a value: with no forced date it
        // is a driver-specific SQL expression (NOW(), datetime('now'), ...) that the
        // database has to evaluate, and a bound parameter would arrive as the literal text
        // "NOW()". Appended rather than kept in its historical second position because the
        // column list is explicit, so the order carries no meaning.
        $names[] = $this->dbService->quoteIdentifier('time');
        if (empty($forcedDate)) {
            $placeholders[] = $this->dbService->now();
        } else {
            $placeholders[] = '?';
            $params[] = $forcedDate;
        }

        $this->dbService->query(
            'INSERT INTO' . $this->dbService->prefixTable('pages')
            . '(' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    public function getOwner($tag = '', $time = '')
    {
        if (!$tag = trim($tag)) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }

        if (!isset($this->ownersCache[$tag])) {
            if (empty($time) && isset($this->pageCache[$tag])) {
                $this->ownersCache[$tag] = $this->pageCache[$tag]['owner'] ?? null;
            } else {
                $timeQuery = $time ? "{$this->dbService->quoteIdentifier('time')} = ?" : "latest = 'Y'";
                $page = $this->dbService->loadSingle(
                    "SELECT owner FROM {$this->dbService->prefixTable('pages')} " .
                        "WHERE tag = ? AND {$timeQuery} " .
                        'LIMIT 1',
                    $time ? [$tag, $time] : [$tag]
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

        $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . "SET owner = ? WHERE tag = ? AND latest = 'Y'", [(string)$user, $tag]);
        $this->ownersCache[$tag] = $user;
        // the row just changed, and both page caches hold a copy of it: keeping only
        // ownersCache current left every other reader with the previous owner. Latent while
        // the unredacted path re-read the row every time; a real bug the moment it stopped
        // (an account's `owner` is set right after its row is created, so the caller that
        // reads it back is the very next line).
        unset($this->pageCache[$tag], $this->rawPageCache[$tag]);
    }

    public function getMetadata($tag): ?array
    {
        // through getOne(), which has already decoded this row's metadata if anything has
        // read the page this request -- and almost always something has, since metadata is
        // asked about a page being rendered. A column-only SELECT of a row already in hand
        // is the shape that made a page render read `metadata` eight extra times.
        $page = $this->getOne($tag, null, true, true);

        return $page === null ? null : ($page['metadatas'] ?? null);
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

        // demote-then-insert, atomically: see the note in save(), which this mirrors
        $this->dbService->transactional(function () use ($tag, $oldPage, $metadata): void {
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = ?", [$tag]);
            $this->insertMetadataRevision($tag, $oldPage, $metadata);
        });
        unset($this->pageCache[$tag]);
        unset($this->rawPageCache[$tag]);
        unset($this->typeCache[$tag]);
        $this->aclService->forget($tag);

        $this->eventDispatcher->yesWikiDispatch('page.updated', [
            'id' => $tag,
            'data' => [
                'tag' => $tag,
                'body' => $oldPage['body'],
                'parent' => $oldPage['parent'] ?? '',
                'owner' => $oldPage['owner'],
                'user' => $this->authenticationService->getLoggedUserName(),
            ],
        ]);

        return true;
    }

    /**
     * The INSERT half of setMetadata()'s revisioning, so the transaction above reads as the two
     * statements it is. Mirrors insertRevision().
     *
     * @param array<string, mixed> $oldPage
     * @param array<string, mixed> $metadata
     */
    private function insertMetadataRevision(string $tag, array $oldPage, array $metadata): void
    {
        // mirrors save()'s INSERT: bound values, with `time` the one column carrying a SQL
        // expression instead. The (string) casts preserve escape()'s own cast, so an absent
        // owner stays '' rather than quietly becoming NULL.
        $columns = [
            'tag' => (string)$tag,
            'owner' => (string)$oldPage['owner'],
            $this->dbService->quoteIdentifier('user') => (string)$this->authenticationService->getLoggedUserName(),
            'latest' => 'Y',
            'body' => PageBody::encode($oldPage['body']),
            // carried forward, like owner and parent: writing metadata is not retyping the
            // Content, and a revision that defaulted to 'page' would turn every account,
            // file and entry into a page the first time its ACLs were touched
            $this->dbService->quoteIdentifier('type') => (string)($oldPage['type'] ?? PageType::DEFAULT),
            'metadata' => $this->encodeMetadata($metadata),
        ];
        if (!empty($oldPage['parent'])) {
            $columns['parent'] = (string)$oldPage['parent'];
        }

        $names = array_keys($columns);
        $placeholders = array_fill(0, count($columns), '?');
        $names[] = $this->dbService->quoteIdentifier('time');
        $placeholders[] = $this->dbService->now();

        $this->dbService->query(
            'INSERT INTO' . $this->dbService->prefixTable('pages')
            . '(' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')',
            array_values($columns)
        );
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
        $entryManager = $this->container->get(EntryManager::class);
        $userManager = $this->container->get(UserManager::class);
        $guard = $this->container->get(Guard::class);
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

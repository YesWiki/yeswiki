<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\SearchIndexer;

class PageManager
{
    protected AclService $aclService;
    protected AuthenticationService $authenticationService;
    protected DbService $dbService;
    protected EventDispatcher $eventDispatcher;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;
    protected TripleStore $tripleStore;
    protected UserManager $userManager;

    /** @var array<string, string|null> tag => the row's owner */
    protected $ownersCache;
    /** @var array<string, array<string, mixed>|null> tag => the latest revision, or null for a tag with no row */
    protected $pageCache;
    /**
     * @var array<string, string|null> tag => `pages`.`type`, or null for a tag with no row
     */
    protected array $typeCache = [];
    /**
     * tag => the latest revision as stored, before any Field ACL redaction.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $rawPageCache = [];
    /**
     * lazily fetches the Journal, which reaches back to PageManager through the ActorSource: injecting it directly would be a constructor cycle.
     */
    protected ContainerInterface $container;

    public function __construct(
        AclService $aclService,
        AuthenticationService $authenticationService,
        DbService $dbService,
        EventDispatcher $eventDispatcher,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
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
     *
     * @return array<string, mixed>|null
     */
    public function getOne($tag, $time = null, $cache = true, $bypassAcls = false, ?string $userNameForCheckingACL = null): ?array
    {
        if ($bypassAcls && !$time && $cache && array_key_exists($tag, $this->rawPageCache)) {
            return $this->rawPageCache[$tag];
        }
        if (!$bypassAcls && !$time && $cache && empty($userNameForCheckingACL) && (($cachedPage = $this->getCached($tag)) !== false)) {
            $page = $cachedPage;
        } else {
            $timeQuery = $time ? "{$this->dbService->quoteIdentifier('time')} = ?" : "latest = 'Y'";
            $page = $this->dbService->loadSingle("
                SELECT * FROM {$this->dbService->prefixTable('pages')}
                WHERE tag = ? AND {$timeQuery}
                LIMIT 1
            ", $time ? [$tag, $time] : [$tag]);

            $this->cacheOwner($page);

            if ($page) {
                $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
                $page['body'] = PageBody::decode($page['body'] ?? null);
            }

            if (!$time) {
                $this->rawPageCache[$tag] = $page;
            }

            if (!$bypassAcls && $page !== null) {
                $page = $this->checkEntriesACL([$page], $tag, $userNameForCheckingACL)[0];
            }

            if (!$bypassAcls && !$time) {
                $this->cache($page, $tag);
            } else {
                $this->unsetCacheOwner($page);
            }
        }

        return $page;
    }

    /**
     * Returns whether $tag is already in use anywhere in the global Content tag namespace -- type-agnostic by construction, since every Content type (pages, bazar entries today; forms and users once tickets 05/06 land) is a row in this same `pages` table, so a single check against the `tag` column covers all of them without needing to know which type currently holds it.
     */
    public function tagExists(string $tag): bool
    {
        return (bool)$this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? LIMIT 1", [$tag]);
    }

    /**
     * How many sequential numeric suffixes suggestFreeTag() tries (JohnDoe2, JohnDoe3, ...) before giving up on a "nice" suggestion and falling back to a random one.
     */
    private const MAX_SEQUENTIAL_SUFFIX_ATTEMPTS = 100;

    /**
     * Resolves a tag-creation collision (ADR-0001): if $desiredTag is free, returns it unchanged; otherwise suggests the first numeric-suffixed alternative (JohnDoe -> JohnDoe2, JohnDoe3, ...) that's itself confirmed free at suggestion time, rather than failing with no path forward.
     */
    public function suggestFreeTag(string $desiredTag): string
    {
        if (!$this->tagIsUnavailable($desiredTag)) {
            return $desiredTag;
        }

        $separator = str_contains($desiredTag, '-') || strtolower($desiredTag) === $desiredTag ? '-' : '';
        for ($suffix = 2; $suffix <= self::MAX_SEQUENTIAL_SUFFIX_ATTEMPTS + 1; $suffix++) {
            $candidate = $desiredTag . $separator . $suffix;
            if (!$this->tagIsUnavailable($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = $desiredTag . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        } while ($this->tagIsUnavailable($candidate));

        return $candidate;
    }

    /** The two reasons a tag cannot be used: something already has it, or nobody may ever have it. */
    private function tagIsUnavailable(string $tag): bool
    {
        return ReservedTags::isReserved($tag) || $this->tagExists($tag);
    }

    /**
     * Renames a Content row's identity: updates `tag` (and any `parent` referencing it) across every revision, preserving history under the new identity.
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

        if (ReservedTags::isReserved($newTag)) {
            throw new ReservedTagException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $newTag]));
        }

        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET tag = ? WHERE tag = ?", [$newTag, $oldTag]);
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET parent = ? WHERE parent = ?", [$newTag, $oldTag]);

        unset($this->pageCache[$oldTag]);
        unset($this->rawPageCache[$oldTag]);
        unset($this->typeCache[$oldTag]);

        $this->aclService->forget($oldTag);
        $this->aclService->forget($newTag);
        unset($this->ownersCache[$oldTag]);

        $this->container->get(SearchIndexer::class)->rename($oldTag, $newTag);
    }

    /**
     * Retrieves the cached version of a page.
     *
     * @param string $tag
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
     * @param array<string, mixed>|null $page
     *                                           The page (full) DB line or null if the page does not exists
     * @param string|null               $pageTag
     *                                           The tag of the page to cache. Defaults to $page['tag'] but is mandatory when $page === null
     */
    public function cache($page, $pageTag = null): void
    {
        $pageTag ??= $page['tag'] ?? null;
        if ($pageTag === null) {
            throw new \InvalidArgumentException('PageManager::cache() needs a tag when the page is null');
        }
        $this->pageCache[$pageTag] = $page;
    }

    /**
     * @param array<string, mixed>|null $page
     */
    public function cacheOwner($page): void
    {
        if (!empty($page['tag']) && isset($page['owner'])) {
            $this->ownersCache[$page['tag']] = $page['owner'];
        }

        if (!empty($page['tag']) && isset($page['type'])) {
            $this->typeCache[$page['tag']] = (string)$page['type'];
        }
    }

    /** What kind of Content this tag holds -- `PageType::PAGE`, `ENTRY`, `USER`, ... */
    public function typeOf(string $tag): ?string
    {
        if ($tag === '') {
            return null;
        }
        if (!array_key_exists($tag, $this->typeCache)) {
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

        return array_map(static fn (array $row): string => (string)$row['tag'], $rows);
    }

    /** Forget everything remembered about this tag. */
    public function forget(string $tag): void
    {
        unset($this->pageCache[$tag], $this->rawPageCache[$tag], $this->typeCache[$tag], $this->ownersCache[$tag]);
    }

    /** Remembered so a freshly created row answers typeOf() without another query. */
    public function cacheType(string $tag, ?string $type): void
    {
        $this->typeCache[$tag] = $type;
    }

    /**
     * @param array<string, mixed>|null $page
     */
    private function unsetCacheOwner($page): void
    {
        if (!empty($page['tag'])) {
            unset($this->ownersCache[$page['tag']]);
        }
    }

    /**
     * @param bool       $bypassAcls do not check acl (and, since ticket 06, skip users-type Field
     *                               ACL redaction too) -- needed by revertToRevision(), which reads
     *                               a revision's TRUE stored data to write it back, not to display
     *                               it. Getting this wrong silently corrupts data rather than just
     *                               hiding it: getById() is also reachable from a genuine display
     *                               path (Wiki::LoadPageById(), used by the RSS revision-diff view),
     *                               which must keep bypassAcls=false so it still redacts sensitive
     *                               fields on that path.
     * @param int|string $id
     *
     * @return array<string, mixed>|null
     */
    public function getById($id, bool $bypassAcls = false): ?array
    {
        $page = $this->dbService->loadSingle('select * from' . $this->dbService->prefixTable('pages') . 'where id = ? limit 1', [$id]);
        if ($page === null) {
            return null;
        }

        $page['metadatas'] = $this->decodeMetadata($page['metadata'] ?? null);
        $page['body'] = PageBody::decode($page['body'] ?? null);
        if (!$bypassAcls) {
            $page = $this->checkEntriesACL([$page], $page['tag'])[0];
        }

        return $page;
    }

    /**
     * Revert a page to a prior revision (identified by its `id`, not `time` -- `time` has only second-granularity and isn't guaranteed unique across revisions).
     *
     * @param string     $tag
     * @param int|string $revisionId
     */
    public function revertToRevision($tag, $revisionId, bool $fullRevert = false): int
    {
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
     * Overwrites the *current* latest revision's metadata in place (no new revision, no merge) -- only meaningful as the second half of revertToRevision()'s full-revert case, completing that one logical action on the row save() just created a moment ago.
     *
     * @param string                    $tag
     * @param array<string, mixed>|null $metadata
     */
    private function replaceMetadata($tag, ?array $metadata): void
    {
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

    /**
     * @param string $pageTag
     * @param int    $limit
     *
     * @return array<array-key, array<string, mixed>> one row per revision, newest first
     */
    public function getRevisions($pageTag, $limit = 10000)
    {
        $userCol = $this->dbService->quoteIdentifier('user');

        return $this->checkEntriesACL($this->dbService->loadAll("
            SELECT id, time, $userCol AS user FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ?
            ORDER BY time DESC
            LIMIT ?
        ", [$pageTag, (int)$limit]), $pageTag);
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>|null the revision before this one, null when it is the first
     */
    public function getPreviousRevision($page)
    {
        $timeCol = $this->dbService->quoteIdentifier('time');
        $previous = $this->dbService->loadSingle("
            SELECT * FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ? AND {$timeCol} < ?
            ORDER BY {$timeCol} DESC
            LIMIT 1
        ", [$page['tag'], $page['time']]);
        if ($previous === null) {
            return null;
        }

        $previous['metadatas'] = $this->decodeMetadata($previous['metadata'] ?? null);
        $previous['body'] = PageBody::decode($previous['body'] ?? null);

        return $this->checkEntriesACL([$previous], $page['tag'])[0];
    }

    /**
     * @param string $page the page's tag
     *
     * @return int
     */
    public function countRevisions($page)
    {
        return $this->dbService->countRows("
            SELECT * FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = ?
        ", [$page]);
    }

    /**
     * @param int    $limit
     * @param string $minDate
     *
     * @return array<array-key, array<string, mixed>>|null null when nothing changed
     */
    public function getRecentlyChanged($limit = 50, $minDate = ''): ?array
    {
        $userCol = $this->dbService->quoteIdentifier('user');
        if (!empty($minDate)) {
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and parent = '' and time >= '$minDate' order by time desc")) {
                return $pages;
            }
        } else {
            $limit = (int)$limit;
            $limit = ($limit < 1) ? 50 : $limit;
            if ($pages = $this->dbService->loadAll("select id, tag, time, $userCol AS user, owner from" . $this->dbService->prefixTable('pages') . "where latest = 'Y' and parent = '' order by time desc limit $limit")) {
                return $pages;
            }
        }

        return null;
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    public function getAll(): array
    {
        $pages = $this->dbService->loadAll(<<<SQL
            SELECT * FROM {$this->dbService->prefixTable('pages')} WHERE LATEST = 'Y' ORDER BY tag
        SQL);
        $pages = $this->checkEntriesACL($pages);

        return $pages;
    }

    /**
     * get readable page tags update page's owner to improve performances.
     *
     * @return string[] list of tags readble for current user
     */
    public function getReadablePageTags(): array
    {
        $sqlRequest = <<<SQL
            SELECT tag,owner FROM {$this->dbService->prefixTable('pages')} WHERE LATEST = 'Y'
        SQL;

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
            $this->cacheOwner($page);

            return $page['tag'];
        }, $pages);
    }

    /**
     * @param string $pageTag
     *
     * @return string|null when the page was first written, null when there is no such page
     */
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

    /**
     * @param string $tag
     */
    public function deleteOrphaned($tag): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // Read before the caches are dropped and the row is gone: asking afterwards would answer
        // from a cache this method just refilled, and leave it holding a type for a tag with no row.
        $type = $this->typeOf($tag);

        unset($this->ownersCache[$tag]);

        unset($this->pageCache[$tag]);
        unset($this->rawPageCache[$tag]);
        unset($this->typeCache[$tag]);
        $this->aclService->forget($tag);

        $this->dbService->query("DELETE FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? OR parent = ?", [$tag, $tag]);
        $this->tripleStore->deleteAll($tag, '');

        // Every deletion, whatever asked for it: this is where the row actually goes, and a
        // deletion leaves no revision behind to be the record of itself.
        $this->journal()->audit('content.delete', $tag, $type === null ? [] : ['type' => $type]);

        $errors = $this->eventDispatcher->yesWikiDispatch('page.deleted', [
            'id' => $tag,
        ]);
    }

    /**
     * SavePage Sauvegarde un contenu dans une page donnee.
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

        if (ReservedTags::isReserved((string)$tag)) {
            throw new ReservedTagException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => $tag]));
        }
        $user = $this->authenticationService->getLoggedUserName();

        $rights = $bypass_acls || ($parent ? $this->aclService->hasAccess(
            'comment',
            $parent
        ) : $this->aclService->hasAccess('write', $tag));

        if ($rights) {
            $initialMetadata = null;
            if (!$oldPage = $this->getOne($tag)) {
                $defaultWrite = $this->aclService->load($tag, 'write', true)['list'] ?? '';
                $defaultRead = $this->aclService->load($tag, 'read', true)['list'] ?? '';
                $defaultComment = $this->aclService->load($tag, 'comment', true)['list'] ?? '';

                $initialMetadata = ['acls' => [
                    'write' => $parent ? $user : $defaultWrite,
                    'read' => $defaultRead,
                    'comment' => $parent ? '' : $defaultComment,
                ]];

                if ($this->authenticationService->getLoggedUser()) {
                    $owner = $user;
                } else {
                    $owner = '';
                }

                $type ??= $parent ? PageType::COMMENT : PageType::DEFAULT;
            } else {
                $owner = $oldPage['owner'];

                if ($parent == '') {
                    $parent = $oldPage['parent'];
                }

                $type ??= (string)($oldPage['type'] ?? PageType::DEFAULT);

                if (PageBody::equals($oldPage['body'], $body)) {
                    return 0;
                }
            }

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
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = ?", [$tag]);

                $this->insertRevision($tag, $owner, $user, $body, $type, $initialMetadata, $oldPage, $parent, $forcedDate);
            });

            unset($this->pageCache[$tag]);
            unset($this->rawPageCache[$tag]);
            unset($this->typeCache[$tag]);
            $this->aclService->forget($tag);
            $this->ownersCache[$tag] = $owner;

            // The act, never the content: the revision that was just written *is* the content, and
            // a second copy of it under a different retention would disagree with the first the
            // moment one was pruned (ADR-0025).
            $this->journal()->audit(empty($oldPage) ? 'content.create' : 'content.update', $tag, ['type' => $type]);

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
     * The INSERT half of save()'s revisioning, extracted so the transaction above reads as the two statements it is.
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
        $columns = [
            'tag' => (string)$tag,
            'owner' => (string)$owner,
            $this->dbService->quoteIdentifier('user') => (string)$user,
            'latest' => 'Y',
            'body' => PageBody::encode($body),
            $this->dbService->quoteIdentifier('type') => (string)$type,

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

    /**
     * @param string $tag
     * @param string $time
     *
     * @return string|null the owner's username, null when nobody owns the page
     */
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

    /**
     * @param string $tag
     * @param string $user the new owner's username
     */
    public function setOwner($tag, $user): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->userManager->getOneByName($user)) {
            return;
        }

        $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . "SET owner = ? WHERE tag = ? AND latest = 'Y'", [(string)$user, $tag]);
        $this->ownersCache[$tag] = $user;

        unset($this->pageCache[$tag], $this->rawPageCache[$tag]);
    }

    /**
     * @param string $tag
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata($tag): ?array
    {
        $page = $this->getOne($tag, null, true, true);

        return $page === null ? null : ($page['metadatas'] ?? null);
    }

    /**
     * Metadata is versioned along with content (ADR-0002): changing it creates a new `pages` revision, the same as an edit to body, carrying the current body forward unchanged -- so permission/metadata history stays reconstructable and revertable separately from content (see PageManager::save()'s revisioning, which this mirrors).
     *
     * @param string               $tag
     * @param array<string, mixed> $metadata
     *
     * @return bool whether anything changed
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

        if ($metadata == $previousMetadata) {
            return false;
        }

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
     * The INSERT half of setMetadata()'s revisioning, so the transaction above reads as the two statements it is.
     *
     * @param array<string, mixed> $oldPage
     * @param array<string, mixed> $metadata
     */
    private function insertMetadataRevision(string $tag, array $oldPage, array $metadata): void
    {
        $columns = [
            'tag' => (string)$tag,
            'owner' => (string)$oldPage['owner'],
            $this->dbService->quoteIdentifier('user') => (string)$this->authenticationService->getLoggedUserName(),
            'latest' => 'Y',
            'body' => PageBody::encode($oldPage['body']),

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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): string
    {
        if (YW_CHARSET != 'UTF-8') {
            return (string)json_encode(array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $metadata));
        }

        return (string)json_encode($metadata);
    }

    /**
     * use Guard to checkACL for entries.
     *
     * @param array<array-key, array<string, mixed>> $pages
     * @param string|null                            $userNameForCheckingACL if empty uses the connected user
     *
     * @return array<array-key, array<string, mixed>> $pages
     */
    private function checkEntriesACL(array $pages, ?string $tag = null, ?string $userNameForCheckingACL = null): array
    {
        if ($this->aclService->isAdmin($userNameForCheckingACL)) {
            return $pages;
        }

        foreach ($pages as $page) {
            $this->cacheOwner($page);
        }

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

    /**
     * @param string $sourceTag
     * @param string $destinationTag
     */
    public function duplicate($sourceTag, $destinationTag): bool
    {
        $result = false;
        $this->journal()->audit('content.duplicate', $destinationTag, ['from' => $sourceTag]);

        return $result;
    }

    private function journal(): Journal
    {
        return $this->container->get(Journal::class);
    }
}

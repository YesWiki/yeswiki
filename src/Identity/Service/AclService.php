<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;

class AclService
{
    protected $authenticationService;
    protected $dbService;
    protected $hibernationService;
    protected $userManager;
    protected $params;
    protected ContainerInterface $container;

    protected $cache;
    /**
     * tag => the `acls` sub-object of its `metadata`, as stored.
     *
     * The privilege cache above cannot stand in for this one: `load()` with
     * `$useDefaults = false` empties `$this->cache[$tag]` and refills it from storage, so
     * the two modes evicted each other and the same `SELECT metadata` ran on every call --
     * three times per page render, before anything asked a second question. This caches the
     * read itself, which is the part that hits the database whichever mode asked.
     *
     * @var array<string, array<string, string>>
     */
    private array $metadataAclsCache = [];

    public function __construct(
        AuthenticationService $authenticationService,
        DbService $dbService,
        UserManager $userManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ContainerInterface $container
    ) {
        $this->authenticationService = $authenticationService;
        $this->dbService = $dbService;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        // lazily fetches PageManager (owner lookups): PageManager itself depends on
        // AclService, so constructor injection would be a cycle
        $this->container = $container;

        $this->cache = [];
    }

    /** @var array<string, bool> per-request memo, user name => is admin */
    protected $isAdminCache = [];

    /**
     * Whether $user (default: the logged-in user) belongs to the admins group
     * (historic Wiki::UserIsAdmin()).
     */
    public function isAdmin(?string $user = null): bool
    {
        if ($user === null) {
            $user = $this->authenticationService->getLoggedUserName();
        }
        if (!array_key_exists($user, $this->isAdminCache)) {
            $this->isAdminCache[$user] = $this->userManager->isInGroup(ADMIN_GROUP, $user, false);
        }

        return $this->isAdminCache[$user];
    }

    /**
     * Whether the logged-in user owns the page $tag (default: the current page).
     */
    public function isOwner(string $tag = ''): bool
    {
        if (!$this->authenticationService->getLoggedUser()) {
            return false;
        }

        if (!$tag = trim($tag)) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }

        return $this->container->get(PageManager::class)->getOwner($tag)
            == $this->authenticationService->getLoggedUserName();
    }

    /**
     * @param string $tag
     * @param string $privilege
     * @param bool   $useDefaults
     *
     * @return array [page_tag, privilege, list]
     */
    public function load($tag, $privilege, $useDefaults = true): ?array
    {
        if ($useDefaults && isset($this->cache[$tag][$privilege])) {
            return $this->cache[$tag][$privilege];
        }

        if ($useDefaults) {
            $this->cache[$tag] = [
                'read' => [
                    'page_tag' => $tag,
                    'privilege' => 'read',
                    'list' => $this->params->get('default_read_acl'),
                ],
                'write' => [
                    'page_tag' => $tag,
                    'privilege' => 'write',
                    'list' => $this->params->get('default_write_acl'),
                ],
                'comment' => [
                    'page_tag' => $tag,
                    'privilege' => 'comment',
                    'list' => $this->params->get('default_comment_acl'),
                ],
            ];
        } else {
            $this->cache[$tag] = [];
        }

        foreach ($this->readMetadataAcls($tag) as $priv => $list) {
            $this->cache[$tag][$priv] = [
                'page_tag' => $tag,
                'privilege' => $priv,
                'list' => $list,
            ];
        }

        if (isset($this->cache[$tag][$privilege])) {
            return $this->cache[$tag][$privilege];
        }

        return null;
    }

    /**
     * Saves several privileges in ONE metadata revision (ACLs are versioned with
     * content, ADR-0002 -- privilege-by-privilege stamping would pile up a revision
     * per privilege, e.g. at entry creation).
     *
     * @param array $lists privilege => list
     */
    public function saveMany($tag, array $lists): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $acls = $this->readMetadataAcls($tag);
        foreach ($lists as $privilege => $list) {
            if (strpos((string)$list, ',') !== false) {
                $list = preg_replace('/\s*,\s*/', "\n", $list);
            }
            $list = trim(str_replace("\r", '', (string)$list));
            $acls[$privilege] = $list;
            $this->cache[$tag][$privilege] = [
                'page_tag' => $tag,
                'privilege' => $privilege,
                'list' => $list,
            ];
        }
        $this->writeMetadataAcls($tag, $acls);
    }

    public function save($tag, $privilege, $list, $appendAcl = false)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // If list is comma-separated, convert into to line-break-separated
        if (strpos($list, ',') !== false) {
            $list = preg_replace('/\s*,\s*/', "\n", $list);
        }

        $acl = $this->load($tag, $privilege, false);

        if ($acl && $appendAcl) {
            $list = $acl['list'] . "\n" . $list;
        }

        $list = trim(str_replace("\r", '', $list));

        $acls = $this->readMetadataAcls($tag);
        $acls[$privilege] = $list;
        $this->writeMetadataAcls($tag, $acls);

        // Update the cache
        $this->cache[$tag][$privilege] = [
            'page_tag' => $tag,
            'privilege' => $privilege,
            'list' => $list,
        ];
    }

    /**
     * @param string       $tag        The page's WikiName
     * @param string|array $privileges a privilege or several privileges to delete from database
     */
    public function delete($tag, $privileges = ['read', 'write', 'comment'])
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }

        // unlike save() (which can't set permissions on a page that doesn't exist),
        // deleting ACL entries for an already-deleted/nonexistent page is a harmless no-op --
        // a common pattern is deleting a page and then separately clearing its ACLs
        if ($this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = '{$this->dbService->escape($tag)}' AND latest = 'Y' LIMIT 1")) {
            $acls = $this->readMetadataAcls($tag);
            foreach ($privileges as $privilege) {
                unset($acls[$privilege]);
            }
            $this->writeMetadataAcls($tag, $acls);
        }

        if (isset($this->cache[$tag])) {
            unset($this->cache[$tag]);
        }
    }

    /**
     * Reads the `acls` sub-object of a page's `metadata` column (`['read' => '...',
     * 'write' => '...', 'comment' => '...']`, only the privileges that have an explicit,
     * non-default value set).
     */
    private function readMetadataAcls(string $tag): array
    {
        if (array_key_exists($tag, $this->metadataAclsCache)) {
            return $this->metadataAclsCache[$tag];
        }

        // asked of PageManager rather than read again: the page whose ACLs are being
        // checked has, in all but the first case, just been loaded in full, metadata
        // included. Its unredacted cache answers this without touching the database, and a
        // read taken *during* that load (checkEntriesACL calls straight back into here)
        // still finds it, because the row is remembered before it is redacted.
        $page = $this->container->get(PageManager::class)->getOne($tag, null, true, true);
        $metadata = $page['metadatas'] ?? null;

        return $this->metadataAclsCache[$tag] = is_array($metadata) ? ($metadata['acls'] ?? []) : [];
    }

    /**
     * Forget what this page's stored ACLs were.
     *
     * Called by whoever writes a `pages` row behind this service's back -- PageManager, when
     * it creates a page (whose first revision carries the default ACLs) or reverts one.
     * Without it a lookup made *before* the row existed would answer for the rest of the
     * request from a cache that predates the page.
     */
    public function forget(string $tag): void
    {
        unset($this->metadataAclsCache[$tag], $this->cache[$tag]);
    }

    /**
     * Writes the `acls` sub-object back into `metadata`, versioned the same way any other
     * metadata change is (ADR-0002): marks the current revision non-latest and inserts a new
     * one carrying `body`/`owner`/`parent` forward unchanged, alongside every other
     * `metadata` key untouched.
     *
     * This duplicates the shape of PageManager::setMetadata() rather than calling it:
     * PageManager already depends on AclService (to bootstrap default ACLs when a page is
     * first created), so AclService depending back on PageManager would be circular. Keep
     * this method's revisioning logic in sync with PageManager::setMetadata()'s if that one
     * changes.
     */
    private function writeMetadataAcls(string $tag, array $acls): void
    {
        $current = $this->dbService->loadSingle("SELECT * FROM {$this->dbService->prefixTable('pages')} WHERE tag = '{$this->dbService->escape($tag)}' AND latest = 'Y' LIMIT 1");
        if (!$current) {
            throw new \Exception("Cannot set ACLs on '$tag': no such page");
        }

        $metadata = empty($current['metadata']) ? [] : (json_decode($current['metadata'], true) ?? []);
        $previousAcls = $metadata['acls'] ?? [];
        // drop empty-string/absent privileges instead of storing them explicitly, so
        // readMetadataAcls() keeps returning [] for a page that never diverged from defaults
        $acls = array_filter($acls, fn ($list) => $list !== null && $list !== '');
        if ($acls === $previousAcls) {
            return;
        }
        $metadata['acls'] = $acls;
        // the row below replaces the stored value this service may have cached
        unset($this->metadataAclsCache[$tag]);

        $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = '" . $this->dbService->escape($tag) . "'");

        $userCol = $this->dbService->quoteIdentifier('user');
        $columns = ['tag', 'time', 'owner', $userCol, 'latest', 'body', $this->dbService->quoteIdentifier('type'), 'metadata'];
        $values = [
            "'" . $this->dbService->escape($tag) . "'",
            $this->dbService->now(),
            "'" . $this->dbService->escape($current['owner']) . "'",
            "'" . $this->dbService->escape($this->authenticationService->getLoggedUserName()) . "'",
            "'Y'",
            // raw SQL row, so the body is still the stored JSON text -- carry it forward
            // verbatim rather than decoding and re-encoding it
            "'" . $this->dbService->escape((string)$current['body']) . "'",
            // carried forward: changing a page's ACLs is not retyping it, and a revision
            // defaulting to 'page' would turn every account, file and entry into a page the
            // first time its permissions were touched
            "'" . $this->dbService->escape((string)($current['type'] ?? PageType::DEFAULT)) . "'",
            "'" . $this->dbService->escape(json_encode($metadata)) . "'",
        ];
        if (!empty($current['parent'])) {
            $columns[] = 'parent';
            $values[] = "'" . $this->dbService->escape($current['parent']) . "'";
        }
        $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('pages') . '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');

        // a new revision of the row PageManager may be holding a copy of
        $this->container->get(PageManager::class)->forget($tag);
    }

    /**
     * Check if user has a privilege on page.
     * The page's owner has always access (always return true).
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag       The page WikiName. Default to current page
     * @param string $user      The username. Default to current user.
     *
     * @return bool true if access granted, false if not
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        // set default to current page
        if ($tag == null || !$tag = trim($tag)) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }

        // set default to current user
        if (!$user) {
            $loggedUser = $this->authenticationService->getLoggedUser();
            $user = $loggedUser['name'] ?? '';
        }

        // load acl
        $acl = $this->load($tag, $privilege);

        // empty acls is considered as no access
        if ($acl === null) {
            return false;
        } elseif (isset($acl['list']) && (
            $acl['list'] === 'comments-closed'
                || (
                    $acl['list'] === '*' && $privilege === 'comment' && empty($user)
                )
        )) {
            return false;
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->isOwner($tag)) {
            return true;
        }

        // now check the acls
        $access = $this->check($acl['list'], $user);

        return $access;
    }

    /**
     * Checks if some $user satisfies the given $acl.
     *
     * @param string $acl
     *                             The acl to check, in the same format than for pages ACL's
     * @param string $user
     *                             The name of the user that must satisfy the ACL. By default
     *                             the current remote user.
     * @param bool   $adminCheck
     *                             Check if user is in admins groups
     *                             Default true
     * @param string $tag
     *                             The name of the page or form to be tested when $acl contains '%'.
     *                             By Default ''
     * @param string $mode
     *                             Mode for cases when $acl contains '%'
     *                             Default '', standard case. $mode = 'creation', the test returns true
     *                             even if the user is connected
     * @param array  $formerGroups
     *                             to avoid loops we keep track of former calls
     *
     * @return bool True if the $user satisfies the $acl, false otherwise
     */
    public function check($acl, $user = null, $adminCheck = true, $tag = '', $mode = '', $formerGroups = [])
    {
        if (!$user) {
            $user = $this->authenticationService->getLoggedUser();
            $username = !empty($user['name']) ? $user['name'] : null;
        } else {
            $username = $user;
        }

        if ($adminCheck && !empty($username) && $this->isAdmin($username)) {
            return true;
        }

        $acl = is_string($acl) ? trim($acl) : '';
        $result = false; // result by default , this function is like a big "OR LOGICAL"

        $acl = str_replace(["\r\n", "\r"], "\n", $acl);
        foreach (explode("\n", $acl) as $line) {
            $line = trim($line);

            // check for inversion character "!"
            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $std_response = false;
                $line = $matches[1];
            } else {
                $std_response = true;
            }

            // if there's still anything left... lines with just a "!" don't count!
            if ($line) {
                switch ($line[0]) {
                    case '#': // comments
                        break;
                    case '*': // everyone
                        $result = $std_response;
                        break;
                    case '+': // registered users
                        $result = (!empty($username) && $this->userManager->getOneByName($username)) ? $std_response : !$std_response;
                        break;
                    case '%': // owner
                        if ($mode == 'creation') {
                            // in creation mode, even if there is a tag
                            // the current user can access to field
                            $result = $std_response;
                        } elseif ($tag == '') {
                            // to manage retrocompatibility without usage of CheckACL without $tag
                            // and no management of '%'
                            $result = false;
                        } else {
                            $result = $this->isOwner($tag) ? $std_response : !$std_response;
                        }
                        break;
                    case '@': // groups
                        $gname = substr($line, 1);
                        // paranoiac: avoid line = '@'
                        if ($gname) {
                            if (in_array($gname, $formerGroups)) {
                                $this->container->get(\YesWiki\Kernel\Service\FlashMessageService::class)->setMessage('Error group ' . $gname . ' inside same groups, inception was a bad movie');
                                $result = false;
                            } else {
                                if (!empty($username)
                                && $this->userManager->isInGroup(
                                    $gname,
                                    $username,
                                    false/* we have allready checked if user was an admin */,
                                    array_merge($formerGroups, [$gname]) // does not change $formerGroups param
                                )
                                ) {
                                    $result = $std_response;
                                } else {
                                    $result = !$std_response;
                                }
                            }
                        } else {
                            $result = false; // line '@'
                        }
                        break;
                    default: // simple user entry
                        if (!empty($username) && $line == $username) {
                            $result = $std_response;
                        } else {
                            $result = !$std_response;
                        }
                }
                if ($result) {
                    return true;
                } // else continue like a big logical OR
            }
        }

        // tough luck.
        return false;
    }

    /** create request for ACL.
     * @return string $request request to append to request
     */
    public function updateRequestWithACL(): string
    {
        // ACLs now live in metadata.acls, a column on the very `pages` row being filtered --
        // no join/subquery against a separate table needed, unlike the old acls-table version
        // this replaces. Every caller of this method selects FROM the (unaliased or
        // consistently-aliased) pages table, so bare `metadata`/`owner` column references
        // resolve the same way the pre-existing bare `tag`/`owner` references already did.
        return $this->buildReadAclPredicate(
            $this->dbService->jsonExtract('metadata', '$.acls.read'),
            'owner',
            true
        );
    }

    /**
     * The same "may the current user read this row?" predicate, over a column that already
     * holds the *effective* read ACL as plain text rather than a JSON path into `metadata`.
     *
     * Ticket 18's search index denormalises the read ACL onto its own rows (ADR-0015), so
     * it filters with this. Sharing the predicate is the point: two hand-written versions of
     * ACL evaluation is how a wiki ends up disclosing a private page down one path and not
     * the other.
     *
     * `$nullMeansDefault` is false here, unlike for `pages`: the index resolves the default
     * at write time, so a row's stored ACL is never absent.
     */
    public function aclColumnPredicate(string $aclColumn, string $ownerColumn): string
    {
        return $this->buildReadAclPredicate($aclColumn, $ownerColumn, false);
    }

    /**
     * @param string $readAclExpr      SQL expression yielding the row's read ACL
     * @param string $ownerColumn      SQL expression yielding the row's owner
     * @param bool   $nullMeansDefault whether an absent ACL falls back to `default_read_acl`
     */
    private function buildReadAclPredicate(string $readAclExpr, string $ownerColumn, bool $nullMeansDefault): string
    {
        // needed ACL
        $neededACL = ['*'];
        // connected ?
        $user = $this->authenticationService->getLoggedUser();
        $userName = '';
        if (!empty($user)) {
            $userName = $user['name'];
            $neededACL[] = '+';
            $neededACL[] = $userName;
            $groups = $this->container->get(GroupOperationsService::class)->getAll();
            foreach ($groups as $group) {
                if (!empty($userName) && $this->userManager->isInGroup($group, $userName, true)) {
                    $neededACL[] = '@' . $group;
                }
            }
        }

        // Built as clearly-parenthesized, self-contained sub-expressions (rather than the old
        // start/end string-accumulator, which relied on an implicit-subquery paren that this
        // rewrite has no equivalent for) so the grouping is verifiable by inspection instead
        // of by manually tracing open/close counts across the method.

        // "the page's read ACL, evaluated against $neededACL": at least one needed entry is
        // explicitly granted, and none of them is explicitly denied (the '!' prefix)
        $matchesNeededAcl = '(';
        $addOr = false;
        foreach ($neededACL as $acl) {
            if ($addOr) {
                $matchesNeededAcl .= ' OR ';
            } else {
                $addOr = true;
            }
            // single-quoted, not double-quoted: DbService::escape() (PDO::quote()) only
            // guarantees safety inside a single-quoted SQL literal -- e.g. SQLite's PDO
            // driver never touches '"' at all, so a double-quoted literal here would let
            // a raw '"' in $acl break out of the string (see
            // AclServiceUpdateRequestWithAclTest for the regression this guards against)
            $matchesNeededAcl .= " {$readAclExpr} LIKE '%" . $this->dbService->escape($acl) . "%'";
        }
        $matchesNeededAcl .= ')';
        foreach ($neededACL as $acl) {
            $matchesNeededAcl .= " AND {$readAclExpr} NOT LIKE '%!" . $this->dbService->escape($acl) . "%'";
        }

        // has an explicit read ACL that matches, OR (if logged in) is the page's owner and
        // the ACL contains the '%' (owner) marker
        $hasExplicitMatchingAcl = "(({$readAclExpr} IS NOT NULL) AND {$matchesNeededAcl})";
        if (!empty($user)) {
            $ownerMatch = "(({$readAclExpr} LIKE '%\\%%' AND {$readAclExpr} NOT LIKE '%!\\%%')" .
                " AND {$ownerColumn} = '" . $this->dbService->escape($userName) . "')";
            $hasExplicitMatchingAcl = "({$hasExplicitMatchingAcl} OR {$ownerMatch})";
        }

        if ($nullMeansDefault && $this->check($this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*')) {
            // current user can display pages without an explicit read acl too
            $request = "(({$readAclExpr} IS NULL) OR {$hasExplicitMatchingAcl})";
        } else {
            $request = $hasExplicitMatchingAcl;
        }

        // return request to append
        return $request;
    }
}

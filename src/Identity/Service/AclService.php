<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Wiki;

class AclService
{
    protected $authenticationService;
    protected $wiki;
    protected $dbService;
    protected $hibernationService;
    protected $userManager;
    protected $params;
    protected ContainerInterface $container;

    protected $cache;

    public function __construct(
        Wiki $wiki,
        AuthenticationService $authenticationService,
        DbService $dbService,
        UserManager $userManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ContainerInterface $container
    ) {
        $this->authenticationService = $authenticationService;
        $this->wiki = $wiki;
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
            $tag = $this->wiki->GetPageTag();
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
        $page = $this->dbService->loadSingle("SELECT metadata FROM {$this->dbService->prefixTable('pages')} WHERE tag = '{$this->dbService->escape($tag)}' AND latest = 'Y' LIMIT 1");
        if (empty($page['metadata'])) {
            return [];
        }

        $metadata = json_decode($page['metadata'], true);

        return $metadata['acls'] ?? [];
    }

    /**
     * Writes the `acls` sub-object back into `metadata`, versioned the same way any other
     * metadata change is (ADR-0002): marks the current revision non-latest and inserts a new
     * one carrying `body`/`owner`/`comment_on` forward unchanged, alongside every other
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

        $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = '" . $this->dbService->escape($tag) . "'");

        $userCol = $this->dbService->quoteIdentifier('user');
        $columns = ['tag', 'time', 'owner', $userCol, 'latest', 'body', 'body_r', 'metadata'];
        $values = [
            "'" . $this->dbService->escape($tag) . "'",
            $this->dbService->now(),
            "'" . $this->dbService->escape($current['owner']) . "'",
            "'" . $this->dbService->escape($this->authenticationService->getLoggedUserName()) . "'",
            "'Y'",
            "'" . $this->dbService->escape($current['body']) . "'",
            "''",
            "'" . $this->dbService->escape(json_encode($metadata)) . "'",
        ];
        if (!empty($current['comment_on'])) {
            $columns[] = 'comment_on';
            $values[] = "'" . $this->dbService->escape($current['comment_on']) . "'";
        }
        $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('pages') . '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');
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
            $tag = $this->wiki->GetPageTag();
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
                                $this->wiki->setMessage('Error group ' . $gname . ' inside same groups, inception was a bad movie');
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
        // needed ACL
        $neededACL = ['*'];
        // connected ?
        $user = $this->authenticationService->getLoggedUser();
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

        // ACLs now live in metadata.acls, a column on the very `pages` row being filtered --
        // no join/subquery against a separate table needed, unlike the old acls-table version
        // this replaces. Every caller of this method selects FROM the (unaliased or
        // consistently-aliased) pages table, so bare `metadata`/`owner` column references
        // resolve the same way the pre-existing bare `tag`/`owner` references already did.
        //
        // Built as clearly-parenthesized, self-contained sub-expressions (rather than the old
        // start/end string-accumulator, which relied on an implicit-subquery paren that this
        // rewrite has no equivalent for) so the grouping is verifiable by inspection instead
        // of by manually tracing open/close counts across the method.
        $readAclExpr = $this->dbService->jsonExtract('metadata', '$.acls.read');

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
                " AND owner = '" . $this->dbService->escape($userName) . "')";
            $hasExplicitMatchingAcl = "({$hasExplicitMatchingAcl} OR {$ownerMatch})";
        }

        if ($this->check($this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*')) {
            // current user can display pages without an explicit read acl too
            $request = "(({$readAclExpr} IS NULL) OR {$hasExplicitMatchingAcl})";
        } else {
            $request = $hasExplicitMatchingAcl;
        }

        // return request to append
        return $request;
    }
}

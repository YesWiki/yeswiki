<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;

class AclService
{
    protected AuthenticationService $authenticationService;
    protected DbService $dbService;
    protected HibernationService $hibernationService;
    protected UserManager $userManager;
    protected ParameterBagInterface $params;
    protected ContainerInterface $container;

    /**
     * tag => privilege => ['page_tag' => string, 'privilege' => string, 'list' => mixed].
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected $cache;
    /**
     * tag => the `acls` sub-object of its `metadata`, as stored.
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

        $this->container = $container;

        $this->cache = [];
    }

    /**
     * @var array<string, bool> per-request memo, user name => is admin
     */
    protected $isAdminCache = [];

    /**
     * Whether $user (default: the logged-in user) belongs to the admins group (historic Wiki::UserIsAdmin()).
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

    /** Whether the logged-in user owns the page $tag (default: the current page). */
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
     * @return array<string, mixed>|null ['page_tag' => ..., 'privilege' => ..., 'list' => ...], null when this page has no such privilege set
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
     * Saves several privileges in ONE metadata revision (ACLs are versioned with content, ADR-0002 -- privilege-by-privilege stamping would pile up a revision per privilege, e.g.
     *
     * @param string                $tag
     * @param array<string, string> $lists privilege => list
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

    /**
     * @param string $tag
     * @param string $privilege
     * @param string $list
     * @param bool   $appendAcl
     *
     * @return void
     */
    public function save($tag, $privilege, $list, $appendAcl = false)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (strpos($list, ',') !== false) {
            $list = preg_replace('/\s*,\s*/', "\n", $list) ?? $list;
        }

        $acl = $this->load($tag, $privilege, false);

        if ($acl && $appendAcl) {
            $list = $acl['list'] . "\n" . $list;
        }

        $list = trim(str_replace("\r", '', $list));

        $acls = $this->readMetadataAcls($tag);
        $acls[$privilege] = $list;
        $this->writeMetadataAcls($tag, $acls);

        $this->cache[$tag][$privilege] = [
            'page_tag' => $tag,
            'privilege' => $privilege,
            'list' => $list,
        ];
    }

    /**
     * @param string                    $tag        The page's WikiName
     * @param string|array<int, string> $privileges a privilege or several privileges to delete from database
     *
     * @return void
     */
    public function delete($tag, $privileges = ['read', 'write', 'comment'])
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }

        if ($this->dbService->loadSingle("SELECT 1 FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? AND latest = 'Y' LIMIT 1", [$tag])) {
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
     * Reads the `acls` sub-object of a page's `metadata` column (`['read' => '...', 'write' => '...', 'comment' => '...']`, only the privileges that have an explicit, non-default value set).
     *
     * @return array<string, string> privilege => list
     */
    private function readMetadataAcls(string $tag): array
    {
        if (array_key_exists($tag, $this->metadataAclsCache)) {
            return $this->metadataAclsCache[$tag];
        }

        $page = $this->container->get(PageManager::class)->getOne($tag, null, true, true);
        $metadata = $page['metadatas'] ?? null;

        return $this->metadataAclsCache[$tag] = is_array($metadata) ? ($metadata['acls'] ?? []) : [];
    }

    /** Forget what this page's stored ACLs were. */
    public function forget(string $tag): void
    {
        unset($this->metadataAclsCache[$tag], $this->cache[$tag]);
    }

    /**
     * Writes the `acls` sub-object back into `metadata`, versioned the same way any other metadata change is (ADR-0002): marks the current revision non-latest and inserts a new one carrying `body`/`owner`/`parent` forward unchanged, alongside every other `metadata` key untouched.
     *
     * @param array<string, string> $acls privilege => list
     */
    private function writeMetadataAcls(string $tag, array $acls): void
    {
        $current = $this->dbService->loadSingle("SELECT * FROM {$this->dbService->prefixTable('pages')} WHERE tag = ? AND latest = 'Y' LIMIT 1", [$tag]);
        if (!$current) {
            throw new \Exception("Cannot set ACLs on '$tag': no such page");
        }

        $metadata = empty($current['metadata']) ? [] : (json_decode($current['metadata'], true) ?? []);
        $previousAcls = $metadata['acls'] ?? [];

        $acls = array_filter($acls, fn (string $list) => $list !== '');
        if ($acls === $previousAcls) {
            return;
        }
        $metadata['acls'] = $acls;

        unset($this->metadataAclsCache[$tag]);

        $this->dbService->transactional(function () use ($tag, $current, $metadata): void {
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET latest = 'N' WHERE tag = ?", [$tag]);
            $this->insertAclRevision($tag, $current, $metadata);
        });

        $this->container->get(PageManager::class)->forget($tag);
    }

    /**
     * The INSERT half of writeMetadataAcls()' revisioning.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $metadata
     */
    private function insertAclRevision(string $tag, array $current, array $metadata): void
    {
        $columns = [
            'tag' => (string)$tag,
            'owner' => (string)$current['owner'],
            $this->dbService->quoteIdentifier('user') => (string)$this->authenticationService->getLoggedUserName(),
            'latest' => 'Y',

            'body' => (string)$current['body'],

            $this->dbService->quoteIdentifier('type') => (string)($current['type'] ?? PageType::DEFAULT),
            'metadata' => json_encode($metadata),
        ];
        if (!empty($current['parent'])) {
            $columns['parent'] = (string)$current['parent'];
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
     * Check if user has a privilege on page.
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag       The page WikiName. Default to current page
     * @param string $user      The username. Default to current user.
     *
     * @return bool true if access granted, false if not
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        if ($tag == null || !$tag = trim($tag)) {
            $tag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        }

        if (!$user) {
            $loggedUser = $this->authenticationService->getLoggedUser();
            $user = $loggedUser['name'] ?? '';
        }

        $acl = $this->load($tag, $privilege);

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

        if ($this->isOwner($tag)) {
            return true;
        }

        $access = $this->check($acl['list'], $user);

        return $access;
    }

    /**
     * Checks if some $user satisfies the given $acl.
     *
     * @param mixed        $acl
     *                                   The acl to check, in the same format than for pages ACL's.
     *                                   Typed loosely on purpose: the `hasAcl` Twig helper and
     *                                   getPageACL() both hand over whatever the caller holds, and
     *                                   anything that is not a string is treated as an empty acl,
     *                                   which denies access rather than granting it.
     * @param string       $user
     *                                   The name of the user that must satisfy the ACL. By default
     *                                   the current remote user.
     * @param bool         $adminCheck
     *                                   Check if user is in admins groups
     *                                   Default true
     * @param string       $tag
     *                                   The name of the page or form to be tested when $acl contains '%'.
     *                                   By Default ''
     * @param string       $mode
     *                                   Mode for cases when $acl contains '%'
     *                                   Default '', standard case. $mode = 'creation', the test returns true
     *                                   even if the user is connected
     * @param list<string> $formerGroups
     *                                   to avoid loops we keep track of former calls
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
        $result = false;

        $acl = str_replace(["\r\n", "\r"], "\n", $acl);
        foreach (explode("\n", $acl) as $line) {
            $line = trim($line);

            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $std_response = false;
                $line = $matches[1];
            } else {
                $std_response = true;
            }

            if ($line) {
                switch ($line[0]) {
                    case '#':
                        break;
                    case '*':
                        $result = $std_response;
                        break;
                    case '+':
                        $result = (!empty($username) && $this->userManager->getOneByName($username)) ? $std_response : !$std_response;
                        break;
                    case '%':
                        if ($mode == 'creation') {
                            $result = $std_response;
                        } elseif ($tag == '') {
                            $result = false;
                        } else {
                            $result = $this->isOwner($tag) ? $std_response : !$std_response;
                        }
                        break;
                    case '@':
                        $gname = substr($line, 1);

                        if ($gname) {
                            if (in_array($gname, $formerGroups)) {
                                $this->container->get(\YesWiki\Kernel\Service\FlashMessageService::class)->setMessage('Error group ' . $gname . ' inside same groups, inception was a bad movie');
                                $result = false;
                            } else {
                                if (!empty($username)
                                && $this->userManager->isInGroup(
                                    $gname,
                                    $username,
                                    false,
                                    array_merge($formerGroups, [$gname])
                                )
                                ) {
                                    $result = $std_response;
                                } else {
                                    $result = !$std_response;
                                }
                            }
                        } else {
                            $result = false;
                        }
                        break;
                    default:
                        if (!empty($username) && $line == $username) {
                            $result = $std_response;
                        } else {
                            $result = !$std_response;
                        }
                }
                if ($result) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * create request for ACL.
     *
     * @return SqlFragment the predicate and the values it binds (ticket 31)
     */
    public function updateRequestWithACL(): SqlFragment
    {
        return $this->buildReadAclPredicate(
            $this->dbService->jsonExtract('metadata', '$.acls.read'),
            'owner',
            true
        );
    }

    /**
     * The same "may the current user read this row?" predicate, over a column that already holds the *effective* read ACL as plain text rather than a JSON path into `metadata`.
     */
    public function aclColumnPredicate(string $aclColumn, string $ownerColumn): SqlFragment
    {
        return $this->buildReadAclPredicate($aclColumn, $ownerColumn, false);
    }

    /**
     * @param string $readAclExpr      SQL expression yielding the row's read ACL
     * @param string $ownerColumn      SQL expression yielding the row's owner
     * @param bool   $nullMeansDefault whether an absent ACL falls back to `default_read_acl`
     *                                 Returns a SqlFragment rather than a string (ticket 31): this predicate is pasted into
     *                                 queries built elsewhere -- PageManager::getAll(), SearchManager, SearchIndexQuery::where()
     *                                 -- so the values it needs have no way to reach the call that executes the statement unless
     *                                 they travel with the SQL. That is the whole reason these were the last escape() calls in
     *                                 live code.
     */
    private function buildReadAclPredicate(string $readAclExpr, string $ownerColumn, bool $nullMeansDefault): SqlFragment
    {
        $neededACL = ['*'];

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

        $suffix = SqlParameters::LIKE_CLAUSE_SUFFIX;
        $contains = static fn (string $needle): array => [SqlParameters::likeContains($needle)];

        $granted = array_map(
            fn (string $acl): SqlFragment => SqlFragment::of("{$readAclExpr} LIKE ?{$suffix}", $contains($acl)),
            $neededACL
        );
        $denied = array_map(
            fn (string $acl): SqlFragment => SqlFragment::of("{$readAclExpr} NOT LIKE ?{$suffix}", $contains('!' . $acl)),
            $neededACL
        );
        $matchesNeededAcl = SqlFragment::all(
            ' AND ',
            SqlFragment::all(' OR ', ...$granted)->wrappedIn('(', ')'),
            ...$denied
        );

        $hasExplicitMatchingAcl = SqlFragment::all(
            ' AND ',
            SqlFragment::of("({$readAclExpr} IS NOT NULL)"),
            $matchesNeededAcl
        )->wrappedIn('(', ')');

        if (!empty($user)) {
            $ownerMatch = SqlFragment::of(
                "(({$readAclExpr} LIKE ?{$suffix} AND {$readAclExpr} NOT LIKE ?{$suffix})"
                . " AND {$ownerColumn} = ?)",
                [SqlParameters::likeContains('%'), SqlParameters::likeContains('!%'), $userName]
            );
            $hasExplicitMatchingAcl = SqlFragment::all(' OR ', $hasExplicitMatchingAcl, $ownerMatch)
                ->wrappedIn('(', ')');
        }

        $defaultReadAcl = $this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*';
        if ($nullMeansDefault && $this->check(is_string($defaultReadAcl) ? $defaultReadAcl : '*')) {
            return SqlFragment::all(
                ' OR ',
                SqlFragment::of("({$readAclExpr} IS NULL)"),
                $hasExplicitMatchingAcl
            )->wrappedIn('(', ')');
        }

        return $hasExplicitMatchingAcl;
    }
}

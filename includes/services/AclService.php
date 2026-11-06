<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class AclService
{
    protected $authController;
    protected $wiki;
    protected $dbService;
    protected $securityController;
    protected $userManager;
    protected $params;

    protected $cache;

    public function __construct(
        Wiki $wiki,
        AuthController $authController,
        DbService $dbService,
        UserManager $userManager,
        ParameterBagInterface $params,
        SecurityController $securityController
    ) {
        $this->authController = $authController;
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->securityController = $securityController;

        $this->cache = [];
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

        $res = $this->dbService->loadAll('SELECT * FROM' . $this->dbService->prefixTable('acls') . 'WHERE page_tag = "' . $this->dbService->escape($tag) . '"');

        foreach ($res as $acl) {
            $this->cache[$tag][$acl['privilege']] = $acl;
        }

        if (isset($this->cache[$tag][$privilege])) {
            return $this->cache[$tag][$privilege];
        }

        return null;
    }

    /**
     * @param string $tag       the page's tag
     * @param string $privilege the privilege
     * @param string $list      the multiline string describing the acl
     */
    public function save($tag, $privilege, $list, $appendAcl = false)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (strpos($list, ',') !== false) {
            $list = preg_replace('/\s*,\s*/', "\n", $list);
        }

        $acl = $this->load($tag, $privilege, false);

        if ($acl && $appendAcl) {
            $list = $acl['list'] . "\n" . $list;
        }

        if ($acl) {
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('acls') . 'SET list = "' . $this->dbService->escape(trim(str_replace("\r", '', $list))) . '" WHERE page_tag = "' . $this->dbService->escape($tag) . '" AND privilege = "' . $this->dbService->escape($privilege) . '"');
        } else {
            $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('acls') . "SET list = '" . $this->dbService->escape(trim(str_replace("\r", '', $list))) . "', page_tag = '" . $this->dbService->escape($tag) . "', privilege = '" . $this->dbService->escape($privilege) . "'");
        }

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
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }

        for ($i = 0; $i < count($privileges); $i++) {
            $privileges[$i] = '"' . $this->dbService->escape($privileges[$i]) . '"';
        }

        $privileges = implode(',', $privileges);

        $this->dbService->query('DELETE FROM' . $this->dbService->prefixTable('acls') . ' WHERE page_tag = "' . $this->dbService->escape($tag) . '" AND privilege IN (' . $privileges . ')');

        if (isset($this->cache[$tag])) {
            unset($this->cache[$tag]);
        }
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

        if ($tag == null || !$tag = trim($tag)) {
            $tag = $this->wiki->GetPageTag();
        }

        if (!$user) {
            $loggedUser = $this->authController->getLoggedUser();
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

        if ($this->wiki->UserIsOwner($tag)) {
            return true;
        }

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
            $user = $this->authController->getLoggedUser();
            $username = !empty($user['name']) ? $user['name'] : null;
        } else {
            $username = $user;
        }

        if ($adminCheck && !empty($username) && $this->wiki->UserIsAdmin($username)) {
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
                            $result = ($this->wiki->UserIsOwner($tag)) ? $std_response : !$std_response;
                        }
                        break;
                    case '@': 
                        $gname = substr($line, 1);

                        if ($gname) {
                            if (in_array($gname, $formerGroups)) {
                                $this->wiki->setMessage('Error group ' . $gname . ' inside same groups, inception was a bad movie');
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

    /** create request for ACL.
     * @return string $request request to append to request
     */
    /**
     * SQL fragment restricting a column holding page tags to what the current user can read.
     *
     * Self-contained, so it works on an alias, a join or another table's column.
     * Append it before any ORDER BY: MySQL parses `ORDER BY tag AND (...)` as a sort
     * expression rather than an error, and the filter would silently do nothing.
     * Set $keepUnknownTags on a column that also holds tags with no page, to drop
     * the unreadable ones without dropping those.
     */
    public function readableFilterSql(string $column = 'tag', bool $keepUnknownTags = false): string
    {
        $subquery = $this->readableTagsSubquery();
        if (empty($subquery)) {
            return '';
        }
        if (!$keepUnknownTags) {
            return " AND $column IN $subquery";
        }
        $pages = $this->dbService->prefixTable('pages');

        return " AND ($column NOT IN (SELECT tag FROM $pages WHERE latest = 'Y') OR $column IN $subquery)";
    }

    /**
     * Subquery yielding the tags the current user can read, empty for an admin.
     *
     * Compose it directly when a plain `IN` would change more than the ACL, for instance
     * on an outer join where a missing page must stay listed.
     */
    public function readableTagsSubquery(): string
    {
        if ($this->wiki->UserIsAdmin()) {
            return '';
        }
        $aclRequest = $this->updateRequestWithACL();
        if (empty($aclRequest)) {
            return '';
        }
        $pages = $this->dbService->prefixTable('pages');

        return "(SELECT tag FROM $pages WHERE latest = 'Y' AND ($aclRequest))";
    }

    public function updateRequestWithACL(): string
    {

        $neededACL = ['*'];

        $user = $this->authController->getLoggedUser();
        if (!empty($user)) {
            $userName = $user['name'];
            $neededACL[] = '+';
            $neededACL[] = $userName;
            foreach ($this->groupsOf($userName) as $group) {
                $neededACL[] = '@' . $group;
            }
        }

        $newRequestStart = '';
        $newRequestEnd = '';
        if ($this->check($this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*')) {

            $newRequestStart .= '(';
            $newRequestEnd = ')' . $newRequestEnd;

            $newRequestStart .= 'tag NOT IN (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="read")';

            $newRequestStart .= ' OR (';
            $newRequestEnd = ')' . $newRequestEnd;
        }

        $newRequestStart .= 'tag in (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="read"';
        $newRequestEnd = ')' . $newRequestEnd;

        if (count($neededACL) > 0) {
            $newRequestStart .= ' AND (';
            if (!empty($user)) {
                $newRequestStart .= '(';
                $newRequestEnd = ')' . $newRequestEnd;
            }

            $addOr = false;
            foreach ($neededACL as $acl) {
                if ($addOr) {
                    $newRequestStart .= ' OR ';
                } else {
                    $addOr = true;
                }
                $newRequestStart .= $this->aclLineMatches($acl, '');
            }
            $newRequestStart .= ')';

            foreach ($neededACL as $acl) {
                $newRequestStart .= ' AND ';
                $newRequestStart .= $this->aclLineMatches($acl, '!', 'NOT ');
            }

            if (!empty($user)) {
                $newRequestStart .= ') OR (';

                $newRequestStart .= '(list LIKE "%\\%%" AND list NOT LIKE "%!\\%%")';
                $newRequestStart .= ' AND owner = _utf8\'' . $this->dbService->escape($userName) . '\'';
            }
        }

        $request = $newRequestStart . $newRequestEnd;

        return $request;
    }

    /** Returns the SQL testing that an ACL list holds this value as a whole line; the visibility signs match anywhere. */
    private function aclLineMatches(string $value, string $negation, string $not = ''): string
    {
        $escaped = $this->dbService->escape($this->escapeLikeValue($negation . $value));
        if (in_array($value, ['*', '+'], true)) {
            return " list {$not}LIKE \"%$escaped%\"";
        }

        return " CONCAT(CHAR(10), REPLACE(REPLACE(list, CHAR(13), ''), ' ', ''), CHAR(10)) {$not}LIKE CONCAT('%', CHAR(10), \"$escaped\", CHAR(10), '%')";
    }

    /**
     * Returns the groups the user belongs to, nested ones included, from a single query.
     * A group using a sign other than plain names and nested groups goes through the full check.
     */
    private function groupsOf(string $userName): array
    {
        $triples = $this->dbService->prefixTable('triples');
        $prefix = $this->dbService->escape($this->escapeLikeValue(GROUP_PREFIX));
        $members = [];
        foreach ($this->dbService->loadAll("SELECT resource, value FROM $triples WHERE resource LIKE '$prefix%' AND property = '" . WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS . "'") as $row) {
            $members[substr($row['resource'], strlen(GROUP_PREFIX))] = preg_split('/\s*[\r\n]+\s*/', trim($row['value']), -1, PREG_SPLIT_NO_EMPTY);
        }
        $found = [];
        foreach ($members as $group => $lines) {
            $signed = preg_grep('/^[!*+%#]/', $lines);
            if (in_array($userName, $lines, true) || ($signed && $this->userManager->isInGroup((string) $group, $userName, true))) {
                $found[$group] = true;
            }
        }
        do {
            $added = false;
            foreach ($members as $group => $lines) {
                if (!isset($found[$group]) && array_filter($lines, fn ($line) => $line[0] === '@' && isset($found[substr($line, 1)]))) {
                    $found[$group] = true;
                    $added = true;
                }
            }
        } while ($added);

        return array_map('strval', array_keys($found));
    }

    /** Escapes the LIKE metacharacters of an identity so a username made of wildcards cannot widen the ACL match. */
    private function escapeLikeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

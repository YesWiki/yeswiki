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
        // If list is comma-separated, convert into to line-break-separated
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
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }

        // Add '"' at begin and end of each escaped privileges elements.
        for ($i = 0; $i < count($privileges); $i++) {
            $privileges[$i] = '"' . $this->dbService->escape($privileges[$i]) . '"';
        }

        // Construct a CSV string with privileges elements
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
        // set default to current page
        if ($tag == null || !$tag = trim($tag)) {
            $tag = $this->wiki->GetPageTag();
        }

        // set default to current user
        if (!$user) {
            $loggedUser = $this->authController->getLoggedUser();
            $user = $loggedUser['name'] ?? '';
        }

        // load acl
        $acl = $this->load($tag, $privilege);

        // empty acls is considered as no access
        if ($acl === null) {
            return false;
        } elseif (isset($acl['list']) && (
            $acl['list'] === 'comments-closed' ||
                (
                    $acl['list'] === '*' && $privilege === 'comment' && empty($user)
                )
        )) {
            return false;
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->wiki->UserIsOwner($tag)) {
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
            $user = $this->authController->getLoggedUser();
            $username = !empty($user['name']) ? $user['name'] : null;
        } else {
            $username = $user;
        }

        if ($adminCheck && !empty($username) && $this->wiki->UserIsAdmin($username)) {
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
                            $result = ($this->wiki->UserIsOwner($tag)) ? $std_response : !$std_response;
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
        $user = $this->authController->getLoggedUser();
        if (!empty($user)) {
            $userName = $user['name'];
            $neededACL[] = '+';
            $neededACL[] = $userName;
            $groups = $this->wiki->GetGroupsList();
            foreach ($groups as $group) {
                if (!empty($userName) && $this->userManager->isInGroup($group, $userName, true)) {
                    $neededACL[] = '@' . $group;
                }
            }
        }

        // check default readacl
        $newRequestStart = ' AND ';
        $newRequestEnd = '';
        if ($this->check($this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*')) {
            // current user can display pages without read acl
            $newRequestStart .= '(';
            $newRequestEnd = ')' . $newRequestEnd;

            $newRequestStart .= 'tag NOT IN (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="read")';

            $newRequestStart .= ' OR (';
            $newRequestEnd = ')' . $newRequestEnd;
        }
        // construct new request when acl
        $newRequestStart .= 'tag in (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="read"';
        $newRequestEnd = ')' . $newRequestEnd;

        // needed ACL
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
                $newRequestStart .= ' list LIKE "%' . $acl . '%"';
            }
            $newRequestStart .= ')';
            // not authorized ACL
            foreach ($neededACL as $acl) {
                $newRequestStart .= ' AND ';
                $newRequestStart .= ' list NOT LIKE "%!' . $acl . '%"';
            }

            // add detection of '%'
            if (!empty($user)) {
                $newRequestStart .= ') OR (';

                $newRequestStart .= '(list LIKE "%\\%%" AND list NOT LIKE "%!\\%%")';
                $newRequestStart .= ' AND owner = _utf8\'' . $this->dbService->escape($userName) . '\'';
            }
        }

        $request = $newRequestStart . $newRequestEnd;

        // return request to append
        return $request;
    }
}

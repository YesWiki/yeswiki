<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class AclService
{
    protected $wiki;
    protected $dbService;
    protected $userManager;
    protected $params;

    protected $cache;
    private $checkOwnerReadAcl;

    public function __construct(Wiki $wiki, DbService $dbService, UserManager $userManager, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->userManager = $userManager;
        $this->params = $params;
        
        $this->cache = [];
        $this->checkOwnerReadAcl = !($this->params->has('baz_check_owner_acl_only_for_field_can_edit')
            && $this->params->get('baz_check_owner_acl_only_for_field_can_edit'));
    }
    
    /**
     * @param string $tag
     * @param string $privilege
     * @param boolean $useDefaults
     * @return array [page_tag, privilege, list]
     */
    public function load($tag, $privilege, $useDefaults = true) : ?array
    {
        if (isset($this->cache[$tag][$privilege])) {
            return $this->cache[$tag][$privilege] ;
        }

        if ($useDefaults) {
            $this->cache[$tag] = [
                'read' => [
                    'page_tag' => $tag,
                    'privilege' => 'read',
                    'list' => $this->params->get('default_read_acl')
                ],
                'write' => [
                    'page_tag' => $tag,
                    'privilege' => 'write',
                    'list' => $this->params->get('default_write_acl')
                ],
                'comment' => [
                    'page_tag' => $tag,
                    'privilege' => 'comment',
                    'list' => $this->params->get('default_comment_acl')
                ]
            ];
        } else {
            $this->cache[$tag] = array();
        }

        $res = $this->dbService->loadAll('SELECT * FROM'.$this->dbService->prefixTable('acls').'WHERE page_tag = "'.$this->dbService->escape($tag).'"');

        foreach ($res as $acl) {
            $this->cache[$tag][$acl['privilege']] = $acl;
        }

        if (isset($this->cache[$tag][$privilege])) {
            return $this->cache[$tag][$privilege];
        }

        return null ;
    }

    /**
     * @param string $tag the page's tag
     * @param string $privilege the privilege
     * @param string $list the multiline string describing the acl
     */
    public function save($tag, $privilege, $list, $appendAcl = false)
    {
        // If list is comma-separated, convert into to line-break-separated
        if (strpos($list, ',') !== false) {
            $list = preg_replace('/\s*,\s*/', "\n", $list);
        }

        $acl = $this->load($tag, $privilege, false);

        if ($acl && $appendAcl) {
            $list = $acl['list']."\n".$list ;
        }

        if ($acl) {
            $this->dbService->query('UPDATE' . $this->dbService->prefixTable('acls') . 'SET list = "' . $this->dbService->escape(trim(str_replace("\r", '', $list))) . '" WHERE page_tag = "' . $this->dbService->escape($tag) . '" AND privilege = "' . $this->dbService->escape($privilege) . '"');
        } else {
            $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('acls') . "SET list = '" . $this->dbService->escape(trim(str_replace("\r", '', $list))) . "', page_tag = '" . $this->dbService->escape($tag) . "', privilege = '" . $this->dbService->escape($privilege) . "'");
        }

        // Update the cache
        $this->cache[$tag][$privilege] = array(
            'page_tag' => $tag,
            'privilege' => $privilege,
            'list' => $list
        );
    }

    /**
     *
     * @param string $tag The page's WikiName
     * @param string|array $privileges A privilege or several privileges to delete from database.
     */
    public function delete($tag, $privileges = ['read','write','comment'])
    {
        if (!is_array($privileges)) {
            $privileges = array($privileges);
        }

        // Add '"' at begin and end of each escaped privileges elements.
        for ($i=0; $i<count($privileges); $i++) {
            $privileges[$i] = '"'.$this->dbService->escape($privileges[$i]) .'"';
        }

        // Construct a CSV string with privileges elements
        $privileges = implode(',', $privileges);

        $this->dbService->query('DELETE FROM'.$this->dbService->prefixTable('acls').' WHERE page_tag = "'.$this->dbService->escape($tag).'" AND privilege IN ('.$privileges.')');

        if (isset($this->cache[$tag])) {
            unset($this->cache[$tag]);
        }
    }

    /**
     * Check if user has a privilege on page.
     * The page's owner has always access (always return true).
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag The page WikiName. Default to current page
     * @param string $user The username. Default to current user.
     * @return boolean true if access granted, false if not.
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        // set default to current page
        if (! $tag = trim($tag)) {
            $tag = $this->wiki->GetPageTag();
        }

        // set default to current user
        if (!$user) {
            $user = $this->userManager->getLoggedUserName();
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->wiki->UserIsOwner($tag)) {
            return true;
        }

        // load acl
        $acl = $this->load($tag, $privilege);
        // now check them
        $access = $this->check($acl['list'], $user);

        return $access ;
    }

    /**
     * Checks if some $user satisfies the given $acl
     *
     * @param string $acl
     *            The acl to check, in the same format than for pages ACL's
     * @param string $user
     *            The name of the user that must satisfy the ACL. By default
     *            the current remote user.
     * @param string $tag
     *            The name of the page or form to be tested when $acl contains '%'.
     *            By Default ''
     * @param string $mode
     *            Mode for cases when $acl contains '%'
     *            Default '', standard case. $mode = 'creation', the test returns true
     *            even if the user is connected
     * @return bool True if the $user satisfies the $acl, false otherwise
     */
    public function check($acl, $user = null, $adminCheck = true, $tag = '', $mode = '')
    {
        if (!$user) {
            $user = $this->userManager->getLoggedUserName();
        }

        if ($adminCheck && $this->wiki->UserIsAdmin($user)) {
            return true;
        }

        $acl = trim($acl);
        $result = false ; // result by default , this function is like a big "OR LOGICAL"

        foreach (explode("\n", $acl) as $line) {
            $line = trim($line);

            // check for inversion character "!"
            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $std_response = false ;
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
                        $result = ($this->userManager->getOneByName($user)) ? $std_response : !$std_response ;
                        break;
                    case '%': // owner
                        if ($mode == 'creation') {
                            // in creation mode, even if there is a tag
                            // the current user can access to field
                            $result = $std_response ;
                        } elseif ($tag == '') {
                            // to manage retrocompatibility without usage of CheckACL without $tag
                            // and no management of '%'
                            $result = false;
                        } elseif ($this->checkOwnerReadAcl || ($mode == 'edit')) {
                            $result = ($this->wiki->UserIsOwner($tag)) ? $std_response : !$std_response ;
                        }
                        break;
                    case '@': // groups
                        $gname = substr($line, 1);
                        // paranoiac: avoid line = '@'
                        if ($gname) {
                            if ($this->wiki->UserIsInGroup($gname, $user, false/* we have allready checked if user was an admin */)) {
                                $result = $std_response ;
                            } else {
                                $result = ! $std_response ;
                            }
                        } else {
                            $result = false ; // line '@'
                        }
                        break;
                    default: // simple user entry
                        if ($line == $user) {
                            $result = $std_response ;
                        } else {
                            $result = ! $std_response ;
                        }
                }
                if ($result) {
                    return true ;
                } // else continue like a big logical OR
            }
        }

        // tough luck.
        return false;
    }
}

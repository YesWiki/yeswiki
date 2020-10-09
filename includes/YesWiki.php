<?php
/**
 * Yeswiki is a great wiki
 *
 * @category Wiki
 * @package  YesWiki
 * @author   2002, Hendrik Mans <hendrik@mans.de>
 * @author   2003 Carlo Zottmann <secret@mail.com>
 * @author   2002, 2003, 2005 David DELON <secret@mail.com>
 * @author   2002, 2003, 2004, 2006 Charles NEPOTE <secret@mail.com>
 * @author   2002, 2003 Patrick PAUL <secret@mail.com>
 * @author   2003 Eric DELORD <secret@mail.com>
 * @author   2003 Eric FELDSTEIN <secret@mail.com>
 * @author   2004-2006 Jean-Christophe ANDRE <secret@mail.com>
 * @author   2005-2006 Didier LOISEAU <secret@mail.com>
 * @author   2009-2018 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */

namespace YesWiki;

require_once 'includes/constants.php';
require_once 'includes/urlutils.inc.php';
require_once 'includes/i18n.inc.php';
require_once 'includes/YesWikiInit.php';
require_once 'includes/Api.class.php';
require_once 'includes/Database.class.php';
require_once 'includes/Session.class.php';
require_once 'includes/User.class.php';

class Wiki
{
    public $dblink;
    public $page;
    public $tag;
    public $parameter = array();
    public $queryLog = array();
    public $interWiki = array();
    public $VERSION;
    public $CookiePath = '/';
    public $inclusions = array();
    public $extensions = array();
    public $api;
    public $db;
    public $session;
    public $user;

    /**
     * An array containing all the actions that are implemented by an object
     *
     * @access private
     */
    public $actionObjects;

    // LinkTrackink
    public $isTrackingLinks = false;
    public $linktable = array();
    public $pageCache = array();
    public $pageCacheFormatted = array();
    public $_groupsCache = array();
    public $_actionsAclsCache = array();

    /**
     * Constructor
     */
    public function __construct($config = array())
    {
        $init = new \YesWiki\Init($config);
        $this->config = $init->config;
        $this->CookiePath = $init->initCookies();
        $this->tag = $init->page;
        $this->method = $init->method;
        $this->dblink = $init->initDb();
        $this->api = new \YesWiki\Api($this);
        $this->db = new \YesWiki\Database($this);
        $this->session = new \YesWiki\Session($this);
        $this->user = new \YesWiki\User($this);
    }

    // DATABASE
    public function Query($query)
    {
        if ($this->GetConfigValue('debug')) {
            $start = $this->GetMicroTime();
        }

        if (! $result = mysqli_query($this->dblink, $query)) {
            ob_end_clean();
            die('Query failed: ' . $query . ' (' . mysqli_error($this->dblink) . ')');
        }
        if ($this->GetConfigValue('debug')) {
            $time = $this->GetMicroTime() - $start;
            $this->queryLog[] = array(
                'query' => $query,
                'time' => $time
            );
        }
        return $result;
    }

    public function LoadSingle($query)
    {
        if ($data = $this->LoadAll($query)) {
            return $data[0];
        }

        return null;
    }

    public function LoadAll($query)
    {
        $data = array();
        if ($r = $this->Query($query)) {
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            mysqli_free_result($r);
        }
        return $data;
    }

    // MISC
    public function GetMicroTime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }

    // VARIABLES
    public function GetPageTag()
    {
        return $this->tag;
    }

    public function GetPageTime()
    {
        return $this->page['time'];
    }

    public function GetMethod()
    {
        return $this->method;
    }

    public function GetConfigValue($name, $default=null)
    {
        return isset($this->config[$name])
            ? trim($this->config[$name])
            : ($default != null ? $default : '') ;
    }

    public function GetWakkaName()
    {
        return $this->GetConfigValue('wakka_name');
    }

    public function GetWakkaVersion()
    {
        return $this->config['wakka_version'];
    }

    public function GetWikiNiVersion()
    {
        return WIKINI_VERSION;
    }

    /**
     * A very simple Request level cache for triple resources
     *
     * @var array
     */
    protected $triplesCacheByResource = array();

    /**
     * Retrieves all the triples that match some criteria.
     * This allows to search triples by their approximate resource or property names.
     * The allowed operators are the sql "LIKE" and the sql "=".
     *
     * Does not use the cache $this->triplesCacheByResource.
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve or null
     * @param string $res_op
     *            The operator of comparison between the effective resource and $resource (default: 'LIKE')
     * @param string $prop_op
     *            The operator of comparison between the effective property and $property (default: '=')
     * @return array The list of all the triples that match the asked criteria
     */
    public function GetMatchingTriples($resource, $property = null, $res_op = 'LIKE', $prop_op = '=')
    {
        static $operators = array(
            '=',
            'LIKE'
        ); // we might want to add other operators later
        $res_op = strtoupper($res_op);
        if (! in_array($res_op, $operators)) {
            $res_op = '=';
        }

        $sql = 'SELECT * FROM ' . $this->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource ' . $res_op . ' "' . mysqli_real_escape_string($this->dblink, $resource) . '"';
        if ($property !== null) {
            $prop_op = strtoupper($prop_op);
            if (! in_array($prop_op, $operators)) {
                $prop_op = '=';
            }

            $sql .= ' AND property ' . $prop_op . ' "' . mysqli_real_escape_string($this->dblink, $property) . '"';
        }
        return $this->LoadAll($sql);
    }

    /**
     * Retrieves all the values for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to THISWIKI_PREFIX)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to WIKINI_VOC_PREFIX)
     * @return array An array of the retrieved values, in the form
     *         array(
     *         0 => array(id = 7 , 'value' => $value1),
     *         1 => array(id = 34, 'value' => $value2),
     *         ...
     *         )
     */
    public function GetAllTriplesValues($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;
        $prop = $prop_prefix . $property ;
        if (isset($this->triplesCacheByResource[$res])) {
            // All resource's properties was previously loaded.
            //error_log(__METHOD__.' cache hits ['.$res.']['.$prop.'] '. count($this->triplesCacheByResource));
            if (isset($this->triplesCacheByResource[$res][$prop])) {
                return $this->triplesCacheByResource[$res][$prop] ;
            }
            // LoadAll($sql) return an empty array when no result, do the same.
            return array();
        }
        //error_log(__METHOD__.' cache miss ['.$res.']['.$prop.'] '. count($this->triplesCacheByResource));
        $this->triplesCacheByResource[$res] = array();
        $sql = 'SELECT * FROM ' . $this->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->dblink, $res) . '"' ;
        foreach ($this->LoadAll($sql) as $triple) {
            if (! isset($this->triplesCacheByResource[$res][ $triple['property'] ])) {
                $this->triplesCacheByResource[$res][ $triple['property'] ] = array();
            }
            $this->triplesCacheByResource[$res][ $triple['property'] ][] = array( 'id'=>$triple['id'], 'value'=>$triple['value']) ;
        }
        if (isset($this->triplesCacheByResource[$res][$prop])) {
            return $this->triplesCacheByResource[$res][$prop] ;
        }
        return array() ;
    }

    /**
     * Retrieves a single value for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return string The value corresponding to ($resource, $property) or null if
     *         there is no such couple in the triples table.
     */
    public function GetTripleValue($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $this->GetAllTriplesValues($resource, $property, $re_prefix, $prop_prefix);
        if ($res) {
            return $res[0]['value'];
        }

        return null;
    }

    /**
     * Checks whether a triple exists or not
     *
     * @param string $resource
     *            The resource of the triple to find
     * @param string $property
     *            The property of the triple to find
     * @param string $value
     *            The value of the triple to find
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @param
     *            int The id of the found triple or 0 if there is no such triple.
     */
    public function TripleExists($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $sql = 'SELECT id FROM ' . $this->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->dblink, $re_prefix . $resource) . '" ' . 'AND property = "' . mysqli_real_escape_string($this->dblink, $prop_prefix . $property) . '" ' . 'AND value = "' . mysqli_real_escape_string($this->dblink, $value) . '"';
        $res = $this->LoadSingle($sql);
        if (! $res) {
            return 0;
        }

        return $res['id'];
    }

    /**
     * Inserts a new triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to insert
     * @param string $property
     *            The property of the triple to insert
     * @param string $value
     *            The value of the triple to insert
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (success), 1 (failure) or 3 (already exists)
     */
    public function InsertTriple($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        if ($this->TripleExists($res, $property, $value, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->triplesCacheByResource[$res])) {
            unset($this->triplesCacheByResource[$res]);
        }

        $sql = 'INSERT INTO ' . $this->GetConfigValue('table_prefix') . 'triples (resource, property, value)' . 'VALUES ("' . mysqli_real_escape_string($this->dblink, $res) . '", "' . mysqli_real_escape_string($this->dblink, $prop_prefix . $property) . '", "' . mysqli_real_escape_string($this->dblink, $value) . '")';
        return $this->Query($sql) ? 0 : 1;
    }

    /**
     * Updates a triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to update
     * @param string $property
     *            The property of the triple to update
     * @param string $oldvalue
     *            The old value of the triple to update
     * @param string $newvalue
     *            The new value of the triple to update
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (succ?s), 1 (?chec),
     *         2 ($resource, $property, $oldvalue does not exist)
     *         or 3 ($resource, $property, $newvalue already exists)
     */
    public function UpdateTriple($resource, $property, $oldvalue, $newvalue, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        $id = $this->TripleExists($res, $property, $oldvalue, '', $prop_prefix);
        if (! $id) {
            return 2;
        }

        if ($this->TripleExists($res, $property, $newvalue, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->triplesCacheByResource[$res])) {
            unset($this->triplesCacheByResource[$res]);
        }

        $sql = 'UPDATE ' . $this->GetConfigValue('table_prefix') . 'triples ' . 'SET value = "' . mysqli_real_escape_string($this->dblink, $newvalue) . '" ' . 'WHERE id = ' . $id;
        return $this->Query($sql) ? 0 : 1;
    }

    /**
     * Deletes a triple ($resource, $property, $value) from the triples' table
     *
     * @param string $resource
     *            The resource of the triple to delete
     * @param string $property
     *            The property of the triple to delete
     * @param string $value
     *            The value of the triple to delete. If set to <tt>null</tt>,
     *            deletes all the triples corresponding to ($resource, $property). (defaults to <tt>null</tt>)
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     */
    public function DeleteTriple($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        $sql = 'DELETE FROM ' . $this->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->dblink, $res) . '" ' . 'AND property = "' . mysqli_real_escape_string($this->dblink, $prop_prefix . $property) . '" ';
        if ($value !== null) {
            $sql .= 'AND value = "' . mysqli_real_escape_string($this->dblink, $value) . '"';
        }

        // invalidate the cache
        if (isset($this->triplesCacheByResource[$res])) {
            unset($this->triplesCacheByResource[$res]);
        }

        $this->Query($sql);
    }

    // inclusions
    /**
     * Enregistre une nouvelle inclusion dans la pile d'inclusions.
     *
     * @param string $pageTag
     *            Le nom de la page qui va etre inclue
     * @return int Le nombre d'elements dans la pile
     */
    public function RegisterInclusion($pageTag)
    {
        return array_unshift($this->inclusions, strtolower(trim($pageTag)));
    }

    /**
     * Retire le dernier element de la pile d'inclusions.
     *
     * @return string Le nom de la page dont l'inclusion devrait se terminer.
     *         null s'il n'y a plus d'inclusion dans la pile.
     */
    public function UnregisterLastInclusion()
    {
        return array_shift($this->inclusions);
    }

    /**
     * Renvoie le nom de la page en cours d'inclusion.
     *
     * @example // dans le cas d'une action comme l'ActionEcrivezMoi
     *          if($inc = $this->CurrentInclusion() && strtolower($this->GetPageTag()) != $inc)
     *          echo 'Cette action ne peut etre appelee depuis une page inclue';
     * @return string Le nom (tag) de la page (en minuscules)
     *         false si la pile est vide.
     */
    public function GetCurrentInclusion()
    {
        return isset($this->inclusions[0]) ? $this->inclusions[0] : false;
    }

    /**
     * Verifie si on est a l'interieur d'une inclusion par $pageTag (sans tenir compte de la casse)
     *
     * @param string $pageTag
     *            Le nom de la page a verifier
     * @return bool True si on est a l'interieur d'une inclusion par $pageTag (false sinon)
     */
    public function IsIncludedBy($pageTag)
    {
        return in_array(strtolower($pageTag), $this->inclusions);
    }

    /**
     *
     * @return array La pile d'inclusions
     *         L'element 0 sera la derniere inclusion, l'element 1 sera son parent et ainsi de suite.
     */
    public function GetAllInclusions()
    {
        return $this->inclusions;
    }

    /**
     * Remplace la pile des inclusions par une nouvelle pile (par defaut une pile vide)
     * Permet de formatter une page sans tenir compte des inclusions precedentes.
     *
     * @param array $
     *            La nouvelle pile d'inclusions.
     *            L'element 0 doit representer la derniere inclusion, l'element 1 son parent et ainsi de suite.
     * @return array L'ancienne pile d'inclusions, avec les noms des pages en minuscules.
     */
    public function SetInclusions($pile = array())
    {
        $temp = $this->inclusions;
        $this->inclusions = $pile;
        return $temp;
    }

    // PAGES
    public function LoadPage($tag, $time = "", $cache = 1)
    {
        // retrieve from cache
        if (! $time && $cache && (($cachedPage = $this->GetCachedPage($tag)) !== false)) {
            $page = $cachedPage;
        } else { // load page

            $sql = 'SELECT * FROM ' . $this->config['table_prefix'] . 'pages' . " WHERE tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "' AND " . ($time ? "time = '" . mysqli_real_escape_string($this->dblink, $time) . "'" : "latest = 'Y'") . " LIMIT 1";
            $page = $this->LoadSingle($sql);

            // the database is in ISO-8859-15, it must be converted
            if (isset($page['body'])) {
                $page['body'] = _convert($page['body'], 'ISO-8859-15');
            }

            // cache result
            if (! $time) {
                $this->CachePage($page, $tag);
            }
        }
        return $page;
    }

    /**
     * Retrieves the cached version of a page.
     *
     * Notice that this method null or false, use
     * $this->GetCachedPage($tag) === false
     * to check if a page is not in the cache.
     *
     * @return mixed The cached version of a page:
     *         - the page DB line if the page exists and is in cache
     *         - null if the cache knows that the page does not exists
     *         - false is the cache does not know the page
     */
    public function GetCachedPage($tag)
    {
        return (array_key_exists($tag, $this->pageCache) ? $this->pageCache[$tag] : false);
    }

    /**
     * Caches a page's DB line.
     *
     * @param array $page
     *            The page (full) DB line or null if the page does not exists
     * @param string $pageTag
     *            The tag of the page to cache. Defaults to $page['tag'] but is mendatory when $page === null
     */
    public function CachePage($page, $pageTag = null)
    {
        if ($pageTag === null) {
            $pageTag = $page['tag'];
        }
        $this->pageCache[$pageTag] = $page;
    }

    public function SetPage($page)
    {
        $this->page = $page;
        if ($this->page['tag']) {
            $this->tag = $this->page['tag'];
        }
    }

    public function LoadPageById($id)
    {
        return $this->LoadSingle('select * from ' . $this->config['table_prefix'] . "pages where id = '" . mysqli_real_escape_string($this->dblink, $id) . "' limit 1");
    }

    public function LoadRevisions($page)
    {
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . "pages where tag = '" . mysqli_real_escape_string($this->dblink, $page) . "' order by time desc");
    }

    public function LoadPagesLinkingTo($tag)
    {
        return $this->LoadAll('select from_tag as tag from ' . $this->config['table_prefix'] . "links where to_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "' order by tag");
    }

    public function LoadRecentlyChanged($limit = 50, $minDate = '')
    {
        if (!empty($minDate)) {
            if ($pages = $this->LoadAll('select id, tag, time, user, owner from ' . $this->config['table_prefix'] . "pages where latest = 'Y' and comment_on = '' and time >= '$minDate' order by time desc")) {
                //foreach ($pages as $page) {
                //    $this->CachePage($page);
                //}
                return $pages;
            }
        } else {
            $limit = (int) $limit;
            if ($pages = $this->LoadAll('select id, tag, time, user, owner from ' . $this->config['table_prefix'] . "pages where latest = 'Y' and comment_on = '' order by time desc limit $limit")) {
                //foreach ($pages as $page) {
                //    $this->CachePage($page);
                //}
                return $pages;
            }
        }
    }

    public function LoadAllPages()
    {
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . "pages where latest = 'Y' order by tag");
    }

    public function GetPageCreateTime($pageTag)
    {
        $sql = 'SELECT time FROM '.$this->config['table_prefix'].'pages'
            .' WHERE tag = "'.mysqli_real_escape_string($this->dblink, $pageTag).'"'
            .' AND comment_on = ""'
            .' ORDER BY `time` ASC LIMIT 1';
        $page = $this->LoadSingle($sql);
        if ($page) {
            return $page['time'];
        }
        return null ;
    }

    public function FullTextSearch($phrase)
    {
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . "pages where latest = 'Y' and (body LIKE '%" . mysqli_real_escape_string($this->dblink, $phrase) . "%' OR tag LIKE '%" . mysqli_real_escape_string($this->dblink, $phrase) . "%')");
    }

    public function LoadWantedPages()
    {
        $p = $this->config['table_prefix'];
        $r = "SELECT ${p}links.to_tag AS tag, COUNT(${p}links.from_tag) AS count " . "FROM ${p}links LEFT JOIN ${p}pages ON ${p}links.to_tag = ${p}pages.tag " . "WHERE ${p}pages.tag IS NULL GROUP BY ${p}links.to_tag ORDER BY count DESC, tag ASC";
        return $this->LoadAll($r);
    }

    public function LoadOrphanedPages()
    {
        return $this->LoadAll('select distinct tag from ' . $this->config['table_prefix'] . 'pages as p left join ' . $this->config['table_prefix'] . "links as l on p.tag = l.to_tag where l.to_tag is NULL and p.comment_on = '' and p.latest = 'Y' order by tag");
    }

    public function IsOrphanedPage($tag)
    {
        return $this->LoadAll('select distinct tag from ' . $this->config['table_prefix'] . 'pages as p left join ' . $this->config['table_prefix'] . "links as l on p.tag = l.to_tag where l.to_tag is NULL and p.latest = 'Y' and tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "'");
    }

    public function DeleteOrphanedPage($tag)
    {
        $p = $this->config['table_prefix'];
        $this->Query("DELETE FROM ${p}pages WHERE tag='" . mysqli_real_escape_string($this->dblink, $tag) . "' OR comment_on='" . mysqli_real_escape_string($this->dblink, $tag) . "'");
        $this->Query("DELETE FROM ${p}links WHERE from_tag='" . mysqli_real_escape_string($this->dblink, $tag) . "' ");
        $this->Query("DELETE FROM ${p}acls WHERE page_tag='" . mysqli_real_escape_string($this->dblink, $tag) . "' ");
        $this->Query("DELETE FROM ${p}referrers WHERE page_tag='" . mysqli_real_escape_string($this->dblink, $tag) . "' ");
    }

    /**
     * SavePage
     * Sauvegarde un contenu dans une page donnee
     *
     * @param string $body
     *            Contenu a sauvegarder dans la page
     * @param string $tag
     *            Nom de la page
     * @param string $comment_on
     *            Indication si c'est un commentaire
     * @param boolean $bypass_acls
     *            Indication si on bypasse les droits d'ecriture
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     */
    public function SavePage($tag, $body, $comment_on = "", $bypass_acls = false)
    {
        // get current user
        $user = $this->GetUserName();

        // check bypass of rights or write privilege
        $rights = $bypass_acls || ($comment_on ? $this->HasAccess('comment', $comment_on) : $this->HasAccess('write', $tag));

        if ($rights) {
            // is page new?
            if (! $oldPage = $this->LoadPage($tag)) {
                // create default write acl. store empty write ACL for comments.
                $this->SaveAcl($tag, 'write', ($comment_on ? $user : $this->GetConfigValue('default_write_acl')));

                // create default read acl
                $this->SaveAcl($tag, 'read', $this->GetConfigValue('default_read_acl'));

                // create default comment acl.
                $this->SaveAcl($tag, 'comment', ($comment_on ? '' : $this->GetConfigValue('default_comment_acl')));

                // current user is owner; if user is logged in! otherwise, no owner.
                if ($this->GetUser()) {
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
            $this->Query('update ' . $this->config['table_prefix'] . "pages set latest = 'N' where tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "'");

            // add new revision
            $this->Query('insert into ' . $this->config['table_prefix'] . 'pages set ' . "tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "', " . ($comment_on ? "comment_on = '" . mysqli_real_escape_string($this->dblink, $comment_on) . "', " : "") . "time = now(), " . "owner = '" . mysqli_real_escape_string($this->dblink, $owner) . "', " . "user = '" . mysqli_real_escape_string($this->dblink, $user) . "', " . "latest = 'Y', " . "body = '" . mysqli_real_escape_string($this->dblink, chop($body)) . "', " . "body_r = ''");
            unset($this->pageCache[$tag]);
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * AppendContentToPage
     * Ajoute du contenu a la fin d'une page
     *
     * @param string $content
     *            Contenu a ajouter a la page
     * @param string $page
     *            Nom de la page
     * @param boolean $bypass_acls
     *            Bouleen pour savoir s'il faut bypasser les ACLs
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     */
    public function AppendContentToPage($content, $page, $bypass_acls = false)
    {
        // Si un contenu est specifie
        if (isset($content)) {
            // -- Determine quelle est la page :
            // -- passee en parametre (que se passe-t'il si elle n'existe pas ?)
            // -- ou la page en cours par defaut
            $page = isset($page) ? $page : $this->GetPageTag();

            // -- Chargement de la page
            $result = $this->LoadPage($page);
            $body = $result['body'];
            // -- Ajout du contenu a la fin de la page
            $body .= $content;

            // -- Sauvegarde de la page
            // TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
            $this->SavePage($page, $body, '', $bypass_acls);

            // now we render it internally so we can write the updated link table.
            $this->ClearLinkTable();
            $this->StartLinkTracking();
            $temp = $this->SetInclusions();
            $this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
            $this->Format($body);
            $this->SetInclusions($temp);
            if ($user = $this->GetUser()) {
                $this->TrackLinkTo($user['name']);
            }
            if ($owner = $this->GetPageOwner()) {
                $this->TrackLinkTo($owner);
            }
            $this->StopLinkTracking();
            $this->WriteLinkTable();
            $this->ClearLinkTable(); /* */

            // Retourne 0 seulement si tout c'est bien passe
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * LogAdministrativeAction($user, $content, $page = "")
     *
     * @param string $user
     *            Utilisateur
     * @param string $content
     *            Contenu de l'enregistrement
     * @param string $page
     *            Page de log
     *
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     */
    public function LogAdministrativeAction($user, $content, $page = '')
    {
        $order = array(
            "\r\n",
            "\n",
            "\r"
        );
        $replace = '\\n';
        $content = str_replace($order, $replace, $content);
        $contentToAppend = "\n" . date('Y-m-d H:i:s') . ' . . . . ' . $user . ' . . . . ' . $content . "\n";
        $page = $page ? $page : 'LogDesActionsAdministratives' . date('Ymd');
        return $this->AppendContentToPage($contentToAppend, $page, true);
    }

    /**
     * Make the purge of page versions that are older than the last version older than 3 "pages_purge_time"
     * This method permits to allways keep a version that is older than that period.
     */
    public function PurgePages()
    {
        if ($days = $this->GetConfigValue('pages_purge_time')) {
            // is purge active ?
            // let's search which pages versions we have to remove
            // this is necessary beacause even MySQL does not handel multi-tables deletes before version 4.0
            $wnPages = $this->GetConfigValue('table_prefix') . 'pages';
            $sql = 'SELECT DISTINCT a.id FROM ' . $wnPages . ' a,' . $wnPages . ' b WHERE a.latest = \'N\' AND a.time < date_sub(now(), INTERVAL \'' . mysqli_real_escape_string($this->dblink, $days) . '\' DAY) AND a.tag = b.tag AND a.time < b.time AND b.time < date_sub(now(), INTERVAL \'' . mysqli_real_escape_string($this->dblink, $days) . '\' DAY)';
            $ids = $this->LoadAll($sql);

            if (count($ids)) {
                // there are some versions to remove from DB
                // let's build one big request, that's better...
                $sql = 'DELETE FROM ' . $wnPages . ' WHERE id IN (';
                foreach ($ids as $key => $line) {
                    $sql .= ($key ? ', ' : '') . $line['id']; // NB.: id is an int, no need of quotes
                }
                $sql .= ')';

                // ... and send it !
                $this->Query($sql);
            }
        }
    }

    // COOKIES
    public function SetSessionCookie($name, $value)
    {
        SetCookie($name, $value, 0, $this->CookiePath, '', !empty($_SERVER['HTTPS']), true);
        $_COOKIE[$name] = $value;
    }

    public function SetPersistentCookie($name, $value, $remember = 0)
    {
        SetCookie($name, $value, time() + ($remember ? 90 * 24 * 60 * 60 : 60 * 60), $this->CookiePath, '', !empty($_SERVER['HTTPS']), true);
        $_COOKIE[$name] = $value;
    }

    public function DeleteCookie($name)
    {
        SetCookie($name, '', 1, $this->CookiePath, '', !empty($_SERVER['HTTPS']), true);
        $_COOKIE[$name] = '';
    }

    public function GetCookie($name)
    {
        return $_COOKIE[$name];
    }

    // HTTP/REQUEST/LINK RELATED
    public function SetMessage($message)
    {
        $_SESSION['message'] = $message;
    }

    public function GetMessage()
    {
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
        } else {
            $message = '';
        }

        $_SESSION['message'] = '';
        return $message;
    }

    public function getBaseUrl()
    {
        $url = explode('wakka.php', $this->config['base_url']);
        $url = explode('index.php', $url[0]);
        $url = preg_replace(array('/\/\?$/', '/\/$/'), '', $url[0]);
        return $url;
    }

    public function Redirect($url)
    {
        header("Location: $url");
        exit();
    }
    // returns just PageName[/method].
    public function MiniHref($method = '', $tag = '')
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }

        return $tag . ($method ? '/' . $method : '');
    }
    // returns the full url to a page/method.
    public function Href($method = '', $tag = '', $params = '', $htmlspchars = true)
    {
        $href = $this->config["base_url"] . $this->MiniHref($method, $tag);
        if ($params) {
            $href .= ($this->config['rewrite_mode'] ? '?' : ($htmlspchars ? '&amp;' : '&')) . $params;
        }
        return $href;
    }

    public function Link($tag, $method = "", $text = "", $track = 1, $forcedLink = false)
    {
        $displayText = $text ? $text : $tag;

        // is this an interwiki link?
        if (preg_match('/^' . WN_INTERWIKI_CAPTURE . '$/', $tag, $matches)) {
            if ($IWiki = $this->GetInterWikiUrl($matches[1], $matches[2])) {
                return '<a href="'.htmlspecialchars($IWiki, ENT_COMPAT, YW_CHARSET)
                . '">' . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET)
                . ' (interwiki)</a>';
            } else {
                return '<a href="' . htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET)
                . '">' . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET)
                . ' (interwiki inconnu)</a>';
            }
        } else {
            // is this a full link? ie, does it contain non alpha-numeric characters?
            // Note : [:alnum:] is equivalent [0-9A-Za-z]
            // [^[:alnum:]] means : some caracters other than [0-9A-Za-z]
            // For example : "www.adress.com", "mailto:adress@domain.com", "http://www.adress.com"
            if ($text and preg_match("/\.(gif|jpeg|png|jpg|svg)$/i", $tag)) {
                // Important: Here, we know that $tag is not something bad
                // and that we must produce a link with it

                // An inline image? (text!=tag and url ends by png,gif,jpeg)
                return '<img src="' . htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET)
                .'" alt="'.htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET).'"/>';
            } elseif (preg_match('/^'.WN_CAMEL_CASE_EVOLVED.'$/u', $tag)) {
                if (! empty($track)) {
                    // it's a Wiki link!
                    $this->TrackLinkTo($tag);
                }
            } elseif ($safeUrl = str_replace(
                array('%3F', '%3A', '%26', '%3D', '%23'),
                array('?', ':', '&', '=', '#'),
                implode('/', array_map('rawurlencode', explode('/', rawurldecode($tag))))
            )
            ) {
                return '<a href="'.$safeUrl.'">'.$text.'</a>'."\n";
            } elseif (preg_match("/^[\w.-]+\@[\w.-]+$/", $tag)) {
                // check for various modifications to perform on $tag
                // email addresses
                $tag = 'mailto:' . $tag;
            } elseif (preg_match('/^[[:alnum:]][[:alnum:].-]*(?:\/|$)/', $tag)) {
                // protocol-less URLs
                $tag = 'https://' . $tag;
            } elseif (preg_match('/^[a-z0-9.+-]*script[a-z0-9.+-]*:/i', $tag)
                || ! (preg_match('/^\.?\.?\//', $tag)
                || preg_match('/^[a-z0-9.+-]+:\/\//i', $tag))
            ) {
                // Finally, block script schemes (see RFC 3986 about
                // schemes) and allow relative link & protocol-full URLs
                // If does't fit, we can't qualify $tag as an URL.
                // There is a high risk that $tag is just XSS (bad
                // javascript: code) or anything nasty. So we must not
                // produce any link at all.
                return htmlspecialchars(
                    $tag . ($text ? ' ' . $text : ''),
                    ENT_COMPAT,
                    YW_CHARSET
                );
            }
        }

        if ($this->LoadPage($tag)) {
            return '<a href="'.htmlspecialchars(
                $this->href($method, $tag),
                ENT_COMPAT,
                YW_CHARSET
            ).'">' . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET) . '</a>';
        } else {
            return '<span class="'.($forcedLink ? 'forced-link ' : '').'missingpage">'
            . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET).'</span><a href="'
            . htmlspecialchars($this->href("edit", $tag), ENT_COMPAT, YW_CHARSET)
            . '">?</a>';
        }
    }

    public function ComposeLinkToPage($tag, $method = "", $text = "", $track = 1)
    {
        if (! $text) {
            $text = $tag;
        }

        $text = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
        if ($track) {
            $this->TrackLinkTo($tag);
        }

        return '<a href="' . $this->href($method, $tag) . '">' . $text . '</a>';
    }

    public function IsWikiName($text, $type = WN_CAMEL_CASE_EVOLVED)
    {
        return preg_match('/^' . $type . '$/u', $text);
    }

    // LinkTracking management
    /**
     * Tracks the link to a given page (only if the LinkTracking is activated)
     *
     * @param string $tag
     *            The tag (name) of the page to track a link to.
     */
    public function TrackLinkTo($tag)
    {
        if ($this->LinkTracking()) {
            $this->linktable[] = $tag;
        }
    }

    /**
     *
     * @return array The current link tracking table
     */
    public function GetLinkTable()
    {
        return $this->linktable;
    }

    /**
     * Clears the link tracking table
     */
    public function ClearLinkTable()
    {
        $this->linktable = array();
    }

    /**
     * Starts the LinkTracking
     *
     * @return bool The previous state of the link tracking
     */
    public function StartLinkTracking()
    {
        return $this->LinkTracking(true);
    }

    /**
     * Stops the LinkTracking
     *
     * @return bool The previous state of the link tracking
     */
    public function StopLinkTracking()
    {
        return $this->LinkTracking(false);
    }

    /**
     * Sets and/or retrieve the state of the LinkTracking
     *
     * @param bool $newStatus
     *            The new status of the LinkTracking
     *            (defaults to <tt>null</tt> which lets it unchanged)
     * @return bool The previous state of the link tracking
     */
    public function LinkTracking($newStatus = null)
    {
        $old = $this->isTrackingLinks;
        if ($newStatus !== null) {
            $this->isTrackingLinks = $newStatus;
        }

        return $old;
    }

    public function WriteLinkTable()
    {
        // delete old link table
        $this->Query('delete from ' . $this->config['table_prefix'] . "links where from_tag = '" . mysqli_real_escape_string($this->dblink, $this->GetPageTag()) . "'");
        if ($linktable = $this->GetLinkTable()) {
            $from_tag = mysqli_real_escape_string($this->dblink, $this->GetPageTag());
            $written = [];
            foreach ($linktable as $to_tag) {
                $lower_to_tag = strtolower($to_tag);
                if (!isset($written[$lower_to_tag])) {
                    $this->Query('insert into ' . $this->config['table_prefix'] . "links set from_tag = '" . $from_tag . "', to_tag = '" . mysqli_real_escape_string($this->dblink, $to_tag) . "'");
                    $written[$lower_to_tag] = 1;
                }
            }
        }
    }

    public function Header()
    {
        $action = $this->GetConfigValue('header_action');
        if (($actionObj = &$this->GetActionObject($action)) && is_object($actionObj)) {
            return $actionObj->GenerateHeader();
        }
        return $this->Action($action, 1);
    }

    public function Footer()
    {
        $action = $this->GetConfigValue('footer_action');
        if (($actionObj = &$this->GetActionObject($action)) && is_object($actionObj)) {
            return $actionObj->GenerateFooter();
        }
        return $this->Action($action, 1);
    }

    // FORMS
    public function FormOpen($method = '', $tag = '', $formMethod = 'post', $class = '')
    {
        $result = "<form action=\"" . $this->href($method, $tag) . "\" method=\"" . $formMethod . "\"";
        $result .= ((! empty($class)) ? " class=\"" . $class . "\"" : "");
        $result .= ">\n";
        if (! $this->config['rewrite_mode']) {
            $result .= "<input type=\"hidden\" name=\"wiki\" value=\"" . $this->MiniHref($method, $tag) . "\" />\n";
        }

        return $result;
    }

    public function FormClose()
    {
        return "</form>\n";
    }

    // INTERWIKI STUFF
    public function ReadInterWikiConfig()
    {
        if ($lines = file('interwiki.conf')) {
            foreach ($lines as $line) {
                if ($line = trim($line)) {
                    list($wikiName, $wikiUrl) = explode(' ', trim($line));
                    $this->AddInterWiki($wikiName, $wikiUrl);
                }
            }
        }
    }

    public function AddInterWiki($name, $url)
    {
        $this->interWiki[strtolower($name)] = $url;
    }

    public function GetInterWikiUrl($name, $tag)
    {
        if (isset($this->interWiki[strtolower($name)])) {
            return $this->interWiki[strtolower($name)] . $tag;
        } else {
            return false;
        }
    }

    // REFERRERS
    public function LogReferrer($tag = "", $referrer = "")
    {
        // fill values
        if (! $tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }

        if (! $referrer = trim($referrer) and isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        // check if it's coming from another site
        if ($referrer && ! preg_match('/^' . preg_quote($this->GetConfigValue('base_url'), '/') . '/', $referrer)) {
            // avoid XSS (with urls like "javascript:alert()" and co)
            // by forcing http/https prefix
            // NB.: this does NOT exempt to htmlspecialchars() the collected URIs !
            if (! preg_match('`^https?://`', $referrer)) {
                return;
            }

            $this->Query('insert into ' . $this->config['table_prefix'] . 'referrers set ' . "page_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "', " . "referrer = '" . mysqli_real_escape_string($this->dblink, $referrer) . "', " . "time = now()");
        }
    }

    public function LoadReferrers($tag = "")
    {
        return $this->LoadAll('select referrer, count(referrer) as num from ' . $this->config['table_prefix'] . 'referrers ' . ($tag = trim($tag) ? "where page_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "'" : "") . " group by referrer order by num desc");
    }

    public function PurgeReferrers()
    {
        if ($days = $this->GetConfigValue("referrers_purge_time")) {
            $this->Query('delete from ' . $this->config['table_prefix'] . "referrers where time < date_sub(now(), interval '" . mysqli_real_escape_string($this->dblink, $days) . "' day)");
        }
    }

    // PLUGINS
    /**
     * Exacutes an "action" module and returns the generated output
     *
     * @param string $action
     *            The name of the action and its eventual parameters,
     *            as it appears in the page between "{{" and "}}"
     * @param boolean $forceLinkTracking
     *            By default, the link tracking will be disabled
     *            during the call of an action. Set this value to <code>true</code> to allow it.
     * @param array $vars
     *            An array of additionnal parameters to give to the action, in the form
     *            array( 'param' => 'value').
     *            This allows you to call Action() internally, setting $action to the name of the action
     *            you want to call and it's parameters in an array, wich is more efficient than
     *            the pattern-matching algorithm used to extract the parameters from $action.
     * @return The output generated by the action.
     */
    public function Action($action, $forceLinkTracking = 0, $vars = array())
    {
        $cmd = trim($action);
        $cmd = str_replace("\n", ' ', $cmd);

        // extract $action and $vars_temp ("raw" attributes)
        if (! preg_match("/^([a-zA-Z0-9_-]+)\/?(.*)$/", $cmd, $matches)) {
            return '<div class="alert alert-danger">' . _t('INVALID_ACTION') . ' &quot;' . htmlspecialchars($cmd, ENT_COMPAT, YW_CHARSET) . '&quot;</div>' . "\n";
        }
        list(, $action, $vars_temp) = $matches;
        $vars[$vars_temp] = $vars_temp; // usefull for {{action/vars_temp}}

        // now that we have the action's name, we can check if the user satisfies the ACLs
        if (! $this->CheckModuleACL($action, 'action')) {
            return '<div class="alert alert-danger">' . _t('ERROR_NO_ACCESS') . ' ' . $action . '.</div>' . "\n";
        }

        // match all attributes (key and value)
        // prepare an array for extract() to work with (in $this->IncludeBuffered())
        if (preg_match_all("/([a-zA-Z0-9]*)=\"(.*)\"/U", $vars_temp, $matches)) {
            for ($a = 0; $a < count($matches[1]); $a ++) {
                $vars[$matches[1][$a]] = $matches[2][$a];
            }
        }

        if (! $forceLinkTracking) {
            $this->StopLinkTracking();
        }

        if ($actionObj = &$this->GetActionObject($action)) {
            if (is_object($actionObj)) {
                $result = $actionObj->PerformAction($vars, $cmd);
            } else { // $actionObj is an error message
                $result = $actionObj;
            }
        } else { // $actionObj == null (not found, no error message)

            $this->parameter = &$vars;
            $result = $this->IncludeBuffered(strtolower($action) . '.php', '<div class="alert alert-danger">' . _t('UNKNOWN_ACTION') . " &quot;$action&quot;</div>\n", $vars, $this->config['action_path']);
            unset($this->parameter);
        }
        $this->StartLinkTracking(); // shouldn't we restore the previous status ?
        return $result;
    }

    /**
     * Finds the object corresponding to an action, if it exists.
     *
     * @param string $name
     *            The name of an action (should be alphanumeric)
     * @return mixed - null if the corresponding file was not found or the corresponding class didn't exist after inclusion
     *         - an error string if the corresponding file was found but an error append while loading it
     *         - the object corresponding to this action if no problem happend
     *         To check the result, you should use is_object() on it.
     *         You should always assign the result of this method by referrence
     *         to avoid copying the object, which is the default beheviour in PHP4.
     * @example $var = &$wiki->GetActionObject('actionname');
     *          if (is_object($var))
     *          {
     *          // normal behaviour
     *          }
     *          elseif ($var) // $var is not an object but an error string
     *          {
     *          // threat error
     *          }
     *          else // action was not found
     *          {
     *          // rescue from inexising action or sth
     *          }
     */
    public function &GetActionObject($name)
    {
        $name = strtolower($name);
        $actionObj = null; // the generated object
        if (! preg_match('/^[a-z0-9]+$/', $name)) { // paranoiac
            return $actionObj;
        }

        // already tried to load this object ? (may be null)
        if (isset($this->actionObjects[$name])) {
            return $this->actionObjects[$name];
        }

        // object not loaded, try to load it
        $filename = $name . '.class.php';
        // load parent class for all action objects (only once)
        require_once 'includes/action.class.php';
        // include the action file, this should return an empty string
        $result = $this->IncludeBuffered($filename, null, null, $this->GetConfigValue('action_path'));
        if ($result) {
            // the result was not an empty string, certainly an error message
            $actionObj = $result;
        } elseif ($result !== false) {
            // the result was empty but the file was found
            $class = 'Action' . ucfirst($name);
            if (class_exists($class)) {
                $actionObj = new $class($this);
                if (! is_a($actionObj, 'WikiniAction')) {
                    die(_t('INVALID_ACTION') . " '$name': " . _t('INCORRECT_CLASS'));
                }
            }
        }
        $this->actionObjects[$name] = &$actionObj;
        return $actionObj;
    }

    // /**
    //  * Retrieves the list of existing actions
    //  *
    //  * @return array An unordered array of all the available actions.
    //  */
    // public function GetActionsList()
    // {
    //     $action_path = $this->GetConfigValue('action_path');
    //     $list = array();
    //     if ($dh = opendir($action_path)) {
    //         while (($file = readdir($dh)) !== false) {
    //             if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches)) {
    //                 $list[] = $matches[1];
    //             }
    //         }
    //     }
    //     return $list;
    // }

    // /**
    //  * Retrieves the list of existing handlers
    //  *
    //  * @return array An unordered array of all the available handlers.
    //  */
    // public function GetHandlersList()
    // {
    //     $handler_path = $this->GetConfigValue('handler_path') . '/page/';
    //     $list = array();
    //     if ($dh = opendir($handler_path)) {
    //         while (($file = readdir($dh)) !== false) {
    //             if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches)) {
    //                 $list[] = $matches[1];
    //             }
    //         }
    //     }
    //     return $list;
    // }

    public function Method($method)
    {
        if (! $handler = $this->page['handler']) {
            $handler = 'page';
        }

        $methodLocation = $handler . '/' . $method . '.php';
        return $this->IncludeBuffered($methodLocation, '<i>' . _t('UNKNOWN_METHOD') . " \"$methodLocation\"</i>", "", $this->config['handler_path']);
    }

    public function Format($text, $formatter = 'wakka', $fullPageName = '')
    {
        if (!empty($fullPageName)) {
            //if (empty($this->pageCacheFormatted[$fullPageName])) {
            return $this->IncludeBuffered($formatter . '.php', '<i>' . _t('FORMATTER_NOT_FOUND') . " \"$formatter\"</i>", compact('text'), $this->config['formatter_path']);
        //}
            //return $this->pageCacheFormatted[$fullPageName];
        } else {
            return $this->IncludeBuffered($formatter . '.php', "<i>Impossible de trouver le formateur \"$formatter\"</i>", compact("text"), $this->config['formatter_path']);
        }
    }

    public function IncludeBuffered($filename, $notfoundText = '', $vars = [], $path = '')
    {
        if ($path) {
            $dirs = explode(':', $path);
        } else {
            $dirs = array(
                ''
            );
        }

        $included = array();
        $included['before'] = array();
        $included['new'] = array();
        $included['after'] = array();

        foreach ($dirs as $dir) {
            if ($dir) {
                if (!preg_match('~/$~', $dir)) {
                    $dir .= '/';
                }
            }
            $fullfilename = $dir . $filename;
            if (strstr($filename, 'page/')) {
                list($file, $extension) = explode('page/', $filename);
                $beforefullfilename = $dir . $file . 'page/__' . $extension;
            } else {
                $beforefullfilename = $dir . '__' . $filename;
            }

            list($file, $extension) = explode('.', $filename);
            $afterfullfilename = $dir . $file . '__.' . $extension;
            if (file_exists($beforefullfilename)) {
                $included['before'][] = $beforefullfilename;
            }

            if (file_exists($fullfilename)) {
                $included['new'][] = $fullfilename;
            }

            if (file_exists($afterfullfilename)) {
                $included['after'][] = $afterfullfilename;
            }
        }
        $plugin_output_new = '';
        $found = 0;

        if (is_array($vars)) {
            extract($vars);
        }

        foreach ($included['before'] as $before) {
            $found = 1;
            ob_start();
            include($before);
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();
        }
        foreach ($included['new'] as $new) {
            $found = 1;
            ob_start();
            include($new);
            $plugin_output_new = ob_get_contents();
            ob_end_clean();
            break;
        }
        foreach ($included['after'] as $after) {
            $found = 1;
            ob_start();
            include($after);
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();
        }
        if ($found) {
            return $plugin_output_new;
        }
        if ($notfoundText) {
            return $notfoundText;
        } else {
            return false;
        }
    }

    /**
     * Retrieves the list of existing actions
     *
     * @return array An unordered array of all the available actions.
     */
    public function GetActionsList()
    {
        $action_path = $this->GetConfigValue('action_path');
        $dirs = explode(":", $action_path);
        $list = array();
        foreach ($dirs as $dir) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('/^([a-zA-Z0-9_-]+)(.class)?.php$/', $file, $matches)) {
                        $list[] = $matches[1];
                    }
                }
            }
        }

        return array_unique($list);
    }

    /**
     * Retrieves the list of existing handlers
     *
     * @return array An unordered array of all the available handlers.
     */
    public function GetHandlersList()
    {
        $handler_path = $this->GetConfigValue('handler_path');
        $dirs = explode(":", $handler_path);
        $list = array();
        foreach ($dirs as $dir) {
            $dir .= '/page';
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('/^([a-zA-Z0-9_-]+)(.class)?.php$/', $file, $matches)) {
                        $list[] = $matches[1];
                    }
                }
            }
        }
        return array_unique($list);
    }


    // USERS
    public function LoadUser($name, $password = 0)
    {
        return $this->LoadSingle('select * from ' . $this->config['table_prefix'] . "users where name = '" . mysqli_real_escape_string($this->dblink, $name) . "' " . ($password === 0 ? "" : "and password = '" . mysqli_real_escape_string($this->dblink, $password) . "'") . ' limit 1');
    }

    public function loadUserByEmail($mail, $password = 0)
    {
        return $this->LoadSingle('select * from ' . $this->config['table_prefix'] . "users where email = '" . mysqli_real_escape_string($this->dblink, $mail) . "' " . ($password === 0 ? "" : "and password = '" . mysqli_real_escape_string($this->dblink, $password) . "'") . ' limit 1');
    }

    public function LoadUsers()
    {
        if (isset($this->config['user_table_prefix']) && !empty($this->config['user_table_prefix'])) {
            $prefix = $this->config['user_table_prefix'];
        } else {
            $prefix = $this->config["table_prefix"];
        }
        return $this->LoadAll('select * from ' . $prefix . 'users order by name');
    }

    public function GetUserName()
    {
        if ($user = $this->GetUser()) {
            $name = $user["name"];
        } else {
            $name = $_SERVER["REMOTE_ADDR"];
        }
        return $name;
    }

    public function GetUser()
    {
        return (isset($_SESSION['user']) ? $_SESSION['user'] : '');
    }

    public function SetUser($user, $remember = 0)
    {
        $_SESSION['user'] = $user;
        $this->SetPersistentCookie('name', $user['name'], $remember);
        $this->SetPersistentCookie('password', $user['password'], $remember);
        $this->SetPersistentCookie('remember', $remember, $remember);
    }

    public function LogoutUser()
    {
        $_SESSION['user'] = '';
        $this->DeleteCookie('name');
        $this->DeleteCookie('password');
    }

    public function UserWantsComments()
    {
        if (! $user = $this->GetUser()) {
            return false;
        }
        return ($user['show_comments'] == 'Y');
    }

    /**
     * Ajout d'un parametre
     *
     * @param string $parameter nom du parametre
     * @param mixed $value valeur du parametre
     * @return void
     */
    public function setParameter($parameter, $value)
    {
        $this->parameter[$parameter] = $value;
    }

    public function GetParameter($parameter, $default = '')
    {
        return (isset($this->parameter[$parameter]) ? $this->parameter[$parameter] : $default);
    }

    // COMMENTS
    /**
     * Charge les commentaires relatifs a une page.
     *
     * @param string $tag
     *            Nom de la page. Ex : "PagePrincipale"
     * @return array Tableau contenant tous les commentaires et leurs
     *         proprietes correspondantes.
     */
    public function LoadComments($tag)
    {
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . 'pages ' . "where comment_on = '" . mysqli_real_escape_string($this->dblink, $tag) . "' " . "and latest = 'Y' " . "order by substring(tag, 8) + 0");
    }

    /**
     * Charge les derniers commentaires de toutes les pages.
     *
     * @param int $limit
     *            Nombre de commentaires charges.
     *            0 par d?faut (ie tous les commentaires).
     * @return array Tableau contenant chaque commentaire et ses
     *         proprietes associees.
     * @todo Ajouter le parametre $start pour permettre une pagination
     *       des commentaires : ->LoadRecentComments(10, 10)
     */
    public function LoadRecentComments($limit = 0)
    {
        // The part of the query which limit the number of comments
        if (is_numeric($limit) && $limit > 0) {
            $lim = ' limit ' . $limit;
        } else {
            $lim = '';
        }

        // Query
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . 'pages ' . 'where comment_on != "" ' . "and latest = 'Y' " . "order by time desc " . $lim);
    }

    public function LoadRecentlyCommented($limit = 50)
    {
        $pages = array();

        // NOTE: this is really stupid. Maybe my SQL-Fu is too weak, but apparently there is no easier way to simply select
        // all comment pages sorted by their first revision's (!) time. ugh!

        // load ids of the first revisions of latest comments. err, huh?
        if ($ids = $this->LoadAll('select min(id) as id from ' . $this->config['table_prefix'] . 'pages where comment_on != "" group by tag order by id desc')) {
            // load complete comments
            $num = 0;
            $comments = [];
            foreach ($ids as $id) {
                $comment = $this->LoadSingle('select * from ' . $this->config['table_prefix'] . "pages where id = '" . $id['id'] . "' limit 1");
                if (!isset($comments[$comment['comment_on']]) && $num < $limit) {
                    $comments[$comment['comment_on']] = $comment;
                    $num ++;
                }
            }

            // now load pages
            if ($comments) {
                // now using these ids, load the actual pages
                foreach ($comments as $comment) {
                    $page = $this->LoadPage($comment['comment_on']);
                    $page['comment_user'] = $comment['user'];
                    $page['comment_time'] = $comment['time'];
                    $page['comment_tag'] = $comment['tag'];
                    $pages[] = $page;
                }
            }
        }
        // load tags of pages
        // return $this->LoadAll("select comment_on as tag, max(time) as time, tag as comment_tag, user from ".$this->config['table_prefix']."pages where comment_on != '' group by comment_on order by time desc");
        return $pages;
    }

    // ACCESS CONTROL
    // returns true if logged in user is owner of current page, or page specified in $tag
    public function UserIsOwner($tag = "")
    {
        // check if user is logged in
        if (! $this->GetUser()) {
            return false;
        }

        // set default tag
        if (! $tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }

        // check if user is owner
        if ($this->GetPageOwner($tag) == $this->GetUserName()) {
            return true;
        }
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return string the ACL associated with the group $gname
     * @see UserIsInGroup to check if a user belongs to some group
     */
    public function GetGroupACL($group)
    {
        if (array_key_exists($group, $this->_groupsCache)) {
            return $this->_groupsCache[$group];
        }
        return $this->_groupsCache[$group] = $this->GetTripleValue($group, WIKINI_VOC_ACLS, GROUP_PREFIX);
    }

    /**
     * Checks if a new group acl is not defined recursively
     * (this method expects that groups that are already defined are not themselves defined recursively...)
     *
     * @param string $gname
     *            The name of the group
     * @param string $acl
     *            The new acl for that group
     * @return boolean True iff the new acl defines the group recursively
     */
    public function MakesGroupRecursive($gname, $acl, $origin = null, $checked = array())
    {
        $gname = strtolower($gname);
        if ($origin === null) {
            $origin = $gname;
        } elseif ($gname === $origin) {
            return true;
        }
        foreach (explode("\n", $acl) as $line) {
            if (! $line) {
                continue;
            }

            if ($line[0] == '!') {
                $line = substr($line, 1);
            }
            if (! $line) {
                continue;
            }

            if ($line[0] == '@') {
                $line = substr($line, 1);
                if (! in_array($line, $checked)) {
                    if ($this->MakesGroupRecursive($line, $this->GetGroupACL($line), $origin, $checked)) {
                        return true;
                    }
                }
            }
        }
        $checked[] = $gname;
        return false;
    }

    /**
     * Sets a new ACL to a given group
     *
     * @param string $gname
     *            The name of a group
     * @param string $acl
     *            The new ACL to associate with the group $gname
     * @return int 0 if successful, a triple error code or a specific error code:
     *         1000 if the new value would define the group recursively
     *         1001 if $gname is not named with alphanumeric chars
     * @see GetGroupACL
     */
    public function SetGroupACL($gname, $acl)
    {
        if (preg_match('/[^A-Za-z0-9]/', $gname)) {
            return 1001;
        }
        $old = $this->GetGroupACL($gname);
        if ($this->MakesGroupRecursive($gname, $acl)) {
            return 1000;
        }
        $this->_groupsCache[$gname] = $acl;
        if ($old === null) {
            return $this->InsertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        } else {
            return $this->UpdateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
        }
    }

    /**
     *
     * @return array The list of all group names
     */
    public function GetGroupsList()
    {
        $res = $this->GetMatchingTriples(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI);
        $prefix_len = strlen(GROUP_PREFIX);
        $list = array();
        foreach ($res as $line) {
            $list[] = substr($line['resource'], $prefix_len);
        }
        return $list;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return boolean true iff the user is in the given $group
     */
    public function UserIsInGroup($group, $user = null, $admincheck = true)
    {
        return $this->CheckACL($this->GetGroupACL($group), $user, $admincheck);
    }

    /**
     * Checks if a given user is andministrator
     *
     * @param string $user
     *            The name of the user (defaults to the current user if not given)
     * @return boolean true iff the user is an administrator
     */
    public function UserIsAdmin($user = null)
    {
        return $this->UserIsInGroup(ADMIN_GROUP, $user, false);
    }

    public function GetPageOwner($tag = "", $time = "")
    {
        if (! $tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }
        if ($page = $this->LoadPage($tag, $time)) {
            return isset($page["owner"]) ? $page["owner"] : null;
        }
    }

    public function SetPageOwner($tag, $user)
    {
        // check if user exists
        if (! $this->LoadUser($user)) {
            return;
        }

        // updated latest revision with new owner
        $this->Query('update ' . $this->config['table_prefix'] . "pages set owner = '" . mysqli_real_escape_string($this->dblink, $user) . "' where tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "' and latest = 'Y' limit 1");
    }

    /**
     * Caching page ACLs (sql query on yeswiki_acls table).
     * Filled in LoadAcl().
     * Updated in SaveAcl().
     * @var array
     */
    protected $aclsCache = array() ;

    /**
     *
     * @param string $tag
     * @param string $privilege
     * @param boolean $useDefaults
     * @return array [page_tag, privilege, list]
     */
    public function LoadAcl($tag, $privilege, $useDefaults = true)
    {
        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege] ;
        }

        if ($useDefaults) {
            $this->aclsCache[$tag] = array(
                'read' => array(
                    'page_tag' => $tag,
                    'privilege' => 'read',
                    'list' => $this->GetConfigValue('default_read_acl')
                ),
                'write' => array(
                    'page_tag' => $tag,
                    'privilege' => 'write',
                    'list' => $this->GetConfigValue('default_write_acl')
                ),
                'comment' => array(
                    'page_tag' => $tag,
                    'privilege' => 'comment',
                    'list' => $this->GetConfigValue('default_comment_acl')
                )
            );
        } else {
            $this->aclsCache[$tag] = array();
        }

        $res = $this->LoadAll('SELECT * FROM '.$this->config['table_prefix'].'acls'.' WHERE page_tag = "'.mysqli_real_escape_string($this->dblink, $tag).'"');
        foreach ($res as $acl) {
            $this->aclsCache[$tag][$acl['privilege']] = $acl;
        }

        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege];
        }
        return null ;
    }

    /**
     *
     * @param string $tag the page's tag
     * @param string $privilege the privilege
     * @param string $list the multiline string describing the acl
     */
    public function SaveAcl($tag, $privilege, $list, $appendAcl = false)
    {
        // if list is comma separated, convert into to line break separated
        if (strpos($list, ',') !== false) {
           $list = preg_replace('/\s*,\s*/', "\n", $list);
        }
        $acl = $this->LoadAcl($tag, $privilege, false);

        if ($acl && $appendAcl) {
            $list = $acl['list']."\n".$list ;
        }

        if ($acl) {
            $this->Query('update ' . $this->config['table_prefix'] . 'acls set list = "' . mysqli_real_escape_string($this->dblink, trim(str_replace("\r", '', $list))) . '" where page_tag = "' . mysqli_real_escape_string($this->dblink, $tag) . '" and privilege = "' . mysqli_real_escape_string($this->dblink, $privilege) . '"');
        } else {
            $this->Query('insert into ' . $this->config['table_prefix'] . "acls set list = '" . mysqli_real_escape_string($this->dblink, trim(str_replace("\r", '', $list))) . "', page_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "', privilege = '" . mysqli_real_escape_string($this->dblink, $privilege) . "'");
        }

        // update the acls cache
        $this->aclsCache[$tag][$privilege] = array(
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
    public function DeleteAcl($tag, $privileges=array('read','write','comment'))
    {
        if (! is_array($privileges)) {
            $privileges = array($privileges);
        }

        // add '"' at begin and end of each escaped privileges elements.
        for ($i=0; $i<count($privileges); $i++) {
            $privileges[$i] = '"'.mysqli_real_escape_string($this->dblink, $privileges[$i]) .'"';
        }
        // construct a CSV string with privileges elements
        $privileges = implode(',', $privileges);

        $this->Query('DELETE FROM ' . $this->config['table_prefix'] . 'acls'
            .' WHERE page_tag = "' . mysqli_real_escape_string($this->dblink, $tag) . '"'
            .' AND privilege IN (' . $privileges .')');

        if (isset($this->aclsCache[$tag])) {
            unset($this->aclsCache[$tag]);
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
    public function HasAccess($privilege, $tag = '', $user = '')
    {

        // set default to current page
        if (! $tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }
        // set default to current user
        if (! $user) {
            $user = $this->GetUserName();
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->UserIsOwner($tag)) {
            return true;
        }

        // load acl
        $acl = $this->LoadAcl($tag, $privilege);
        // now check them
        $access = $this->CheckACL($acl['list'], $user);

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
     * @return bool True if the $user satisfies the $acl, false otherwise
     */
    public function CheckACL($acl, $user = null, $admincheck = true)
    {
        if (! $user) {
            $user = $this->GetUserName();
        }

        if ($admincheck && $this->UserIsAdmin($user)) {
            return true;
        }

        foreach (explode("\n", $acl) as $line) {
            $line = trim($line);

            // check for inversion character "!"
            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $negate = true ;
                $line = $matches[1];
            } else {
                $negate = false;
            }

            // if there's still anything left... lines with just a "!" don't count!
            if ($line) {
                switch ($line[0]) {
                    case '#': // comments
                        break;
                    case '*': // everyone
                        return ! $negate;
                    case '+': // registered users
                        if (! $this->LoadUser($user)) {
                            return $negate;
                        } else {
                            return ! $negate;
                        }
                        // no break
                    case '@': // groups
                        $gname = substr($line, 1);
                        // paranoiac: avoid line = '@'
                        if ($gname && $this->UserIsInGroup($gname, $user, false/* we have allready checked if user was an admin */)) {
                            return ! $negate;
                        }
                        break;
                    default: // simple user entry
                        if ($line == $user) {
                            return ! $negate;
                        }
                }
            }
        }

        // tough luck.
        return false;
    }

    /**
     * Loads the module (handlers) ACL for a certain module.
     *
     * Database example row :
     *  resource = http://www.wikini.net/_vocabulary/handler/addcomment
     *  property = 'http://www.wikini.net/_vocabulary/acls'
     *  value = +
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module: 'action' or 'handler'
     * @return string The ACL string  for the given module or "*" if not found.
     */
    public function GetModuleACL($module, $module_type)
    {
        $module = strtolower($module);
        switch ($module_type) {

            case 'action':
                if (array_key_exists($module, $this->_actionsAclsCache)) {
                    $acl = $this->_actionsAclsCache[$module];
                    break;
                }
                $this->_actionsAclsCache[$module] = $acl = $this->GetTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_ACTIONS_PREFIX);
                if ($acl === null) {
                    $action = &$this->GetActionObject($module);
                    if (is_object($action)) {
                        return $this->_actionsAclsCache[$module] = $action->GetDefaultACL();
                    }
                }
                break;

            case 'handler':
                $acl = $this->GetTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_HANDLERS_PREFIX);
                break;
            default:
                return null; // TODO error msg ?
        }
        return $acl === null ? '*' : $acl;
    }

    /**
     * Sets the $acl for a given $module
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module ('action' or 'handler')
     * @param string $acl
     *            The new ACL for that module
     * @return 0 on success, > 0 on error (see InsertTriple and UpdateTriple)
     */
    public function SetModuleACL($module, $module_type, $acl)
    {
        $module = strtolower($module);
        $voc_prefix = $module_type == 'action' ? WIKINI_VOC_ACTIONS_PREFIX : WIKINI_VOC_HANDLERS_PREFIX;
        $old = $this->GetTripleValue($module, WIKINI_VOC_ACLS, $voc_prefix);

        if ($module_type == 'action') {
            $this->_actionsAclsCache[$module] = $acl;
        }

        if ($old === null) {
            return $this->InsertTriple($module, WIKINI_VOC_ACLS, $acl, $voc_prefix);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        } else {
            return $this->UpdateTriple($module, WIKINI_VOC_ACLS, $old, $acl, $voc_prefix);
        }
    }

    /**
     * Checks if a $user satisfies the ACL to access a certain $module
     *
     * @param string $module
     *            The name of the module to access
     * @param string $module_type
     *            The type of the module ('action' or 'handler')
     * @param string $user
     *            The name of the user. By default
     *            the current remote user.
     * @return bool True if the $user has access to the given $module, false otherwise.
     */
    public function CheckModuleACL($module, $module_type, $user = null)
    {
        $acl = $this->GetModuleACL($module, $module_type);
        if ($acl === null) {
            return true;
        }
        // undefined ACL means everybody has access
        return $this->CheckACL($acl, $user);
    }

    // MAINTENANCE
    public function Maintenance()
    {
        // purge referrers
        $this->PurgeReferrers();
        // purge old page revisions
        $this->PurgePages();
    }

    // THE BIG EVIL NASTY ONE!
    public function Run($tag = '', $method = '')
    {
        if (! ($this->GetMicroTime() % 9)) {
            $this->Maintenance();
        }

        $this->ReadInterWikiConfig();

        // do our stuff!
        if ($tag == '') {
            $tag = $this->tag;
        }

        if (! $this->method = trim($method)) {
            $this->method = "show";
        }

        if (! $this->tag = trim($tag)) {
            $this->Redirect($this->href("", $this->config['root_page']));
        }

        if ((! $this->GetUser() && isset($_COOKIE['name'])) && ($user = $this->LoadUser($_COOKIE['name'], $_COOKIE['password']))) {
            $this->SetUser($user, $_COOKIE['remember']);
        }

        if ($tag == 'api') {
            $apiKey = $this->api->getBearerToken();
            // test if key exists in order to authorize access
            if (empty($this->config['api_allowed_keys'])) {
                http_response_code(403);
                header("Access-Control-Allow-Origin: * ");
                header("Content-Type: application/json; charset=UTF-8");
                echo json_encode(array("message" => "No api keys found, api is unavailable for this yeswiki."));
            } elseif(!empty($this->config['api_allowed_keys']['public']) || in_array($apiKey, $this->config['api_allowed_keys']) ) {
                $func = $this->method;
                if (function_exists($func)) {
                    echo $func($GLOBALS['api_args']);
                } else {
                    echo $this->Header();
                    echo $this->api->documentationYesWiki();
                    $extensions = array_keys($this->extensions);
                    foreach ($extensions as $extension) {
                        $func = 'documentation'.ucfirst(strtolower($extension));
                        if (function_exists($func)) {
                            echo $func();
                        }
                    }
                    echo $this->Footer();
                }
            } else {
                http_response_code(401);
                header("Access-Control-Allow-Origin: * ");
                header("Content-Type: application/json; charset=UTF-8");
                echo json_encode(array("message" => "You are not allowed to use this api, check your api key."));
            }
            //TODO : add jwt token auth cf. https://github.com/tecnom1k3/sp-simple-jwt/blob/master/public/login.php
            exit();
        }

        $this->SetPage($this->LoadPage($tag, (isset($_REQUEST['time']) ? $_REQUEST['time'] : '')));
        $this->LogReferrer();

        // correction pour un support plus facile de nouveaux handlers
        if ($this->CheckModuleACL($this->method, 'handler')) {
            echo $this->Method($this->method);
        } else {
            echo _t('HANDLER_NO_ACCESS');
        }

        // action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
        if (! empty($_SESSION['redirects'])) {
            session_unregister('redirects');
        }
    }

    public function AddCSS($style)
    {
        if (!isset($GLOBALS['css'])) {
            $GLOBALS['css'] = '';
        }
        if (!empty($style) && !strpos($GLOBALS['css'], '<style>'."\n".$style.'</style>')) {
            $GLOBALS['css'] .= '  <style>'."\n".$style.'</style>'."\n";
        }
        return;
    }

    public function AddCSSFile($file, $conditionstart = '', $conditionend = '')
    {
        if (!isset($GLOBALS['css'])) {
            $GLOBALS['css'] = '';
        }
        if (!empty($file) && file_exists($file)) {
            if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$file.'">')) {
                $GLOBALS['css'] .= '  '.$conditionstart."\n"
                .'  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$file.'">'
                ."\n".'  '.$conditionend."\n";
            }
        } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
            if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$file.'">')) {
                $GLOBALS['css'] .= '  '.$conditionstart."\n"
                    .'  <link rel="stylesheet" href="'.$file.'">'."\n"
                    .'  '.$conditionend."\n";
            }
        }
        return;
    }

    public function AddJavascript($script)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }
        if (!empty($script) && !strpos($GLOBALS['js'], '<script>'."\n".$script.'</script>')) {
            $GLOBALS['js'] .= '  <script>'."\n".$script.'</script>'."\n";
        }
        return;
    }

    public function AddJavascriptFile($file, $first = false)
    {
        if (!isset($GLOBALS['js'])) {
            $GLOBALS['js'] = '';
        }
        if (!empty($file) && file_exists($file)) {
            if (!strpos($GLOBALS['js'], '<script defer src="'.$this->getBaseUrl().'/'.$file.'"></script>')) {
                if ($first) {
                    $GLOBALS['js'] = '  <script src="'.$this->getBaseUrl().'/'.$file.'"></script>'."\n".$GLOBALS['js'];
                } else {
                    $GLOBALS['js'] .= '  <script defer src="'.$this->getBaseUrl().'/'.$file.'"></script>'."\n";
                }
            }
        } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
            if (!strpos($GLOBALS['js'], '<script defer src="'.$file.'"></script>')) {
                $GLOBALS['js'] .= '  <script defer src="'.$file.'"></script>'."\n";
            }
        }
        return;
    }


    public function GetMetaDatas($pagetag)
    {
        $metadatas = $this->GetTripleValue($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', '', '', '');
        if (!empty($metadatas)) {
            if (YW_CHARSET != 'UTF-8') {
                return array_map('utf8_decode', json_decode($metadatas, true));
            } else {
                return json_decode($metadatas, true);
            }
        } else {
            return false;
        }
    }

    public function SaveMetaDatas($pagetag, $metadatas)
    {
        $former_metadatas = $this->GetMetaDatas($pagetag);

        if ($former_metadatas) {
            $metadatas = array_merge($former_metadatas, $metadatas);
            $this->DeleteTriple($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', null, '', '');
        }
        if (YW_CHARSET != 'UTF-8') {
            $metadatas = json_encode(array_map("utf8_encode", $metadatas));
        } else {
            $metadatas = json_encode($metadatas);
        }
        return $this->InsertTriple($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', $metadatas, '', '');
    }

    /**
     * Load all extensions
     *
     * @param mixed $wiki main YesWiki object
     *
     * @return void
     */
    public function loadExtensions()
    {
        $wakkaConfig = $this->config;
        $wiki = $this;
        $page = $wiki->tag;
        loadpreferredI18n($page);
        $plugins_root = 'tools/';

        include_once 'includes/YesWikiPlugins.php';
        $objPlugins = new \YesWiki\Plugins($plugins_root);
        $objPlugins->getPlugins(true);
        $pluginsList = $objPlugins->getPluginsList();
        $yeswikiClasses = [];
        foreach ($pluginsList as $k => $v) {
            $pluginBase = $plugins_root . $k . '/';

            if (file_exists($pluginBase . 'wiki.php')) {
                include $pluginBase . 'wiki.php';
            }

            // language files : first default language, then preferred language
            if (file_exists($pluginBase . 'lang/' . $k . '_fr.inc.php')) {
                include $pluginBase . 'lang/' . $k . '_fr.inc.php';
            }
            if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
                include $pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php';
            }

            // api functions
            if (file_exists($pluginBase . 'libs/' . $k . '.api.php')) {
                include $pluginBase . 'libs/' . $k . '.api.php';
            }

            // YesWiki Classes
            $currentClassFile = $pluginBase . 'libs/' . $k . '.class.inc.php';
            if (file_exists($currentClassFile)) {
                $yeswikiClasses['\YesWiki\\'.ucfirst(strtolower($k))] = $currentClassFile;
            }

            if (file_exists($pluginBase . 'actions')) {
                $wakkaConfig['action_path'] = $pluginBase . 'actions/' . ':' . $wakkaConfig['action_path'];
            }
            if (file_exists($pluginBase . 'handlers')) {
                $wakkaConfig['handler_path'] = $pluginBase . 'handlers/' . ':' . $wakkaConfig['handler_path'];
            }
            if (file_exists($pluginBase . 'formatters')) {
                $wakkaConfig['formatter_path'] = $pluginBase . 'formatters/' . ':' . $wakkaConfig['formatter_path'];
            }
        }

        $previous = false;
        foreach ($yeswikiClasses as $yeswikiClass => $path) {
            $classContent = file_get_contents($path);
            if (!$previous) {
                $previous = $yeswikiClass;
            } else {
                $classContent = str_replace('extends \YesWiki\Wiki', 'extends '.$previous, $classContent);
                $previous = $yeswikiClass;
            }
            eval('?>' . $classContent);
            $wiki = new $yeswikiClass($wakkaConfig);
        }
        $wiki->config = $wakkaConfig;
        $wiki->extensions = $pluginsList;

        return $wiki;
    }
}

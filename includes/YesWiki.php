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
require_once 'includes/Session.class.php';
require_once 'includes/User.class.php';
require_once 'includes/YesWikiPerformable.php';
require_once 'includes/objects/YesWikiAction.php';
require_once 'includes/objects/YesWikiHandler.php';
require_once 'includes/objects/YesWikiFormatter.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ApiService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\YesWikiControllerResolver;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;

class Wiki
{
    public $dblink;
    public $page;
    public $tag;
    public $parameter = array();
    public $request;
    // current output used for actions/handlers/formatters
    public $output;
    public $interWiki = array();
    public $VERSION;
    public $CookiePath = '/';
    public $inclusions = array();
    public $extensions = array();
    public $routes = array();
    public $session;
    public $user;
    public $services;

    /**
     * An array containing all the actions that are implemented by an object
     *
     * @access private
     */
    public $actionObjects;

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

        $this->services = $init->initCoreServices($this);
        $this->loadExtensions();
        $this->routes = $init->initRoutes($this);

        $this->session = new \YesWiki\Session($this);
        $this->user = new \YesWiki\User($this);
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
        return empty($this->page['time']) ?  '' : $this->page['time'];
    }

    public function GetMethod()
    {
        if ($this->method=='iframe') {
            return 'show';
        } elseif ($this->method=='editiframe') {
            return 'edit';
        } else {
            return $this->method;
        }
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

    public function SetPage($page)
    {
        if (!empty($page)) {
            $this->page = $page;
            if (!empty($this->page['tag'])) {
                $this->tag = $this->page['tag'];
            }
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
        $linkTracker = $this->services->get(LinkTracker::class);

        // Si un contenu est specifie
        if (isset($content)) {
            // -- Determine quelle est la page :
            // -- passee en parametre (que se passe-t'il si elle n'existe pas ?)
            // -- ou la page en cours par defaut
            $page = isset($page) ? $page : $this->GetPageTag();

            // -- Chargement de la page
            $result = $this->LoadPage($page);
            $body = empty($result['body']) ? '' : $result['body'];
            // -- Ajout du contenu a la fin de la page
            $body .= $content;

            // -- Sauvegarde de la page
            // TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
            $this->SavePage($page, $body, '', $bypass_acls);

            // now we render it internally so we can write the updated link table.
            $linkTracker->clear();
            $linkTracker->start();
            // on simule totalement un affichage normal
            $temp = $this->SetInclusions();
            $this->RegisterInclusion($this->GetPageTag());
            $this->Format($body);
            $this->SetInclusions($temp);
            if ($user = $this->GetUser()) {
                $linkTracker->add($user['name']);
            }
            if ($owner = $this->GetPageOwner()) {
                $linkTracker->add($owner);
            }
            $linkTracker->stop();
            $linkTracker->persist();
            $linkTracker->clear();

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
        if (($days = $this->GetConfigValue('pages_purge_time')) && !$this->services->get(SecurityController::class)->isWikiHibernated()) {
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
    public function MiniHref($method = null, $tag = null)
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }

        return $tag . ($method ? '/' . $method : '');
    }

    // returns the full url to a page/method.
    public function Href($method = null, $tag = null, $params = null, $htmlspchars = true)
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }
        $href = $this->config["base_url"] . $this->MiniHref($method, $tag);
        if ($params) {
            if (is_array($params)) {
                $paramsArray = [];
                foreach ($params as $key => $value) {
                    if ($value) {
                        $paramsArray[] = "$key=$value";
                    }
                };
                if (count($paramsArray)>0) {
                    $params = implode(($htmlspchars ? '&amp;' : '&'), $paramsArray);
                } else {
                    $params = '';
                }
            }
            $href .= ($this->config['rewrite_mode'] ? '?' : ($htmlspchars ? '&amp;' : '&')) . $params;
        }
        if (isset($_GET['lang']) && $_GET['lang']!='') {
            $href .= '&lang='.$GLOBALS['prefered_language'];
        }
        return $href;
    }

    public function Link($tag, $method = null, $params = null, $text = null, $track = 1, $forcedLink = false)
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
            } elseif (preg_match('/^' . WN_CAMEL_CASE_EVOLVED_WITH_SLASH_AND_PARAMS . '$/u', $tag)) {
                if (! empty($track)) {
                    // it's a Wiki link!
                    $this->TrackLinkTo(explode('?', $tag)[0]);
                }
            } elseif ($safeUrl = str_replace(
                array('%3F', '%3A', '%26', '%3D', '%23'),
                array('?', ':', '&', '=', '#'),
                implode('/', array_map('rawurlencode', explode('/', rawurldecode($tag))))
            )
            ) {
                return '<a href="'.$safeUrl.'">'.$text.'</a>';
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

        if ((!empty($method) && $method != 'show') || $this->LoadPage($tag)) {
            // if the page refers to an handler url (contains /) or an existing page, display a 'show' link
            return '<a href="' . $this->href($method, $tag, $params) . '">'
                . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET) . '</a>';
        } else {
            // otherwise display an 'edit' link
            return '<span class="' . ($forcedLink ? 'forced-link ' : '') . 'missingpage">'
                . htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET) . '</span><a href="'
                . $this->href("edit", $tag) . '">?</a>';
        }
    }

    /**
     * Handle string that could be a valid link, a yeswiki short link, or anything else (anchor, relative url..)
     *
     * if a yeswiki short link if discovered, it will be completed in order to have a real link
     * @param string $link the link to parse
     * @return string final form of the link
     *
     */
    public function generateLink($link): ?string
    {
        if (empty($link)) {
            return null;
        } else {
            $linkParts = $this->extractLinkParts($link) ;
            if ($linkParts) {
                return $this->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
            } elseif (filter_var($link, FILTER_VALIDATE_URL)) {
                // a valid url
                return $link;
            } else {
                // for now let's be tolerant : it may be a relative url or an anchor
                return $link;
            }
        }
    }
    /**
     * Extract the different part of a link of the style MyTag/method?param1=value1&param2=value2...
     *
     * The resulting array has the tree keys : 'tag' (string), 'method' (string) and 'params' (arrays of key/value for
     * each param). 'tag' can't have a null value, but 'method' can, and 'params' can also return an empty array.
     * If the link has a '/' and a '?' but no letter between (no method), the url is not recognized.
     * @param $link the link to parse
     * @return array|null if the link is recognize return the result array, otherwise nullhref
     *
     */
    public function extractLinkParts($link): ?array
    {
        if (preg_match('/^(' . WN_CAMEL_CASE_EVOLVED . ')(?:\/(' . WN_CAMEL_CASE_EVOLVED . '))?(?:[?&]('
            . RFC3986_URI_CHARS . '))?$/u', $link, $linkParts)) {
            $tag = !empty($linkParts[1]) ? $linkParts[1] : null;
            $method = !empty($linkParts[2]) ? $linkParts[2] : null;
            $paramsStr = !empty($linkParts[3]) ? $linkParts[3] : null;
            parse_str($paramsStr, $params);
            return [
                'tag' => $tag,
                'method' => $method,
                'params' => $params
            ];
        } else {
            return null;
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

    public function Header()
    {
        return $this->Action($this->GetConfigValue('header_action'), 1);
    }

    public function Footer()
    {
        return $this->Action($this->GetConfigValue('footer_action'), 1);
    }

    // FORMS
    public function FormOpen($method = '', $tag = '', $formMethod = 'post', $class = '')
    {
        if ($method=='edit' || $method=='editiframe') {
            $result  = '<form id="ACEditor" name="ACEditor" enctype="multipart/form-data" action="'.$this->href($method, $tag).'" method="'.$formMethod.'"';
            $result .= !empty($class) ? ' class="'.$class.'"' : '';
            $result .= ">\n";
            if (isset($this->config['password_for_editing']) and !empty($this->config['password_for_editing'])
                and isset($_POST['password_for_editing'])) {
                $result .= '<input type="hidden" name="password_for_editing" value="'.$_POST['password_for_editing'].'" />'."\n";
            }
        } else {
            $result = '<form action="'.$this->href($method, $tag).'" method="'.$formMethod.'"';
            $result .= !empty($class) ? ' class="'.$class.'"' : '';
            $result .= ">\n";
        }

        if (!$this->config["rewrite_mode"]) {
            $result .= '<input type="hidden" name="wiki" value="'.$this->MiniHref($method, $tag).'" />'."\n";
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
        if (($days = $this->GetConfigValue("referrers_purge_time"))&& !$this->services->get(SecurityController::class)->isWikiHibernated()) {
            $this->Query('delete from ' . $this->config['table_prefix'] . "referrers where time < date_sub(now(), interval '" . mysqli_real_escape_string($this->dblink, $days) . "' day)");
        }
    }

    /**
     * Executes an "action" module and returns the generated output
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
     * @return string The output generated by the action.
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

        // match all attributes (key and value)
        // prepare an array for extract() to work with (in $this->IncludeBuffered())
        if (preg_match_all("/([a-zA-Z0-9_]*)=\"(.*)\"/U", $vars_temp, $matches)) {
            for ($a = 0; $a < count($matches[1]); $a ++) {
                $vars[$matches[1][$a]] = $matches[2][$a];
            }
        }

        if (!$forceLinkTracking) {
            $this->StopLinkTracking();
        }
        $result = $this->services->get(Performer::class)->run($action, 'action', $vars);
        $this->StartLinkTracking(); // shouldn't we restore the previous status ?
        return $result;
    }

    public function Method($method)
    {
        return $this->services->get(Performer::class)->run($method, 'handler', []);
    }

    public function Format($text, $formatter = 'wakka', $pageTag = '')
    {
        return $this->services->get(Performer::class)->run($formatter, 'formatter', compact('text'));
    }

    /**
     * Executes a file in a buffer, so we can work on the output variable $plugin_out_new before display
     *
     * This method is used by Performer Service
     * We need to run this method in YesWiki class, so the variable $this will be referencing YesWiki
     * in the included file
     *
     * @param ___file the file to execute (___ because as we use the extract function, we have to choose a name which
     * will be not used by users for an performable argument)
     * @param array vars the variables used as an execution context. 'plugin_output_new' represents the current output
     * (strange variable name, but it's used in everywhere, so let's keep it... !)
     * @return array the execution context variables updated by the execution (with 'plugin_output_new for the current output)
     */
    public function runFileInBuffer($___file, array $vars)
    {
        $this->parameter = &$vars;
        extract($this->parameter, EXTR_REFS);
        unset($vars);

        // the 'plugin_output_new' variable must be passed to $vars
        assert(isset($plugin_output_new));
        // add the alias with the property output, more convenient to use
        $this->output = &$plugin_output_new;

        ob_start();
        include($___file);
        $plugin_output_new .= ob_get_contents();
        ob_end_clean();

        // save the context variables into $updatedVars
        $updatedVars = get_defined_vars();
        unset($updatedVars['___file']);
        // add new variables added to $this->parameter in $updatedVars (already existing vars share the same ref)
        if (isset($this->parameter)) {
            $updatedVars = array_merge($updatedVars, $this->parameter);
        }
        unset($this->parameter);
        return $updatedVars;
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

    public function UserWantsComments()
    {
        if (! $user = $this->GetUser()) {
            return false;
        }
        return ($user['show_comments'] == 'Y');
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
     * Checks if a given user is administrator
     *
     * @param string $user
     *            The name of the user (defaults to the current user if not given)
     * @return boolean true iff the user is an administrator
     */
    public function UserIsAdmin($user = null)
    {
        return $this->UserIsInGroup(ADMIN_GROUP, $user, false);
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
                $acl = $this->GetTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_ACTIONS_PREFIX);
                $this->_actionsAclsCache[$module] = $acl;
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
            return true; // undefined ACL means everybody has access
        }
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

        $this->request = Request::createFromGlobals();

        // Is this a special page ?
        if ($tag === 'api') {
            $this->RunAPI();
        } else {
            $this->SetPage($this->LoadPage($tag, (isset($_REQUEST['time']) ? $_REQUEST['time'] : '')));
            $this->LogReferrer();

            echo $this->Method($this->method);

            // action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
            if (!empty($_SESSION['redirects'])) {
                unset($_SESSION['redirects']);
            }
        }
    }

    private function RunAPI()
    {
        // We must manually parse the body data for the PUT or PATCH methods
        // See https://www.php.net/manual/fr/features.file-upload.put-method.php
        // TODO properly use the Symfony HttpFoundation component to avoid this
        if (($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH')) {
            if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
                $response = new Response(_t('WIKI_IN_HIBERNATION'), Response::HTTP_UNAUTHORIZED);
                $response->send();
                exit();
            }
            if (empty($_POST)) {
                $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }

        $context = new RequestContext();
        $context->fromRequest($this->request);
        
        // Use query string as the path (part before '&')
        $extract = explode('&', $context->getQueryString());
        $path = $extract[0];
        if (strpos($path, "=") !== false) {
            if (!empty($this->method)) {
                if ($this->method === 'show' && $path === 'wiki=api') {
                    $path = 'api';
                } else {
                    $path = $this->tag.'/'.$this->method;
                    $newQuerytring = implode('&', $extract);
                }
            } else {
                $response = new Response(_t('ROUTE_BAD_CONFIGURED'), Response::HTTP_BAD_REQUEST);
                $response->send();
                exit();
            }
        } elseif (count($extract) > 1) {
            array_shift($extract);
            $newQuerytring = implode('&', $extract);
        }
        $context->setPathInfo('/' . $path);
        $context->setQueryString($newQuerytring ?? '');

        $matcher = new UrlMatcher($this->routes, $context);

        $controllerResolver = new YesWikiControllerResolver($this);
        $argumentResolver = new ArgumentResolver();

        try {
            // TODO put this elsewhere ?
            $attributes = $matcher->match($context->getPathInfo());
            if ($this->services->get(ApiService::class)->isAuthorized($attributes, $this->routes)) {
                $this->request->attributes->add($attributes);

                $controller = $controllerResolver->getController($this->request);
                $arguments = $argumentResolver->getArguments($this->request, $controller);

                $response = call_user_func_array($controller, $arguments);
            } else {
                $response = new Response('', Response::HTTP_UNAUTHORIZED);
            }
        } catch (ResourceNotFoundException $exception) {
            $response = new Response('', Response::HTTP_NOT_FOUND);
        } catch (HttpException $exception) {
            $response = new Response($exception->getMessage(), $exception->getStatusCode(), $exception->getHeaders());
        } catch (MethodNotAllowedException $exception) {
            $response = new Response('', Response::HTTP_METHOD_NOT_ALLOWED);
        }
        $response->send();
    }

    /**
     * @deprecated Use AssetsManager service instead
     */
    public function AddCSS($style)
    {
        return $this->services->get(AssetsManager::class)->AddCSS($style);
    }

    /**
     * @deprecated Use AssetsManager service instead
     */
    public function AddCSSFile($file, $conditionstart = '', $conditionend = '')
    {
        return $this->services->get(AssetsManager::class)->AddCSSFile($file, $conditionstart, $conditionend);
    }

    /**
     * @deprecated Use AssetsManager service instead
     */
    public function AddJavascript($script)
    {
        return $this->services->get(AssetsManager::class)->AddJavascript($script);
    }

    /**
     * @deprecated Use AssetsManager service instead
     */
    public function AddJavascriptFile($file, $first = false, $module = false)
    {
        return $this->services->get(AssetsManager::class)->AddJavascriptFile($file, $first, $module);
    }

    public function parse_size($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    // Drupal code under GPL2 cf. http://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size#25370978
    // Returns a file size limit in bytes based on the PHP upload_max_filesize
    // and post_max_size
    public function file_upload_max_size()
    {
        static $max_size = -1;

        if ($max_size < 0) {
            // Start with post_max_size.
            $max_size = $this->parse_size(ini_get('post_max_size'));

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = $this->parse_size(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }
        return $max_size;
    }

    /**
     * Load all extensions
     * @return void
     */
    public function loadExtensions()
    {
        $pluginsRoot = 'tools/';

        include_once 'includes/YesWikiPlugins.php';
        $objPlugins = new \YesWiki\Plugins($pluginsRoot);
        $objPlugins->getPlugins(true);
        $this->extensions = $objPlugins->getPluginsList();

        // TODO refactor as custom and actionsbuilder are not extensions
        foreach ($this->extensions as $pluginName => $pluginInfo) {
            $this->extensions[$pluginName] = $pluginsRoot . $pluginName . '/';
        }
        $this->extensions['custom'] = 'custom/'; // Will load custom/actions, custom/handlers etc...
        $this->extensions['actionsbuilder'] = 'docs/actions/'; // Will load langs inside docs/actions/lang

        // This is necessary for retrocompatibility reasons, as these variables are used by the extensions
        // TODO refactor all extensions to use the correct variable name
        // TODO remove this when the retrocompatibility is no longer necessary
        $wiki = $this;
        $page = $this->tag;
        $wakkaConfig = &$this->config;

        // TODO put elsewhere
        $fullDomain = parse_url($this->Href());
        $this->services->setParameter('host', $fullDomain['host']);
        $this->services->setParameter('max-upload-size', $this->file_upload_max_size());

        // Load all services
        foreach ($this->extensions as $k => $pluginBase) {
            $loader = new YamlFileLoader($this->services, new FileLocator($pluginBase));

            // Load the initialization file (constants and includes)
            if (file_exists($pluginBase . 'wiki.php')) {
                include $pluginBase . 'wiki.php';
            }

            if (file_exists($pluginBase . 'vendor/autoload.php')) {
                include $pluginBase . 'vendor/autoload.php';
            }

            // TODO load the user-defined configs after this loop
            if (file_exists($pluginBase . 'config.yaml')) {
                $loader->load('config.yaml');
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
        }

        // set all wakka configs as container's parameters
        // overwrite the parameters if they were already defined in the extensions's config (for arrays, recursively
        // merge them)
        foreach ($this->config as $key => $value) {
            if (is_array($value) && $this->services->hasParameter($key) && is_array($this->services->getParameter($key))) {
                // merge recursively the arrays to let overwrite only some values
                $mergedArray = array_replace_recursive($this->services->getParameter($key), $value);
                $this->services->setParameter($key, $mergedArray);
            } else {
                $this->services->setParameter($key, $value);
            }
        }

        // Now we have loaded all the services, compile them
        // See https://symfony.com/doc/current/components/dependency_injection/compilation.html
        $this->services->compile();

        // set to wakka config the same parameters than the merged service's parameter bag
        // need to be executed after $this->services->compile() because the %paramName% are resolved there
        $this->config = $this->services->getParameterBag()->all();

        $this->dblink = $this->services->get(DbService::class)->getLink();

        // This must be done after service initialization, as it uses services
        loadpreferredI18n($this, $this->tag);

        $metadata = $this->services->get(PageManager::class)->getMetadata($this->tag);

        if (isset($metadata['lang'])) {
            $this->config['lang'] = $metadata['lang'];
        } elseif (!isset($this->config['lang'])) {
            $this->config['lang'] = 'fr';
        }

        // TODO Don't put templates in configs
        // TODO avoid modifying the $wakkaConfig array
        $this->config['templates'] = $this->services->get(ThemeManager::class)->loadTemplates($metadata);
    }

    /**
     * Shortcut to be used in the old plain PHP Actions and Handlers (instead of using SquelettePhp class)
     */
    public function render($templatePath, $data)
    {
        try {
            return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error rendering ' . $templatePath . ': '.  $e->getMessage(). '</div>'."\n";
        }
    }

    /*
     * RETRO-COMPATIBILITY
     */

    /**
     * @deprecated Use DbService::query instead
     */
    public function Query($query)
    {
        return $this->services->get(DbService::class)->query($query);
    }

    /**
     * @deprecated Use DbService::loadSingle instead
     */
    public function LoadSingle($query)
    {
        return $this->services->get(DbService::class)->loadSingle($query);
    }

    /**
     * @deprecated Use DbService::loadAll instead
     */
    public function LoadAll($query)
    {
        return $this->services->get(DbService::class)->loadAll($query);
    }

    /**
     * @deprecated Use PageManager::getOne instead
     */
    public function LoadPage($tag, $time = "", $cache = 1)
    {
        return $this->services->get(PageManager::class)->getOne($tag, $time, $cache);
    }

    /**
     * @deprecated Use PageManager::getCached instead
     */
    public function GetCachedPage($tag)
    {
        return $this->services->get(PageManager::class)->getCached($tag);
    }

    /**
     * @deprecated Use PageManager::cache instead
     */
    public function CachePage($page, $pageTag = null)
    {
        return $this->services->get(PageManager::class)->cache($page, $pageTag);
    }

    /**
     * @deprecated Use PageManager::getById instead
     */
    public function LoadPageById($id)
    {
        return $this->services->get(PageManager::class)->getById($id);
    }

    /**
     * @deprecated Use PageManager::getLinkingTo instead
     */
    public function LoadPagesLinkingTo($tag)
    {
        return $this->services->get(PageManager::class)->getLinkingTo($tag);
    }

    /**
     * @deprecated Use PageManager::getRecentlyChanged instead
     */
    public function LoadRecentlyChanged($limit = 50, $minDate = '')
    {
        return $this->services->get(PageManager::class)->getRecentlyChanged($limit, $minDate);
    }

    /**
     * @deprecated Use PageManager::getAll instead
     */
    public function LoadAllPages()
    {
        return $this->services->get(PageManager::class)->getAll();
    }

    /**
     * @deprecated Use PageManager::getCreateTime instead
     */
    public function GetPageCreateTime($pageTag)
    {
        return $this->services->get(PageManager::class)->getCreateTime($pageTag);
    }

    /**
     * @deprecated Use PageManager::searchFullText instead
     */
    public function FullTextSearch($phrase)
    {
        return $this->services->get(PageManager::class)->searchFullText($phrase);
    }

    /**
     * @deprecated Use PageManager::getWanted instead
     */
    public function LoadWantedPages()
    {
        return $this->services->get(PageManager::class)->getWanted();
    }

    /**
     * @deprecated Use PageManager::getOrphaned instead
     */
    public function LoadOrphanedPages()
    {
        return $this->services->get(PageManager::class)->getOrphaned();
    }

    /**
     * @deprecated Use PageManager::isOrphaned instead
     */
    public function IsOrphanedPage($tag)
    {
        return $this->services->get(PageManager::class)->isOrphaned($tag);
    }

    /**
     * @deprecated Use PageManager::deletedOrphaned instead
     */
    public function DeleteOrphanedPage($tag)
    {
        return $this->services->get(PageManager::class)->deleteOrphaned($tag);
    }

    /**
     * @deprecated Use PageManager::save instead
     */
    public function SavePage($tag, $body, $comment_on = "", $bypass_acls = false)
    {
        return $this->services->get(PageManager::class)->save($tag, $body, $comment_on, $bypass_acls);
    }

    /**
     * @deprecated Use PageManager::getOwner instead
     */
    public function GetPageOwner($tag = "", $time = "")
    {
        return $this->services->get(PageManager::class)->getOwner($tag, $time);
    }

    /**
     * @deprecated Use PageManager::save instead
     */
    public function SetPageOwner($tag, $user)
    {
        return $this->services->get(PageManager::class)->setOwner($tag, $user);
    }

    /**
     * @deprecated Use PageManager::getMetadata instead
     */
    public function GetMetaDatas($tag)
    {
        return $this->services->get(PageManager::class)->getMetadata($tag);
    }

    /**
     * @deprecated Use PageManager::setMetadata instead
     */
    public function SaveMetaDatas($tag, $metadata)
    {
        return $this->services->get(PageManager::class)->setMetadata($tag, $metadata);
    }

    /**
     * @deprecated Use TagsManager::deleteAll instead
     */
    public function DeleteAllTags($page)
    {
        return $this->services->get(TagsManager::class)->deleteAll($page);
    }

    /**
     * @deprecated Use TagsManager::save instead
     */
    public function SaveTags($page, $liste_tags)
    {
        return $this->services->get(TagsManager::class)->save($page, $liste_tags);
    }

    /**
     * @deprecated Use TagsManager::getAll instead
     */
    public function GetAllTags($page = '')
    {
        return $this->services->get(TagsManager::class)->getAll($page);
    }

    /**
     * @deprecated Use TagsManager::getPagesByTags instead
     */
    public function PageList($tags = '', $type = '', $nb = '', $tri = '')
    {
        return $this->services->get(TagsManager::class)->getPagesByTags($tags, $type, $nb, $tri);
    }

    /**
     * @deprecated Use TripleStore::getOne instead
     */
    public function GetTripleValue($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->getOne($resource, $property, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use TripleStore::getMatching instead
     */
    public function GetMatchingTriples($resource = null, $property = null, $value = null, $res_op = 'LIKE', $prop_op = '=')
    {
        return $this->services->get(TripleStore::class)->getMatching($resource, $property, $value, $res_op, $prop_op);
    }

    /**
     * @deprecated Use TripleStore::getAll instead
     */
    public function GetAllTriplesValues($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->getAll($resource, $property, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use TripleStore::exist instead
     */
    public function TripleExists($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->exist($resource, $property, $value, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use TripleStore::create instead
     */
    public function InsertTriple($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->create($resource, $property, $value, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use TripleStore::update instead
     */
    public function UpdateTriple($resource, $property, $oldvalue, $newvalue, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->update($resource, $property, $oldvalue, $newvalue, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use TripleStore::delete instead
     */
    public function DeleteTriple($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        return $this->services->get(TripleStore::class)->delete($resource, $property, $value, $re_prefix, $prop_prefix);
    }

    /**
     * @deprecated Use UserManager::getOneByName instead
     */
    public function LoadUser($name, $password = 0)
    {
        return $this->services->get(UserManager::class)->getOneByName($name, $password);
    }

    /**
     * @deprecated Use UserManager::getOneByEmail instead
     */
    public function loadUserByEmail($mail, $password = 0)
    {
        return $this->services->get(UserManager::class)->getOneByEmail($mail, $password);
    }

    /**
     * @deprecated Use UserManager::getAll instead
     */
    public function LoadUsers()
    {
        return $this->services->get(UserManager::class)->getAll();
    }

    /**
     * @deprecated Use UserManager::getLoggedUser instead
     */
    public function GetUser()
    {
        return $this->services->get(UserManager::class)->getLoggedUser();
    }

    /**
     * @deprecated Use UserManager::getLoggedUserName instead
     */
    public function GetUserName()
    {
        return $this->services->get(UserManager::class)->getLoggedUserName();
    }

    /**
     * @deprecated Use UserManager::login instead
     */
    public function SetUser($user, $remember = 0)
    {
        return $this->services->get(UserManager::class)->login($user, $remember);
    }

    /**
     * @deprecated Use UserManager::logout instead
     */
    public function LogoutUser()
    {
        return $this->services->get(UserManager::class)->logout();
    }

    /**
     * @deprecated Use AclService::load
     */
    public function LoadAcl($tag, $privilege, $useDefaults = true)
    {
        return $this->services->get(AclService::class)->load($tag, $privilege, $useDefaults);
    }

    /**
     * @deprecated Use AclService::save
     */
    public function SaveAcl($tag, $privilege, $list, $appendAcl = false)
    {
        return $this->services->get(AclService::class)->save($tag, $privilege, $list, $appendAcl);
    }

    /**
     * @deprecated Use AclService::delete
     */
    public function DeleteAcl($tag, $privileges = ['read','write','comment'])
    {
        return $this->services->get(AclService::class)->delete($tag, $privileges);
    }

    /**
     * @deprecated Use AclService::hasAccess
     */
    public function HasAccess($privilege, $tag = '', $user = '')
    {
        return $this->services->get(AclService::class)->hasAccess($privilege, $tag, $user);
    }

    /**
     * @deprecated Use AclService::check
     */
    public function CheckACL($acl, $user = null, $admincheck = true, $tag = '', $mode = '')
    {
        return $this->services->get(AclService::class)->check($acl, $user, $admincheck, $tag, $mode);
    }

    /**
     * @deprecated Use LinkTracker::start
     */
    public function StartLinkTracking()
    {
        return $this->services->get(LinkTracker::class)->start();
    }

    /**
     * @deprecated Use LinkTracker::stop
     */
    public function StopLinkTracking()
    {
        return $this->services->get(LinkTracker::class)->stop();
    }

    /**
     * @deprecated Use LinkTracker::track
     */
    public function LinkTracking($newState = null)
    {
        return $this->services->get(LinkTracker::class)->track($newState);
    }

    /**
     * @deprecated Use LinkTracker::add
     */
    public function TrackLinkTo($tag)
    {
        return $this->services->get(LinkTracker::class)->add($tag);
    }

    /**
     * @deprecated Use LinkTracker::getAll
     */
    public function GetLinkTable()
    {
        return $this->services->get(LinkTracker::class)->getAll();
    }

    /**
     * @deprecated Use LinkTracker::persist
     */
    public function WriteLinkTable()
    {
        return $this->services->get(LinkTracker::class)->persist();
    }

    /**
     * @deprecated Use LinkTracker::getAll
     */
    public function ClearLinkTable()
    {
        return $this->services->get(LinkTracker::class)->clear();
    }
}

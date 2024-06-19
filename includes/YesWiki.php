<?php

namespace YesWiki;

require_once 'includes/constants.php';
require_once 'includes/urlutils.inc.php';
require_once 'includes/i18n.inc.php';
require_once 'includes/YesWikiInit.php';
require_once 'includes/YesWikiPerformable.php';
require_once 'includes/objects/YesWikiAction.php';
require_once 'includes/objects/YesWikiHandler.php';
require_once 'includes/objects/YesWikiFormatter.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ApiService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiControllerResolver;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;

class Wiki
{
    public $config;
    public $dblink;
    public $metadatas; // todo use PageManager or method instead of public var
    public $method;
    public $page;
    public $tag;
    public $parameter = [];
    public $request;
    // current output used for actions/handlers/formatters
    public $output;
    public $interWiki = [];
    public $VERSION;
    public $CookiePath = '/';
    public $inclusions = [];
    public $extensions = [];
    public $routes = [];
    public $user; // depreciated TODO remove it for ectoplasme : replaced by userManager
    public $services;
    public $actionObjects = []; // keep track of actions performed
    public $pageCacheFormatted = [];
    public $_groupsCache = [];
    public $_actionsAclsCache = [];

    /**
     * Constructor.
     */
    public function __construct($config = [])
    {
        $init = new \YesWiki\Init($config);
        $this->config = $init->config;
        $this->CookiePath = $init->initCookies();
        $this->tag = $init->page;
        $this->method = $init->method;

        $this->services = $init->initCoreServices($this);
        $this->loadExtensions();
        $this->routes = $init->initRoutes($this);
    }

    // MISC
    public function GetMicroTime()
    {
        list($usec, $sec) = explode(' ', microtime());

        return (float)$usec + (float)$sec;
    }

    // VARIABLES
    public function GetPageTag()
    {
        return $this->tag;
    }

    public function GetPageTime()
    {
        return empty($this->page['time']) ? '' : $this->page['time'];
    }

    public function GetMethod()
    {
        if ($this->method == 'iframe') {
            return 'show';
        } elseif ($this->method == 'editiframe') {
            return 'edit';
        } else {
            return $this->method;
        }
    }

    public function GetConfigValue($name, $default = null)
    {
        return isset($this->config[$name])
            ? is_array($this->config[$name]) ? $this->config[$name] : trim($this->config[$name])
            : ($default != null ? $default : '');
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

    public function isCli(): bool
    {
        return in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true);
    }

    // inclusions
    /**
     * Enregistre une nouvelle inclusion dans la pile d'inclusions.
     *
     * @param string $pageTag
     *                        Le nom de la page qui va etre inclue
     *
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
     *                null s'il n'y a plus d'inclusion dans la pile.
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
     *
     * @return string le nom (tag) de la page (en minuscules)
     *                false si la pile est vide
     */
    public function GetCurrentInclusion()
    {
        return isset($this->inclusions[0]) ? $this->inclusions[0] : false;
    }

    /**
     * Verifie si on est a l'interieur d'une inclusion par $pageTag (sans tenir compte de la casse).
     *
     * @param string $pageTag
     *                        Le nom de la page a verifier
     *
     * @return bool True si on est a l'interieur d'une inclusion par $pageTag (false sinon)
     */
    public function IsIncludedBy($pageTag)
    {
        return in_array(strtolower($pageTag), $this->inclusions);
    }

    /**
     * @return array la pile d'inclusions
     *               L'element 0 sera la derniere inclusion, l'element 1 sera son parent et ainsi de suite
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
     *
     * @return array L'ancienne pile d'inclusions, avec les noms des pages en minuscules
     */
    public function SetInclusions($pile = [])
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
     * Ajoute du contenu a la fin d'une page.
     *
     * @param string $content
     *                            Contenu a ajouter a la page
     * @param string $page
     *                            Nom de la page
     * @param bool   $bypass_acls
     *                            Bouleen pour savoir s'il faut bypasser les ACLs
     *
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
            $body = empty($result['body']) ? '' : $result['body'];
            // -- Ajout du contenu a la fin de la page
            $body .= $content;

            // -- Sauvegarde de la page
            // TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
            $this->SavePage($page, $body, '', $bypass_acls);

            // now we render it internally so we can write the updated link table.
            $page = $this->services->get(PageManager::class)->getOne($page);
            $this->services->get(LinkTracker::class)->registerLinks($page, false, true);

            // Retourne 0 seulement si tout c'est bien passe
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * LogAdministrativeAction($user, $content, $page = "").
     *
     * @param string $user
     *                        Utilisateur
     * @param string $content
     *                        Contenu de l'enregistrement
     * @param string $page
     *                        Page de log
     *
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     */
    public function LogAdministrativeAction($user, $content, $page = '')
    {
        $order = [
            "\r\n",
            "\n",
            "\r",
        ];
        $replace = '\\n';
        $content = str_replace($order, $replace, $content);
        $contentToAppend = "\n" . date('Y-m-d H:i:s') . ' . . . . ' . $user . ' . . . . ' . $content . "\n";
        $tag = $page ? $page : 'LogDesActionsAdministratives' . date('Ymd');
        $result = $this->AppendContentToPage($contentToAppend, $tag, true);
        if (empty($page) && $result === 0) {
            try {
                // keep only 10 revisions of this page
                $pageManager = $this->services->get(PageManager::class);
                $dbService = $this->services->get(DbService::class);
                $revisions = $pageManager->getRevisions($tag);
                if (!empty($revisions) && count($revisions) > 10) {
                    $idsToDelete = array_map(
                        function ($data) {
                            return $data['id'];
                        },
                        array_slice($revisions, 10)
                    );

                    $formattedIds = implode(
                        ',',
                        array_map(
                            function ($id) use ($dbService) {
                                return $dbService->escape($id);
                            },
                            $idsToDelete
                        )
                    );

                    // there are some versions to remove from DB
                    // let's build one big request, that's better...
                    $sql = <<<SQL
                    DELETE FROM {$dbService->prefixTable('pages')} WHERE `id` IN ($formattedIds);
                    SQL;

                    // ... and send it !
                    $dbService->query($sql);
                }
            } catch (Throwable $th) {
            }
        }

        return $result;
    }

    /**
     * Make the purge of page versions that are older than the last version older than "pages_purge_time"
     * This method permits to allways keep a not latest version that is older than that period.
     */
    public function PurgePages()
    {
        if (($days = $this->GetConfigValue('pages_purge_time')) && !$this->services->get(SecurityController::class)->isWikiHibernated()) {
            // is purge active ?
            // let's search which pages versions we have to remove
            // this is necessary beacause even MySQL does not handel multi-tables deletes before version 4.0
            $wnPages = $this->GetConfigValue('table_prefix') . 'pages';
            $daysFormatted = mysqli_real_escape_string($this->dblink, $days);
            $sql = <<<SQL
            SELECT DISTINCT a.id FROM $wnPages a,$wnPages b
                WHERE a.latest = 'N'
                    AND b.latest = 'N'
                    AND a.time < date_sub(now(), INTERVAL '$daysFormatted' DAY)
                    AND a.tag = b.tag
                    AND a.time < b.time
                    AND b.time < date_sub(now(), INTERVAL '$daysFormatted' DAY);
            SQL;
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
        $url = preg_replace(['/\/\?$/', '/\/$/'], '', $url[0]);

        return $url;
    }

    public function Redirect($url)
    {
        header("Location: $url");
        $this->exit();
    }

    public function exit(string $message = '')
    {
        if ($this->isCli()) {
            throw new ExitException($message);
        } else {
            exit($message);
        }
    }

    // returns just PageName[/method].
    public function MiniHref($method = null, $tag = null)
    {
        if (!$tag = trim($tag)) {
            $tag = $this->tag;
        }

        return $tag . ($method ? '/' . $method : '');
    }

    // returns the full url to a page/method.
    public function Href($method = null, $tag = null, $params = null, $htmlspchars = true)
    {
        if ($tag == null || !$tag = trim($tag)) {
            $tag = $this->tag;
        }
        $href = $this->config['base_url'] . $this->MiniHref($method, $tag);
        if ($params) {
            if (is_array($params)) {
                $paramsArray = [];
                foreach ($params as $key => $value) {
                    if (!empty($value) || in_array($value, [0, '0', ''], true)) {
                        $paramsArray[] = "$key=" . urlencode($value);
                    }
                }
                if (count($paramsArray) > 0) {
                    $params = implode(($htmlspchars ? '&amp;' : '&'), $paramsArray);
                } else {
                    $params = '';
                }
            }
            $href .= ($this->config['rewrite_mode'] ? '?' : ($htmlspchars ? '&amp;' : '&')) . $params;
        }
        if (isset($_GET['lang']) && $_GET['lang'] != '') {
            $href .= '&lang=' . $GLOBALS['prefered_language'];
        }

        return $href;
    }

    /**
     * Handle string that could be a valid url, a yeswiki short link, or anything else (anchor, relative url..).
     *
     * if a yeswiki short link if discovered, it will be completed in order to have a real url
     *
     * @param string $link the link to parse
     *
     * @return string url
     */
    public function generateLink($link): ?string
    {
        if (empty($link)) {
            return null;
        } else {
            $linkParts = $this->extractLinkParts($link);
            if ($linkParts) {
                return $this->Href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
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
     *
     * @param $link the link to parse
     *
     * @return array|null if the link is recognize return the result array, otherwise nullhref
     */
    public function extractLinkParts($link): ?array
    {
        if (preg_match('/^(' . WN_CAMEL_CASE_EVOLVED . ')(?:\/(' . WN_CAMEL_CASE_EVOLVED . '))?(?:[?&]('
            . RFC3986_URI_CHARS . '))?$/u', $link, $linkParts)) {
            $tag = !empty($linkParts[1]) ? $linkParts[1] : null;
            $method = !empty($linkParts[2]) ? $linkParts[2] : null;
            $paramsStr = !empty($linkParts[3]) ? $linkParts[3] : null;
            $params = [];
            if (is_string($paramsStr)) {
                parse_str($paramsStr, $params);
            }

            return [
                'tag' => $tag,
                'method' => $method,
                'params' => $params,
            ];
        } else {
            return null;
        }
    }

    /**
     * @deprecated Use LinkTo instead
     */
    public function ComposeLinkToPage($tag, $method = '', $text = '', $track = 1)
    {
        return $this->LinkTo($tag, $text, ['method' => $method, 'track' => $track]);
    }

    /**
     * @deprecated Use LinkTo instead
     */
    public function Link($tag, $method = null, $params = null, $text = null, $track = 1, $forcedLink = false)
    {
        return $this->LinkTo($tag, $text, [
            'method' => $method,
            'params' => $params,
            'track' => $track,
            'class' => $forcedLink ? 'forced-link' : '', // Cannot find any use of this forced-link...
        ]);
    }

    /**
     * Create an HTML link.
     *
     * LinkTo("WikiPage")
     * LinkTo("WikiPage", "My page", ["track" => false])
     * LinkTo("WikiPage", "", ["method" => "xml"])
     * LinkTo("WikiPage/edit?params=2", "ma page")
     * LinkTo("https://test.fr", "mon lien", ["class" => "yeah"])
     *
     * @param string $link    $url, or wiki $tag
     * @param string $text
     * @param array  $options Array of HTML options. You can also provide 'track' and 'method'
     *
     * @return string HTML link
     */
    public function LinkTo($link, $text = '', $options = [])
    {
        if (!$text) {
            $text = $link;
        }

        // YesWiki pages links, like "HomePage" or "HomePage/xml"
        if ($wikiLink = $this->extractLinkParts($link)) {
            $tag = $wikiLink['tag'];
            $method = $options['method'] ?? $wikiLink['method'];
            $params = $options['params'] ?? $wikiLink['params'] ?? [];

            // Handle missing Tag
            if ((empty($method) || $method == 'show') && !$this->LoadPage($tag)) {
                $params = array_merge($params, $this->ParamsForNewPageLink());
                $method = 'edit';
                $options['data-missing-tag'] = true;
            }

            // Tag and Method to be kept as HTML attributes
            $options['data-tag'] = $tag;
            $options['data-method'] = $method ?? 'show';
            unset($options['method']);
            unset($options['params']);

            // Trackable
            if (!empty($options['track']) && $options['track']) {
                $this->services->get(LinkTracker::class)->add(explode('?', $tag)[0]);
                $options['data-tracked'] = true;
            }
            unset($options['track']);

            // General URL
            $link = $this->Href($method, $tag, $params, false);
        } elseif ((!isset($options['data-iframe']) ||
                strval($options['data-iframe']) != '0') &&
            !empty($options['class']) &&
            is_string($options['class']) &&
            preg_match('/(^|\s)modalbox($|\s)/', $options['class'])
        ) {
            // use iframe for external links in modalbox except if `data-iframe=0`
            $options['data-iframe'] = '1';
            if (!isset($options['title']) && !empty($text)) {
                // set a title because it is beautiful
                $options['title'] = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
            }
        }

        // Email addresses
        if (preg_match("/^[\w.-]+\@[\w.-]+$/", $link)) {
            $link = 'mailto:' . $link;
        }

        // Options to HTML attributes
        $stringAttrs = implode(
            ' ',
            array_map(
                function ($key) use ($options) {
                    $value = $options[$key];
                    $encodedValue = is_string($value)
                        ? $value
                        : json_encode($value);

                    return "$key=\"$encodedValue\"";
                },
                array_keys($options)
            )
        );

        // Block script schemes (see RFC 3986 about schemes)
        $link = htmlspecialchars($link, ENT_COMPAT, YW_CHARSET);
        $text = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);

        // Generate HTML
        return <<<HTML
        <a href="$link" $stringAttrs>$text</a>
        HTML;
    }

    public function ParamsForNewPageLink()
    {
        $result = ['newpage' => 1];

        // Config from current page
        $config = $this->config;
        $fromConfig = [
            'theme' => 'favorite_theme',
            'squelette' => 'favorite_squelette',
            'style' => 'favorite_style',
            'bgimg' => 'favorite_background_image',
        ];
        foreach ($fromConfig as $param => $configKey) {
            if (!empty($config[$configKey])) {
                $result[$param] = $config[$configKey];
            }
        }

        // Metadata from current page
        $currentPageTag = $this->GetPageTag();
        $pageMetadatas = empty($currentPageTag) ? [] : $this->GetMetaDatas($currentPageTag);
        foreach (\YesWiki\Core\Service\ThemeManager::SPECIAL_METADATA as $metadata) {
            if (!empty($pageMetadatas[$metadata])) {
                $result[$metadata] = $pageMetadatas[$metadata];
            }
        }

        return $result;
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
        return $this->render('@core/_form-open.twig', compact(['method', 'tag', 'formMethod', 'class']));
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

    // REFERRERS
    public function LogReferrer($tag = '', $referrer = '')
    {
        // fill values
        if (!$tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }

        if (!$referrer = trim($referrer) and isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        // check if it's coming from another site
        if ($referrer && !preg_match('/^' . preg_quote($this->GetConfigValue('base_url'), '/') . '/', $referrer)) {
            // avoid XSS (with urls like "javascript:alert()" and co)
            // by forcing http/https prefix
            // NB.: this does NOT exempt to htmlspecialchars() the collected URIs !
            if (!preg_match('`^https?://`', $referrer)) {
                return;
            }

            $this->Query('insert into ' . $this->config['table_prefix'] . 'referrers set ' . "page_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "', " . "referrer = '" . mysqli_real_escape_string($this->dblink, $referrer) . "', " . 'time = now()');
        }
    }

    public function LoadReferrers($tag = '')
    {
        return $this->LoadAll('select referrer, count(referrer) as num from ' . $this->config['table_prefix'] . 'referrers ' . ($tag = trim($tag) ? "where page_tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "'" : '') . ' group by referrer order by num desc');
    }

    public function PurgeReferrers()
    {
        if (($days = $this->GetConfigValue('referrers_purge_time')) && !$this->services->get(SecurityController::class)->isWikiHibernated()) {
            $this->Query('delete from ' . $this->config['table_prefix'] . "referrers where time < date_sub(now(), interval '" . mysqli_real_escape_string($this->dblink, $days) . "' day)");
        }
    }

    /**
     * Executes an "action" module and returns the generated output.
     *
     * @param string $action
     *                                  The name of the action and its eventual parameters,
     *                                  as it appears in the page between "{{" and "}}"
     * @param bool   $forceLinkTracking
     *                                  By default, the link tracking will be disabled
     *                                  during the call of an action. Set this value to <code>true</code> to allow it.
     * @param array  $vars
     *                                  An array of additionnal parameters to give to the action, in the form
     *                                  array( 'param' => 'value').
     *                                  This allows you to call Action() internally, setting $action to the name of the action
     *                                  you want to call and it's parameters in an array, wich is more efficient than
     *                                  the pattern-matching algorithm used to extract the parameters from $action.
     *
     * @return string the output generated by the action
     */
    public function Action($action, $forceLinkTracking = 0, $vars = [])
    {
        $cmd = trim($action);
        $cmd = str_replace("\n", ' ', $cmd);
        // extract $action and $vars_temp ("raw" attributes)
        if (!preg_match("/^([a-zA-Z0-9_-]+)\/?(.*)$/", $cmd, $matches)) {
            return '<div class="alert alert-danger">' . _t('INVALID_ACTION') . ' &quot;' . htmlspecialchars($cmd, ENT_COMPAT, YW_CHARSET) . '&quot;</div>' . "\n";
        }
        list(, $action, $vars_temp) = $matches;

        // match all attributes (key and value)
        // prepare an array for extract() to work with (in $this->IncludeBuffered())
        if (preg_match_all('/([a-zA-Z0-9_]*)="(.*)"/U', $vars_temp, $matches)) {
            for ($a = 0; $a < count($matches[1]); $a++) {
                $vars[$matches[1][$a]] = $matches[2][$a];
            }
        }

        if (!$forceLinkTracking) {
            $this->StopLinkTracking();
        }
        // keep track of actions and their parameters
        array_push($this->actionObjects, [
            'action' => $action,
            'vars' => $vars,
        ]);
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
     * Executes a file in a buffer, so we can work on the output variable $plugin_out_new before display.
     *
     * This method is used by Performer Service
     * We need to run this method in YesWiki class, so the variable $this will be referencing YesWiki
     * in the included file
     *
     * @param ___file the file to execute (___ because as we use the extract function, we have to choose a name which
     * will be not used by users for an performable argument)
     * @param array vars the variables used as an execution context. 'plugin_output_new' represents the current output
     * (strange variable name, but it's used in everywhere, so let's keep it... !)
     *
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
        try {
            include $___file;
        } catch (Throwable $throwableToThrow) {
            // $throwableToThrow is thrown at the of the method because ob_end_clean() and get_defined_vars()
            // could change the way to catch Throwable at higher levels
            // for pre actions
        }
        $plugin_output_new .= ob_get_contents();
        ob_end_clean();

        // save the context variables into $updatedVars
        $updatedVars = get_defined_vars();
        unset($updatedVars['___file']);
        unset($updatedVars['throwableToThrow']);
        // add new variables added to $this->parameter in $updatedVars (already existing vars share the same ref)
        if (isset($this->parameter)) {
            $updatedVars = array_merge($updatedVars, $this->parameter);
        }
        unset($this->parameter);
        if (isset($throwableToThrow)) {
            throw $throwableToThrow;
        }

        return $updatedVars;
    }

    /**
     * Ajout d'un parametre.
     *
     * @param string $parameter nom du parametre
     * @param mixed  $value     valeur du parametre
     *
     * @return void
     */
    public function setParameter($parameter, $value)
    {
        $this->parameter[$parameter] = $value;
    }

    public function GetParameter($parameter, $default = '')
    {
        return isset($this->parameter[$parameter]) ? $this->parameter[$parameter] : $default;
    }

    // COMMENTS
    /**
     * Charge les derniers commentaires de toutes les pages.
     *
     * @param int $limit
     *                   Nombre de commentaires charges.
     *                   0 par d?faut (ie tous les commentaires).
     *
     * @return array tableau contenant chaque commentaire et ses
     *               proprietes associees
     *
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
        return $this->LoadAll('select * from ' . $this->config['table_prefix'] . 'pages ' . 'where comment_on != "" ' . "and latest = 'Y' " . 'order by time desc ' . $lim);
    }

    public function LoadRecentlyCommented($limit = 50)
    {
        $pages = [];

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
                    $num++;
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
        if (!$user = $this->GetUser()) {
            return false;
        }

        return $user['show_comments'] == 'Y';
    }

    // ACCESS CONTROL
    // returns true if logged in user is owner of current page, or page specified in $tag
    public function UserIsOwner($tag = '')
    {
        // check if user is logged in
        if (!$this->GetUser()) {
            return false;
        }

        // set default tag
        if (!$tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }

        // check if user is owner
        return $this->GetPageOwner($tag) == $this->GetUserName();
    }

    /**
     * @param string $group
     *                      The name of a group
     *
     * @return string the ACL associated with the group $gname
     *
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
     * (this method expects that groups that are already defined are not themselves defined recursively...).
     *
     * @param string $gname
     *                      The name of the group
     * @param string $acl
     *                      The new acl for that group
     *
     * @return bool True if the new acl defines the group recursively
     */
    public function MakesGroupRecursive($gname, $acl, $origin = null, $checked = [])
    {
        $gname = strtolower(trim($gname));
        if ($origin === null) {
            $origin = $gname;
        } elseif ($gname === $origin) {
            return true;
        }
        $acl = str_replace(["\r\n", "\r"], "\n", $acl);
        foreach (explode("\n", $acl) as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            if ($line[0] == '!') {
                $line = substr($line, 1);
            }
            if (!$line) {
                continue;
            }

            if ($line[0] == '@') {
                $line = substr($line, 1);
                if (!in_array($line, $checked)) {
                    if ($this->MakesGroupRecursive($line, $this->GetGroupACL($line) ?? '', $origin, $checked)) {
                        return true;
                    }
                }
            }
        }
        $checked[] = $gname;

        return false;
    }

    /**
     * Sets a new ACL to a given group.
     *
     * @param string $gname
     *                      The name of a group
     * @param string $acl
     *                      The new ACL to associate with the group $gname
     *
     * @return int 0 if successful, a triple error code or a specific error code:
     *             1000 if the new value would define the group recursively
     *             1001 if $gname is not named with alphanumeric chars
     *
     * @see GetGroupACL
     */
    public function SetGroupACL($gname, $acl)
    {
        if (preg_match('/[^A-Za-z0-9]/', $gname)) {
            return 1001;
        }
        $old = $this->GetGroupACL($gname);
        // we get rid of lost spaces before saving to db
        $acl = str_replace(["\r\n", "\r"], "\n", $acl);
        $acls = explode("\n", $acl);
        $acls = array_map('trim', $acls);
        $acl = implode("\n", $acls);
        if ($this->MakesGroupRecursive($gname, $acl)) {
            return 1000;
        }
        $this->_groupsCache[$gname] = $acl;
        if ($old === null) {
            return $this->InsertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        } elseif (strcasecmp($old, $acl) === 0 && strcmp($old, $acl) !== 0) {
            // possible error when directly updating triple
            if ($this->DeleteTriple($gname, WIKINI_VOC_ACLS, $old, GROUP_PREFIX)) {
                return $this->InsertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
            } else {
                return $this->UpdateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
            }
        } else {
            return $this->UpdateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
        }
    }

    /**
     * @return array The list of all group names
     */
    public function GetGroupsList()
    {
        $res = $this->GetMatchingTriples(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI);
        $prefix_len = strlen(GROUP_PREFIX);
        $list = [];
        foreach ($res as $line) {
            $list[] = substr($line['resource'], $prefix_len);
        }

        return $list;
    }

    /**
     * Checks if a given user is administrator.
     *
     * @param string $user
     *                     The name of the user (defaults to the current user if not given)
     *
     * @return bool true iff the user is an administrator
     */
    public function UserIsAdmin($user = null)
    {
        return $this->services->get(UserManager::class)->isInGroup(ADMIN_GROUP, $user, false);
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
     *                            The name of the module
     * @param string $module_type
     *                            The type of module: 'action' or 'handler'
     *
     * @return string the ACL string  for the given module or "*" if not found
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
     * Sets the $acl for a given $module.
     *
     * @param string $module
     *                            The name of the module
     * @param string $module_type
     *                            The type of module ('action' or 'handler')
     * @param string $acl
     *                            The new ACL for that module
     *
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
     * Checks if a $user satisfies the ACL to access a certain $module.
     *
     * @param string $module
     *                            The name of the module to access
     * @param string $module_type
     *                            The type of the module ('action' or 'handler')
     * @param string $user
     *                            The name of the user. By default
     *                            the current remote user.
     *
     * @return bool true if the $user has access to the given $module, false otherwise
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
        if (!(intval($this->GetMicroTime()) % 9)) {
            $this->Maintenance();
        }

        $this->ReadInterWikiConfig();

        // do our stuff!
        if ($tag == '') {
            $tag = $this->tag;
        }

        if (!$this->method = trim($method)) {
            $this->method = 'show';
        }

        if (!$this->tag = trim($tag)) {
            $this->Redirect($this->href('', $this->config['root_page']));
        }

        $this->services->get(AuthController::class)->connectUser();

        $this->request = Request::createFromGlobals();

        // Is this a special page ?
        if (in_array($tag, ['api', 'doc'])) {
            $this->RunSpecialPages();
        } else {
            $this->SetPage($this->LoadPage($tag, (isset($_REQUEST['time']) ? $_REQUEST['time'] : '')));
            $this->LogReferrer();

            try {
                echo $this->Method($this->method);
            } catch (ExitException $th) {
                if (!$this->isCli()) {
                    // action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
                    if (!empty($_SESSION['redirects'])) {
                        unset($_SESSION['redirects']);
                    }
                    // do nothing except and script with message
                    exit($th->getMessage);
                }
            }

            // action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
            if (!empty($_SESSION['redirects'])) {
                unset($_SESSION['redirects']);
            }
        }
    }

    // Find and run controller action based on route declaration, instead of using page Tag
    private function RunSpecialPages()
    {
        // We must manually parse the body data for the PUT or PATCH methods
        // See https://www.php.net/manual/fr/features.file-upload.put-method.php
        // TODO properly use the Symfony HttpFoundation component to avoid this
        if (($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH')) {
            if (empty($_POST)) {
                $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }

        $context = new RequestContext();
        $context->fromRequest($this->request);

        // Use query string as the path (part before '&')
        $extract = explode('&', $context->getQueryString());
        $path = $extract[0];
        if (strpos($path, '=') !== false) {
            if (!empty($this->method)) {
                if ($this->method === 'show' && $path === 'wiki=api') {
                    $path = 'api';
                } else {
                    $path = $this->tag . '/' . $this->method;
                    $newQuerytring = implode('&', $extract);
                }
            } else {
                $response = new Response(_t('ROUTE_BAD_CONFIGURED'), Response::HTTP_BAD_REQUEST);
                $response->send();
                $this->exit();
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

        // start buffer to prevent bad formatting response
        ob_start();
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
        } catch (Throwable $th) {
            if (isset($response) && $response instanceof JsonResponse) {
                $previousContent = json_decode($response->getContent(), true);
                $newContent = ['exceptionMessage' => $th->__toString()] + $previousContent;
                $response->setData($newContent);
                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $response = new ApiResponse(['exceptionMessage' => $th->__toString()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        $rawOutput = ob_get_contents();
        ob_end_clean();
        if (!empty($rawOutput)) {
            if ($response instanceof JsonResponse) {
                $previousContent = json_decode($response->getContent(), true);
                $newContent = is_array($previousContent)
                    ? ['rawOutput' => $rawOutput] + $previousContent
                    : (
                        is_string($previousContent)
                        ? $previousContent . $rawOutput
                        : $rawOutput
                    );
                $response->setData($newContent);
            } else {
                $previousContent = $response->getContent();
                $newContent = $previousContent . $rawOutput;
                $response->setContent($newContent);
            }
        }
        $response->send();
    }

    /**
     * furnish a method to generateRandomString.
     */
    public function generateRandomString(
        int $length = 30,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-_*=.:,?'
    ): string {
        $randompassword = '';
        $maxIndex = strlen($charset) - 1;

        if ($length < 1) {
            $length = 30;
        }

        for ($i = 0; $i < $length; $i++) {
            $randompassword .= substr($charset, random_int(0, $maxIndex), 1);
        }

        return $randompassword;
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
    public function AddCSSFile($file, $conditionstart = '', $conditionend = '', $attrs = '')
    {
        return $this->services->get(AssetsManager::class)->AddCSSFile($file, $conditionstart, $conditionend);
    }

    /**
     * @deprecated Use AssetsManager service instead
     */
    public function LinkCSSFile($file, $conditionstart = '', $conditionend = '', $attrs = '')
    {
        return $this->services->get(AssetsManager::class)->LinkCSSFile($file, $conditionstart, $conditionend);
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
            return intval(round($size * pow(1024, stripos('bkmgtpezy', $unit[0]))));
        } else {
            return intval(round($size));
        }
    }

    // Returns a file size limit in bytes based on the PHP upload_max_filesize,
    // post_max_size and wakka config max_file_size
    public function file_upload_max_size()
    {
        $conf_max_file_size = $this->GetConfigValue('max_file_size') ? $this->parse_size($this->GetConfigValue('max_file_size')) : 0;

        $post_max_size = $this->parse_size(ini_get('post_max_size'));

        $upload_max = $this->parse_size(ini_get('upload_max_filesize'));

        // return the min size limit, excluding 0 values that mean no limit
        return min(array_filter([$conf_max_file_size, $post_max_size, $upload_max]) ?? DEFAULT_MAX_UPLOAD_SIZE);
    }

    /**
     * Load all extensions.
     *
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

            // api functions
            if (file_exists($pluginBase . 'libs/' . $k . '.api.php')) {
                include $pluginBase . 'libs/' . $k . '.api.php';
            }
        }

        // merge the config between the wakka.config.php and the config.yaml of each tool
        // the priority is given for the wakka.config.php settings for scalar values and indexed arrays
        // but it's different for associative arrays, the result array is the merge between the array of the two settings
        $config = array_replace_recursive($this->services->getParameterBag()->all(), $this->config);
        $this->replaceRecursivelyIndexedArrays($config, $this->config);
        // set all wakka configs as container's parameters
        foreach ($config as $key => $value) {
            $this->services->setParameter($key, $value);
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

        // translations
        foreach ($this->extensions as $k => $pluginBase) {
            // language files : first default language, then preferred language
            if (file_exists($pluginBase . 'lang/' . $k . '_fr.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . '_fr.inc.php';
                load_translations($returnedArray);
            }
            if (file_exists($pluginBase . 'lang/' . $k . 'js_fr.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . 'js_fr.inc.php';
                load_translations($returnedArray, true);
            }
            if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php';
                load_translations($returnedArray);
            }
            if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . 'js_' . $GLOBALS['prefered_language'] . '.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . 'js_' . $GLOBALS['prefered_language'] . '.inc.php';
                load_translations($returnedArray, true);
            }
        }

        $metadata = $this->services->get(PageManager::class)->getMetadata($this->tag);

        if (isset($metadata['lang'])) {
            $this->config['lang'] = $metadata['lang'];
        } elseif (!isset($this->config['lang'])) {
            $this->config['lang'] = 'fr';
        }

        $this->services->get(ThemeManager::class)->loadTemplates($metadata);
    }

    /**
     * Replace recursively all the indexed arrays of $array1 with the corresponding indexed array of $array2.
     *
     * @param $array1 the first array that is merged
     * @param $array2 the second array that give the value for indexed array
     */
    public function replaceRecursivelyIndexedArrays(&$array1, &$array2)
    {
        foreach ($array2 as $key => $val) {
            if (is_array($val)) {
                if (!$this->isAssocArray($val)) {
                    if (!isset($array1[$key]) || $array1[$key] != $val) {
                        $array1[$key] = $val;
                    }
                } else {
                    $subarray1 = &$array1[$key];
                    $subarray2 = &$array2[$key];
                    $this->replaceRecursivelyIndexedArrays($subarray1, $subarray2);
                }
            }
        }
    }

    /**
     * Test if an array is an associative array and not an indexed on*
     * From php8.1, @see https://www.php.net/manual/fr/function.array-is-list.php instead.
     *
     * @param $arr the array
     *
     * @return bool true is it's an associative array, otherwise false
     */
    public function isAssocArray($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Shortcut to be used in the old plain PHP Actions and Handlers (instead of using SquelettePhp class).
     */
    public function render($templatePath, $data)
    {
        try {
            return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error rendering ' . $templatePath . ': ' . $e->getMessage() . '</div>' . "\n";
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
    public function LoadPage($tag, $time = '', $cache = 1)
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
    public function SavePage($tag, $body, $comment_on = '', $bypass_acls = false)
    {
        return $this->services->get(PageManager::class)->save($tag, $body, $comment_on, $bypass_acls);
    }

    /**
     * @deprecated Use PageManager::getOwner instead
     */
    public function GetPageOwner($tag = '', $time = '')
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
     * @deprecated Use AuthController::getLoggedUser instead
     */
    public function GetUser()
    {
        return $this->services->get(AuthController::class)->getLoggedUser();
    }

    /**
     * @deprecated Use AuthController::getLoggedUserName instead
     */
    public function GetUserName()
    {
        return $this->services->get(AuthController::class)->getLoggedUserName();
    }

    /**
     * @deprecated Use AuthController::login instead
     */
    public function SetUser($user, $remember = 0)
    {
        return $this->services->get(AuthController::class)->login($user, $remember);
    }

    /**
     * @deprecated Use AuthController::logout instead
     */
    public function LogoutUser()
    {
        return $this->services->get(AuthController::class)->logout();
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
    public function DeleteAcl($tag, $privileges = ['read', 'write', 'comment'])
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
     * @deprecated Use LinkTracker::clear
     */
    public function ClearLinkTable()
    {
        return $this->services->get(LinkTracker::class)->clear();
    }

    /**
     * @param string $group
     *                      The name of a group
     *
     * @return bool true iff the user is in the given $group
     *
     * @deprecated Use UserManager::isInGroup instead
     */
    public function UserIsInGroup($group, $user = null, $admincheck = true)
    {
        return $this->services->get(UserManager::class)->isInGroup($group, $user, $admincheck);
    }

    // COOKIES
    /**
     * @param string $name
     * @param string $value
     *
     * @deprecated Use AuthController::setPersistentCookie instead
     */
    public function SetSessionCookie($name, $value)
    {
        $this->services->get(AuthController::class)->setPersistentCookie($name, $value, 0);
        $_COOKIE[$name] = $value;
    }

    /**
     * @param string   $name
     * @param string   $value
     * @param bool|int $remember
     *
     * @deprecated Use AuthController::setPersistentCookie instead
     */
    public function SetPersistentCookie($name, $value, $remember = 0)
    {
        $authController = $this->services->get(AuthController::class);

        $authController->setPersistentCookie($name, $value, $authController->getExpirationTimeStamp(new DateTime(), $remember == 1));
        $_COOKIE[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @deprecated Use AuthController::deleteOldCookie instead
     */
    public function DeleteCookie($name)
    {
        $this->services->get(AuthController::class)->deleteOldCookie($name);
    }

    /**
     * @deprecated no replacement
     */
    public function GetCookie($name)
    {
        return $_COOKIE[$name];
    }
}

<?php

/**
 * Yeswiki initialization class file.
 */

namespace YesWiki;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\EnvironmentConfiguration;

class AttributeRouteControllerLoader extends AttributeClassLoader
{
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
    {
        $route->setDefault('_controller', $class->getName() . '::' . $method->getName());
    }
}

/**
 * Yeswiki initialization class.
 */
class Init
{
    public $page = '';
    public $method = '';
    public $config = [];
    public $configFile;

    /**
     * Create a new Init instance.
     *
     * @param array $config initial config array (empty by default)
     *
     * @return void
     */
    public function __construct($config = [])
    {
        $this->configFile = ConfigurationFileProvider::getConfigFileFromEnv();

        $this->getRoute();
        $this->config = $this->getConfig($config);
        $this->setIframeHeaders();

        /* @todo : compare versions, start installer for update if necessary */
        if (!file_exists($this->configFile)) {
            $this->doInstall();
            exit;
        }
    }

    /**
     * Guess the page and the handler called by current url.
     *
     * @return void
     */
    public function getRoute()
    {
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS'])) {
            $protocol = 'https://';
        }
        $scriptlocation = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
        $uri = str_replace($scriptlocation, '', $_SERVER['REQUEST_URI']);
        $uri = preg_replace('~^/\??~', '', $uri);
        $uri = explode('&', $uri);
        $uri = explode('?', $uri[0]);
        $args = explode('/', rawurldecode($uri[0]));
        if (!empty($args[0]) or !empty($_GET['wiki'])) {
            // if old school wiki url
            if ($args[0] == 'index.php' or !empty($_GET['wiki'])) {
                // remove leading slash
                $wiki = empty($_GET['wiki']) ? '' : preg_replace('/^\//', '', urldecode($_GET['wiki']));
            } else {
                $a = explode('=', $args[0]);
                $wiki = urldecode($a[0]);
            }
            if (empty($wiki)) {
                // this will be redirected to install or to homepage later
            } elseif (preg_match('`^api`', $wiki)) {
                // for api split into api/end of route, checking wiki name & method name (XSS proof)
                $this->page = 'api';
                if (strpos($wiki, '/') !== false) {
                    // $wiki already contains the full path (e.g. 'api/ferme/wikis/upgrade' from $_REQUEST['wiki'])
                    $wikiParts = explode('/', $wiki);
                    array_shift($wikiParts); // remove 'api'
                    $this->method = rtrim(implode('/', $wikiParts), '=');
                } else {
                    // $wiki is just 'api', extract method from URL path segments
                    array_shift($args); // remove api from the args
                    $this->method = rtrim(implode('/', $args), '=');
                }
            } elseif (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`u', $wiki, $matches)) {
                // split into page/method, checking wiki name & method name (XSS proof)
                list(, $this->page, $this->method) = $matches;
            } elseif (preg_match('`^' . WN_PAGE_TAG . '$`u', $wiki)) {
                // WikiPageName without method
                $this->page = $wiki;
                if (isset($args[1]) and !empty($args[1])) {
                    // Security (quick hack) : Check method syntax
                    if (preg_match('#^[A-Za-z0-9_]*$#', $args[1])) {
                        $this->method = $args[1];
                    }
                }
            } else {
                // invalid WikiPageName
                echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
                exit;
            }

            // TODO refactor this
            if (!$this->method) {
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                // We must manually parse the body data for the PUT or PATCH methods
                // See https://www.php.net/manual/fr/features.file-upload.put-method.php
                if (empty($_POST) && ($requestMethod == 'POST' || $requestMethod == 'PUT' || $requestMethod == 'PATCH')) {
                    $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
                }

                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: X-Requested-With, Location, Link, Slug, Accept, Content-Type');
                header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT, PATCH');
                header('Access-Control-Max-Age: 86400');

                switch ($requestMethod) {
                    case 'DELETE':
                        $this->method = 'api_delete';
                        break;
                    case 'PATCH':
                        $this->method = 'api_patch';
                        break;
                    case 'PUT':
                        $this->method = 'api_put';
                        break;
                }
            }
            $_GET['wiki'] = $this->page . ($this->method ? '/' . $this->method : '');
        }
    }

    /**
     * set headers for iframes.
     */
    private function setIframeHeaders()
    {
        // set header for Content-Security-Policy
        $allowedMethods = $this->config['allowed_methods_in_iframe'] ?? 'all';

        if ($this->page === 'doc' || $allowedMethods === 'all' || (
            is_array($allowedMethods) && in_array($this->method, $allowedMethods, true)
        )) {
            // allow local ('self') and everyone (*)
            header("Content-Security-Policy: frame-ancestors 'self' *;");
        } else {
            // for old browsers
            header('X-frame-Options: deny');
            // disallow (CSP takes advantage on x-frame-options)
            header("Content-Security-Policy: frame-ancestors 'none';");
        }
    }

    /**
     * Utility function to merge the multidimentionnal config array the right way.
     *
     * @return array merged array
     */
    protected function array_merge_recursive_distinct(array &$array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Check in the config file exists and provide default configuration.
     *
     * @param array $yeswikiConfig initial config array (empty by default)
     *
     * @return array the configuration
     */
    public function getConfig($yeswikiConfig = [])
    {
        $_rewrite_mode = detectRewriteMode();
        $yeswikiDefaultConfig = [
            'yeswiki_version' => '',
            'yeswiki_release' => '',
            'charset' => 'UTF-8',
            'debug' => false,
            'db_driver' => 'mysql',
            'db_host' => 'localhost',
            'db_database' => '',
            'db_user' => '',
            'db_password' => '',
            'table_prefix' => 'yeswiki_',
            'base_url' => computeBaseURL($_rewrite_mode),
            'rewrite_mode' => $_rewrite_mode,
            'meta_keywords' => '',
            'meta_description' => '',
            'header_action' => 'header',
            'footer_action' => 'footer',
            'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
            'referrers_purge_time' => 24,
            'pages_purge_time' => 365,
            'default_write_acl' => '*',
            'default_read_acl' => '*',
            'default_comment_acl' => 'comments-closed',
            // default false, unlike the pre-absorption accountactivationbyemail extension's
            // own default of true (ticket 07): that default only ever affected wikis that
            // explicitly installed the extension -- defaulting to true here would silently
            // gate new signups on every wiki upgrading to this version, whether or not it
            // ever used this feature before
            'signup_email_activation' => false,
            'user_activation_key_length' => 20,
            'comments_activated' => true,
            'comments_handler' => 'yeswiki',
            'preview_before_save' => false,
            'allow_raw_html' => true,
            'disallowed_html_tags' => ['title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext'],
            'allowed_methods_in_iframe' => ['iframe', 'editiframe', 'bazariframe', 'render'],
            'revisionscount' => 30,
            'timezone' => 'Europe/Paris', // Only used if not set in yeswiki.config.php nor in php.ini
            'root_page' => 'PagePrincipale', // backup root_page if deleted from yeswiki.config.php
            'yeswiki_name' => '', // backup yeswiki_name if deleted from yeswiki.config.php
            'htmlPurifierActivated' => true,
            'htmlPurifierSafeIframeRegexp' => '~^https://.*~', // regex for domains allowed as <iframe> src ; very permissive by default, restrict for public wikis
            'favorites_activated' => true,
            ArchiveService::PARAMS_KEY_IN_WAKKA => [
                ArchiveService::KEY_FOR_HIDE_CONFIG_VALUES => ArchiveService::DEFAULT_PARAMS_TO_ANONYMIZE,
                'authorize_bypass_preupdate_backup' => false,
                'preupdate_backup_activated' => true,
                'call_archive_async' => true,
                ArchiveService::KEY_FOR_PRIVATE_FOLDER => ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP,
                'max_nb_files' => 10,
            ],
        ];
        unset($_rewrite_mode);

        if (file_exists($this->configFile)) {
            include $this->configFile;
            // config files written before the rename define $wakkaConfig instead
            if (isset($wakkaConfig) && is_array($wakkaConfig)) {
                $yeswikiConfig = $wakkaConfig;
            }
        } else {
            // we must init language file without loading the page's settings.. to translate some default config settings
            $yeswikiDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
            $yeswikiDefaultConfig['yeswiki_name'] = _t('MY_YESWIKI_SITE');
        }

        // Backwards compatibility: map keys of pre-ectoplasme config files to their new names
        $legacyKeyMapping = [
            'mysql_host' => 'db_host',
            'mysql_database' => 'db_database',
            'mysql_user' => 'db_user',
            'mysql_password' => 'db_password',
            'mysql_port' => 'db_port',
            'wakka_name' => 'yeswiki_name',
        ];
        foreach ($legacyKeyMapping as $oldKey => $newKey) {
            if (isset($yeswikiConfig[$oldKey]) && !isset($yeswikiConfig[$newKey])) {
                $yeswikiConfig[$newKey] = $yeswikiConfig[$oldKey];
            }
            unset($yeswikiConfig[$oldKey]);
        }
        // dropped keys and the removed wakka.php entry script, from pre-ectoplasme config files
        unset($yeswikiConfig['wakka_version']);
        if (!empty($yeswikiConfig['base_url'])) {
            $yeswikiConfig['base_url'] = str_replace('/wakka.php?wiki=', '/?', $yeswikiConfig['base_url']);
        }

        $yeswikiConfig = $this->array_merge_recursive_distinct($yeswikiDefaultConfig, $yeswikiConfig);

        // debug is a boolean now; existing config files may still carry the
        // historical 'yes'/'no' strings
        $yeswikiConfig['debug'] = filter_var($yeswikiConfig['debug'], FILTER_VALIDATE_BOOLEAN);

        // environment overrides (private/.env or real environment variables) win over
        // yeswiki.config.php; applied before the timezone/debug values are consumed below.
        // YesWikiKernel::build() applies the same overrides to extension parameters.
        $yeswikiConfig = EnvironmentConfiguration::apply($yeswikiConfig);

        // give a default timezone to avoid error
        if (!empty($yeswikiConfig['timezone'])) {
            date_default_timezone_set($yeswikiConfig['timezone']);
        } elseif (!empty($yeswikiDefaultConfig['timezone'])) {
            date_default_timezone_set($yeswikiDefaultConfig['timezone']);
        } elseif (!ini_get('date.timezone')) {
            // backup in last case
            date_default_timezone_set('GMT');
        }

        // check for locking
        if (file_exists('locked')) {
            // read password from lockfile
            $lines = file('locked');
            $lockpw = trim($lines[0]);

            // is authentification given?
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                if (!(($_SERVER['PHP_AUTH_USER'] == 'admin') && ($_SERVER['PHP_AUTH_PW'] == $lockpw))) {
                    $ask = 1;
                }
            } else {
                $ask = 1;
            }

            if ($ask) {
                header('WWW-Authenticate: Basic realm="' . $yeswikiConfig['yeswiki_name'] . ' Install/Upgrade Interface"');
                header('HTTP/1.0 401 Unauthorized');
                echo _t('SITE_BEING_UPDATED');
                exit;
            }
        }

        // Display all errors if in debug mode
        if ($yeswikiConfig['debug']) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        if (empty($yeswikiConfig['mail_domain'] ?? null)) {
            $yeswikiConfig['mail_domain'] = \getMailDomain(parse_url($yeswikiConfig['base_url'])['host']);
        }

        if (!empty($yeswikiConfig['extra_headers'])) {
            foreach ($yeswikiConfig['extra_headers'] as $header) {
                header($header);
            }
        }

        return $yeswikiConfig;
    }

    /**
     * Initialize the cookie.
     *
     * @return string $CookiePath path to the cookie
     */
    public function initCookies()
    {
        // configuration du cookie de session
        // determine le chemin pour les cookies
        $urlParsed = parse_url($this->config['base_url']);
        $CookiePath = $urlParsed['path'];

        // Fixe la gestion des cookie sous les OS utilisant le \ comme separateur de chemin
        $CookiePath = str_replace('\\', '/', $CookiePath);

        foreach (['index.php'] as $anchor) {
            if (substr($CookiePath, -strlen($anchor)) == $anchor) {
                $CookiePath = substr($CookiePath, 0, strlen($CookiePath) - strlen($anchor));
            }
        }

        // ajoute un '/' terminal sauf si on est a la racine web et si nécessaire
        if (substr($CookiePath, -1) !== '/') {
            $CookiePath .= '/';
        }

        $sessionName = 'YesWiki-main';
        if ($CookiePath !== '/') {
            $sessionName = 'YesWiki-' . str_replace('/', '-', substr($CookiePath, 1, -1));
        }

        // test if session exists, because the wiki object is instanciated for every plugin
        if (!isset($_SESSION)) {
            $cookiesParam = session_get_cookie_params();
            $cookiesParam['path'] = $CookiePath;
            $cookiesParam['httponly'] = true;
            $cookiesParam['samesite'] = 'Lax';
            if (preg_match('`^https://`', $this->config['base_url'], $matches)) {
                $cookiesParam['secure'] = true;
            }
            session_set_cookie_params($cookiesParam);
            session_name($sessionName);
            session_start();
        }

        return $CookiePath;
    }

    /**
     * Start the install process.
     *
     * @return void
     */
    public function doInstall()
    {
        $controller = new Core\Controller\InstallationController($this->config, $this->configFile);
        $controller->run();
    }
}

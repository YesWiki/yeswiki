<?php
/**
 * Yeswiki initialization class file
 *
 * @category Wiki
 * @package  YesWiki
 * @author   2018 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 *
 *
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 * derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace YesWiki;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

// TODO put elsewhere
// https://github.com/sensiolabs/SensioFrameworkExtraBundle/blob/master/src/Routing/AnnotatedRouteControllerLoader.php
class AnnotatedRouteControllerLoader extends AnnotationClassLoader
{
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot)
    {
        $route->setDefault('_controller', $class->getName() . '::' . $method->getName());
    }
}

/**
 * Yeswiki initialization class
 *
 * @category Wiki
 * @package  YesWiki
 * @author   2018 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */
class Init
{
    public $page = '';
    public $method = '';
    public $config = array();
    public $configFile = 'wakka.config.php';

    /**
     * Create a new Init instance.
     *
     * @param array $config initial config array (empty by default)
     *
     * @return void
     */
    public function __construct($config = array())
    {
        $this->getRoute();
        $this->config = $this->getConfig($config);

        /* @todo : compare versions, start installer for update if necessary */
        if (!file_exists($this->configFile)) {
            $this->doInstall();
            exit();
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
        $scriptlocation = str_replace(array('/index.php', '/wakka.php'), '', $_SERVER["SCRIPT_NAME"]);
        $uri = str_replace($scriptlocation, '', $_SERVER['REQUEST_URI']);
        $uri = preg_replace('~^/\??~', '', $uri);
        $uri = explode('&', $uri);
        $uri = explode('?', $uri[0]);
        $args = explode('/', $uri[0]);

        if (!empty($args[0]) or !empty($_REQUEST['wiki'])) {
            // if old school wiki url
            if ($args[0] == 'index.php' or $args[0] == 'wakka.php' or !empty($_REQUEST['wiki'])) {
                // remove leading slash
                $wiki = empty($_REQUEST['wiki']) ? '' : preg_replace('/^\//', '', urldecode($_REQUEST['wiki']));
            } else {
                $a = explode('=', $args[0]);
                $wiki = urldecode($a[0]);
            }
            if (empty($wiki)) {
                // this will be redirected to install or to homepage later
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
            } elseif (preg_match('`^api/('.WN_CHAR2.'+(?:' . WN_CHAR2 . '|/| )*)$`u', $wiki, $matches)) {
                // for api split into api/end of route, checking wiki name & method name (XSS proof)
                $this->page = 'api';
                list(, $this->method) = $matches;
            } else {
                // invalid WikiPageName
                echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
                exit();
            }

            // TODO refactor this
            if (!$this->method) {
                // We must manually parse the body data for the PUT or PATCH methods
                // See https://www.php.net/manual/fr/features.file-upload.put-method.php
                if (empty($_POST) && ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH')) {
                    $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
                }

                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: X-Requested-With, Location, Link, Slug, Accept, Content-Type');
                header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT, PATCH');
                header('Access-Control-Max-Age: 86400');

                switch ($_SERVER['REQUEST_METHOD']) {
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

            $_GET['wiki'] = $this->page.($this->method ? '/'.$this->method : '');
        }
    }

    /**
     * Check in the config file exists and provide default configuration
     *
     * @param array $wakkaConfig initial config array (empty by default)
     *
     * @return array the configuration
     */
    public function getConfig($wakkaConfig = array())
    {
        $_rewrite_mode = detectRewriteMode();
        $yeswikiDefaultConfig = array(
            'wakka_version' => '',
            'wikini_version' => '',
            'yeswiki_version' => '',
            'yeswiki_release' => '',
            'charset' => 'UTF-8',
            'debug' => 'no',
            'mysql_host' => 'localhost',
            'mysql_database' => '',
            'mysql_user' => '',
            'mysql_password' => '',
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
            'default_comment_acl' => '@admins',
            'preview_before_save' => 0,
            'allow_raw_html' => true,
            'disable_wiki_links' => false,
            'timezone'=>'GMT' // Only used if not set in wakka.config.php nor in php.ini
        );
        unset($_rewrite_mode);

        if (file_exists($this->configFile)) {
            include $this->configFile;
        } else {
            // we must init language file without loading the page's settings.. to translate some default config settings
            $yeswikiDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
            $yeswikiDefaultConfig['wakka_name'] = _t('MY_YESWIKI_SITE');
        }
        $wakkaConfig = array_merge($yeswikiDefaultConfig, $wakkaConfig);

        // give a default timezone to avoid error
        if ($wakkaConfig['timezone'] != $yeswikiDefaultConfig['timezone']) {
            date_default_timezone_set($wakkaConfig['timezone']);
        } elseif (!ini_get('date.timezone')) {
            date_default_timezone_set($yeswikiDefaultConfig['timezone']);
        }

        // check for locking
        if (file_exists('locked')) {
            // read password from lockfile
            $lines = file('locked');
            $lockpw = trim($lines[0]);

            // is authentification given?
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                if (! (($_SERVER['PHP_AUTH_USER'] == "admin") && ($_SERVER["PHP_AUTH_PW"] == $lockpw))) {
                    $ask = 1;
                }
            } else {
                $ask = 1;
            }

            if ($ask) {
                header('WWW-Authenticate: Basic realm="' . $wakkaConfig['wakka_name'] . ' Install/Upgrade Interface"');
                header('HTTP/1.0 401 Unauthorized');
                echo _t('SITE_BEING_UPDATED');
                exit();
            }
        }

        // Afficher les erreurs en mode debug
        if (isset($_GET['debug'])) {
            $wakkaConfig['debug'] = 'yes';
        }
        if (strtolower($wakkaConfig['debug']) == 'yes') {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        if ($wakkaConfig['wakka_version'] && (! $wakkaConfig['wikini_version'])) {
            $wakkaConfig['wikini_version'] = $wakkaConfig['wakka_version'];
        }

        return $wakkaConfig;
    }

    /**
     * Initialize YesWiki core services
     * Extensions services will be loaded in the YesWiki::loadExtensions method
     */
    public function initCoreServices($wiki)
    {
        $containerBuilder = new ContainerBuilder();

        // Set main YesWiki object as a parameter
        // TODO remove this when the refactoring will be done
        $containerBuilder->setParameter('wiki', $wiki);

        $containerBuilder->set(Wiki::class, $wiki);
        $containerBuilder->set(ParameterBagInterface::class, $containerBuilder->getParameterBag());

        $loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('services.yaml');

        return $containerBuilder;
    }

    public function initRoutes($wiki)
    {
        $routes = new RouteCollection();

        $loader = new AnnotationDirectoryLoader(
            new FileLocator(__DIR__.'/../'),
            new AnnotatedRouteControllerLoader(
                new AnnotationReader()
            )
        );

        // Core controllers
        $routes->addCollection($loader->load('includes/controllers'));

        foreach ($wiki->extensions as $extensionKey => $extensionPath) {
            $controllersDir = \getcwd(). '/' . $extensionPath . 'controllers';
            if (is_dir($controllersDir)) {
                $routes->addCollection($loader->load($controllersDir));
            }
        }

        return $routes;
    }

    /**
     * Initialize the cookie
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

        // ajoute un '/' terminal sauf si on est a la racine web et si nécessaire
        if (substr($CookiePath, -1) !== '/') {
            $CookiePath .= '/';
        }

        $sessionName = "YesWiki-main";
        if ($CookiePath !== '/') {
            $sessionName = "YesWiki-" . str_replace('/', '-', substr($CookiePath, 1, -1));
        }

        // test if session exists, because the wiki object is instanciated for every plugin
        if (!isset($_SESSION)) {
            $cookiesParam = session_get_cookie_params();
            $cookiesParam['path'] = $CookiePath;
            $cookiesParam['httponly'] = true;
            session_set_cookie_params($cookiesParam);
            session_name($sessionName);
            session_start();
        }

        return $CookiePath;
    }

    /**
     * Start the install process
     *
     * @return void
     */
    public function doInstall()
    {
        // start installer
        if (! isset($_REQUEST['installAction']) or ! $installAction = trim($_REQUEST['installAction'])) {
            $installAction = "default";
        }
        // default lang
        loadpreferredI18n('');
        $wakkaConfig = $this->config;
        $wakkaConfigLocation = $this->configFile;
        include_once 'setup/install.helpers.php';
        include_once 'setup/header.php';
        if (file_exists('setup/' . $installAction . '.php')) {
            include_once 'setup/' . $installAction . '.php';
        } else {
            echo '<em>', _t("INVALID_ACTION"), '</em>';
        }
        include_once 'setup/footer.php';
    }
}

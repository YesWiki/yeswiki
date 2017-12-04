<?php
/** 
 * First start initialisation of YesWiki
 * 
 * @todo : this is a transition file that checks everything before starting YesWiki,
 *  it should become a method of the main YesWiki class
 *  that checks and initialise everything 
 *
 **/

// do not change this line, you fool. In fact, don't change anything! Ever!
define('WAKKA_VERSION', '0.1.1');
define('WIKINI_VERSION', '0.5.0');
define("YESWIKI_VERSION", 'cercopitheque');
define("YESWIKI_RELEASE", '2017-11-25-1');

require_once 'includes/constants.php';
require_once 'includes/urlutils.inc.php';
require_once 'includes/i18n.inc.php';
require_once 'includes/class.yeswiki.php';

define('T_START', microtime(true));

$t_SQL = 0;

// stupid version check
if (! isset($_REQUEST)) {
    die(_t('NO_REQUEST_FOUND'));
}

$page = $method = '';
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
        $wiki = empty($_REQUEST['wiki']) ? '' : preg_replace('/^\//', '', $_REQUEST['wiki']);

        if (empty($wiki)) {
            // this will be redirected to install or to homepage later        
        } elseif (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`', $wiki, $matches)) {
            // split into page/method, checking wiki name & method name (XSS proof)
            list (, $page, $method) = $matches;
        } elseif (preg_match('`^' . WN_PAGE_TAG . '$`', $wiki)) {
            // WikiPageName without method
            $page = $wiki;
        } else {
            // invalid WikiPageName
            echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
            exit();
        }
    } elseif ($args[0] == 'api') {
        // call to YesWiki api
        if (isset($args[1]) and !empty($args[1])) {
            $apiFunctionName = ucfirst($_SERVER['REQUEST_METHOD']).ucfirst($args[1]);
            echo 'YesWiki api - function: '.$apiFunctionName;
        } else {
            echo 'YesWiki api';
        }
    } else {
        $page = $args[0];
        if (isset($args[1]) and !empty($args[1])) {
            // Security (quick hack) : Check method syntax
            if (preg_match('#^[A-Za-z0-9_]*$#', $args[1])) {
                $method = $args[1];
            }
        }
        $_GET['wiki'] = $page.'/'.$method;
        
    }
}

// default configuration values
$wakkaConfig = array();
$_rewrite_mode = detectRewriteMode();
$wakkaDefaultConfig = array(
    'wakka_version' => '',
    'wikini_version' => '',
    'yeswiki_version' => '',
    'yeswiki_release' => '',
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
    'action_path' => 'actions',
    'handler_path' => 'handlers',
    'header_action' => 'header',
    'footer_action' => 'footer',
    'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
    'referrers_purge_time' => 24,
    'pages_purge_time' => 90,
    'default_write_acl' => '*',
    'default_read_acl' => '*',
    'default_comment_acl' => '@admins',
    'preview_before_save' => 0,
    'allow_raw_html' => false,
    'timezone'=>'GMT' // Only used if not set in wakka.config.php nor in php.ini
);
unset($_rewrite_mode);

// load config
if (! $configfile = GetEnv('WAKKA_CONFIG')) {
    $configfile = 'wakka.config.php';
}

if (file_exists($configfile)) {
    include $configfile;
} else {
    // we must init language file without loading the page's settings.. to translate some default config settings
    $wakkaDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
    $wakkaDefaultConfig['wakka_name'] = _t('MY_YESWIKI_SITE');
}
$wakkaConfigLocation = $configfile;
$wakkaConfig = array_merge($wakkaDefaultConfig, $wakkaConfig);

// give a default timezone to avoid error
if ($wakkaConfig['timezone'] != $wakkaDefaultConfig['timezone']) {
    date_default_timezone_set($wakkaConfig['timezone']);
} elseif (!ini_get('date.timezone')) {
    date_default_timezone_set($wakkaDefaultConfig['timezone']);
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

// compare versions, start installer if necessary
if ($wakkaConfig['wakka_version'] && (! $wakkaConfig['wikini_version'])) {
    $wakkaConfig['wikini_version'] = $wakkaConfig['wakka_version'];
}

if (($wakkaConfig['wakka_version'] != WAKKA_VERSION) || ($wakkaConfig['wikini_version'] != WIKINI_VERSION)) {
    // start installer
    if (! isset($_REQUEST['installAction']) or ! $installAction = trim($_REQUEST['installAction'])) {
        $installAction = "default";
    }
    include 'setup/header.php';
    if (file_exists('setup/' . $installAction . '.php')) {
        include 'setup/' . $installAction . '.php';
    } else {
        echo '<em>', _t("INVALID_ACTION"), '</em>';
    }
    include 'setup/footer.php';
    exit();
}


// Afficher les erreurs en mode debug
if (strtolower($wakkaConfig['debug']) == 'yes') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}


// configuration du cookie de session
// determine le chemin pour les cookies
$a = parse_url($wakkaConfig['base_url']);
$CookiePath = dirname($a['path']);
// Fixe la gestion des cookie sous les OS utilisant le \ comme s?parteur de chemin
$CookiePath = str_replace('\\', '/', $CookiePath);
// ajoute un '/' terminal sauf si on est ? la racine web
if ($CookiePath != '/') {
    $CookiePath .= '/';
}

$a = session_get_cookie_params();
session_set_cookie_params($a['lifetime'], $CookiePath);
unset($a);
unset($CookiePath);

// start session
session_start();

// fetch wakka location
if (empty($page)) {
    // redirect to the root page
    header('Location: ' . $wakkaConfig['base_url'] . $wakkaConfig['root_page']);
    exit();
}

// create wiki object
$wiki = new \YesWiki\Wiki($wakkaConfig);

// update lang
loadpreferredI18n($page);
// check for database access
if (! $wiki->dblink) {
    echo '<p>', _t('DB_CONNECT_FAIL'), '</p>';
    // Log error (useful to find the buggy server in a load balancing platform)
    trigger_error(_t('LOG_DB_CONNECT_FAIL'));
    exit();
}

// Meme nom : remplace
// _Meme nom : avant
// Meme nom : _apres

require_once 'includes/class.plugins.php';

$plugins_root = 'tools/';

$objPlugins = new plugins($plugins_root);
$objPlugins->getPlugins(true);
$plugins_list = $objPlugins->getPluginsList();

$wakkaConfig['formatter_path'] = 'formatters';
$wikiClasses[] = 'WikiTools';
$wikiClassesContent[] = '';

foreach ($plugins_list as $k => $v) {
    
    $pluginBase = $plugins_root . $k . '/';
    
    if (file_exists($pluginBase . 'wiki.php')) {
        include ($pluginBase . 'wiki.php');
    }
    
    // language files : first default language, then preferred language
    if (file_exists($pluginBase . 'lang/' . $k . '_fr.inc.php')) {
        include ($pluginBase . 'lang/' . $k . '_fr.inc.php');
    }
    if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
        include ($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php');
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

for ($iw = 0; $iw < count($wikiClasses); $iw ++) {
    if ($wikiClasses[$iw] != 'WikiTools') {
        if ($wikiClasses[$iw - 1] == 'Wiki') {
            $wikiClasses[$iw - 1] == '\YesWiki\Wiki';
        }
        if ($wikiClasses[$iw] == 'Wiki') {
            $wikiClasses[$iw] == '\YesWiki\Wiki';
        }
        eval('Class ' . $wikiClasses[$iw] . ' extends ' . $wikiClasses[$iw - 1] . ' { ' . $wikiClassesContent[$iw] . ' }; ');
    }
}
// $wiki = new WikiTools($wakkaConfig);
eval('$wiki  = new ' . $wikiClasses[count($wikiClasses) - 1] . '($wakkaConfig);');

$wiki->Run($page, $method);

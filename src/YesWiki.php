<?php

namespace YesWiki;

require_once __DIR__ . '/bootstrap_paths.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/urlutils.inc.php';
require_once __DIR__ . '/email.inc.php';
require_once __DIR__ . '/bazar.functions.php';
// defines LanguageService and the global _t() translation function; loaded
// explicitly because the autoloader may not be registered yet at this point
require_once __DIR__ . '/Kernel/Service/LanguageService.php';
require_once __DIR__ . '/YesWikiInit.php';
require_once __DIR__ . '/YesWikiKernel.php';
require_once __DIR__ . '/YesWikiPerformable.php';
require_once __DIR__ . '/objects/YesWikiAction.php';
require_once __DIR__ . '/objects/YesWikiHandler.php';

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Admin\Service\ApiService;
use YesWiki\Content\Controller\LegacyPageController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\ReferrerService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiControllerResolver;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ThemeManager;

// base translations and language detection (also defines YW_CHARSET); runs at load
// time, before anything (Init, the installer, error paths) calls _t()
LanguageService::getInstance()->initialize();

class Wiki
{
    public $config;
    public $metadatas; // todo use PageManager or method instead of public var
    public $method;
    public $page;
    public $tag;
    public $request;
    public $CookiePath = '/';
    public $extensions = [];
    // lazily populated RouteCollection - always read it through getRoutes()
    public $routes = [];
    /**
     * The service container, assigned from the kernel in boot().
     *
     * Annotated rather than natively typed on purpose: a native non-nullable type would
     * make any read before boot() a runtime Error instead of the null it returns today.
     * Annotated non-null (ticket 08): every surviving read runs after boot() — the
     * pre-boot paths (kernel build, constructor) were weaned off service-backed methods —
     * and the property goes away with the locator when this class is deleted.
     *
     * @var ContainerInterface
     */
    public $services;
    public $_groupsCache = [];

    private $environment;
    private $httpKernel;

    /**
     * Constructor.
     */
    public function __construct($config = [])
    {
        $init = new Init($config);
        $this->config = $init->config;
        $this->CookiePath = $init->initCookies();
        $this->tag = $init->page;
        $this->method = $init->method;

        // single source of truth for every environment-keyed cache
        // (compiled container, see boot(); route collection, see getRoutes())
        $this->environment = defined('PHPUNIT_COMPOSER_INSTALL')
            ? 'test'
            : ($this->GetConfigValue('debug') ? 'dev' : 'prod');
        $this->request = Request::createFromGlobals();

        $this->loadExtensions();
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Typed service lookup for the delegation shims: Symfony's ContainerInterface::get()
     * is declared `?object`, so chaining a call on it fails static analysis even though
     * every service asked for here is a compiled, always-present one. Dies with the
     * locator when this class is deleted.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        /** @var T $service */
        $service = $this->services->get($class);

        return $service;
    }

    public function GetConfigValue($name, $default = null)
    {
        return isset($this->config[$name])
            ? is_array($this->config[$name]) ? $this->config[$name] : trim($this->config[$name])
            : ($default != null ? $default : '');
    }

    /**
     * Make the purge of page versions that are older than the last version older than "pages_purge_time"
     * This method permits to allways keep a not latest version that is older than that period.
     */
    public function PurgePages()
    {
        if (($days = $this->GetConfigValue('pages_purge_time')) && !$this->service(HibernationService::class)->isWikiHibernated()) {
            // is purge active ?
            // let's search which pages versions we have to remove
            // this is necessary beacause even MySQL does not handel multi-tables deletes before version 4.0
            $wnPages = $this->GetConfigValue('table_prefix') . 'pages';
            $dbService = $this->service(DbService::class);
            $dateExpr = $dbService->dateSubDays(intval($days));
            $sql = <<<SQL
            SELECT DISTINCT a.id FROM $wnPages a,$wnPages b
                WHERE a.latest = 'N'
                    AND b.latest = 'N'
                    AND a.time < $dateExpr
                    AND a.tag = b.tag
                    AND a.time < b.time
                    AND b.time < $dateExpr;
            SQL;
            $ids = $this->service(DbService::class)->loadAll($sql);

            if (count($ids)) {
                // there are some versions to remove from DB
                // let's build one big request, that's better...
                $sql = 'DELETE FROM ' . $wnPages . ' WHERE id IN (';
                foreach ($ids as $key => $line) {
                    $sql .= ($key ? ', ' : '') . $line['id']; // NB.: id is an int, no need of quotes
                }
                $sql .= ')';

                // ... and send it !
                $this->service(DbService::class)->query($sql);
            }
        }
    }

    // COMMENTS
    // ACCESS CONTROL
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
        return $this->service(ModuleAclService::class)->checkModuleAcl($module, $module_type, $user);
    }

    // MAINTENANCE
    protected const MAINTENANCE_INTERVAL = 1800; // run at most once every 30 minutes
    protected const MAINTENANCE_LOCK_FILE = 'cache/maintenance.lock';

    public function Maintenance()
    {
        // purge referrers
        $this->service(ReferrerService::class)->purge();
        // purge old page revisions
        $this->PurgePages();
        // purge expired password recovery keys
        $this->service(UserManager::class)->purgeExpiredPasswordRecoveryKeys();
        // purge expired account-activation keys
        $this->service(AccountActivationService::class)->purgeExpiredActivationKeys();
    }

    /**
     * Poor man's cron: Maintenance() is only run once self::MAINTENANCE_INTERVAL has elapsed.
     */
    protected function shouldRunMaintenance(): bool
    {
        $lastRun = @filemtime(self::MAINTENANCE_LOCK_FILE) ?: 0;
        if (time() - $lastRun < self::MAINTENANCE_INTERVAL) {
            return false;
        }
        if (!is_dir('cache')) {
            mkdir('cache', 0777, true);
        }
        touch(self::MAINTENANCE_LOCK_FILE);

        return true;
    }

    // THE BIG EVIL NASTY ONE!
    public function Run($tag = '', $method = '')
    {
        try {
            $this->doRun($tag, $method);
        } catch (ExitException $th) {
            // Wiki::exit()/Redirect() unwinding outside of the HttpKernel dispatch (e.g. the
            // empty-tag redirect below, or RunSpecialPages' early exits): reproduce the
            // historical exit($message) behavior for web requests, keep throwing under CLI
            // where tests/console rely on catching it
            if (YesWikiKernel::isCli()) {
                throw $th;
            }
            echo $th->getMessage();
        }
    }

    private function doRun($tag, $method)
    {
        if ($this->shouldRunMaintenance()) {
            $this->Maintenance();
        }

        // do our stuff!
        if ($tag == '') {
            $tag = $this->tag;
        }

        if (!$this->method = trim($method)) {
            $this->method = 'show';
        }

        if (!$this->tag = trim($tag)) {
            $this->service(Redirector::class)->redirect($this->service(UrlFormatter::class)->href('', $this->config['root_page']));
        }

        $this->service(AuthenticationService::class)->connectUser();

        // Is this a special page ?
        if (in_array($tag, ['api', 'doc'])) {
            $this->RunSpecialPages();
        } else {
            $request = $this->service(CurrentRequest::class)->get();
            $request->attributes->set('_controller', LegacyPageController::class);
            $request->attributes->set('_tag', $this->tag);
            $request->attributes->set('_method', $this->method);

            $this->handleWithHttpKernel($request)->send();

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
        if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH') {
            if (empty($_POST)) {
                $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }
        $context = new RequestContext();
        $context->fromRequest($this->service(CurrentRequest::class)->get());

        // Use query string as the path (part before '&')
        $extract = explode('&', $context->getQueryString());
        $path = $extract[0];
        if (strpos($path, '=') !== false) {
            if (!empty($this->method)) {
                if ($this->method === 'show' && $path === 'wiki=api') {
                    $path = 'api';
                } else {
                    $path = $this->tag . '/' . $this->method;
                    $newQuerystring = implode('&', $extract);
                }
            } else {
                $response = new Response(_t('ROUTE_BAD_CONFIGURED'), Response::HTTP_BAD_REQUEST);
                $response->send();
                throw new ExitException('');
            }
        } elseif (count($extract) > 1) {
            array_shift($extract);
            $newQuerystring = implode('&', $extract);
        }
        $context->setPathInfo('/' . $_GET['wiki']);
        $context->setQueryString($newQuerystring ?? $_GET['wiki']);

        $matcher = new UrlMatcher($this->getRoutes(), $context);

        // start buffer to prevent bad formatting response
        ob_start();
        try {
            // TODO put this elsewhere ?
            $attributes = $matcher->match($context->getPathInfo());
            if ($this->service(ApiService::class)->isAuthorized($attributes, $this->getRoutes())) {
                $request = $this->service(CurrentRequest::class)->get();
                $request->attributes->add($attributes);
                $response = $this->handleWithHttpKernel($request);
            } else {
                $response = new Response('Not enough access rights', Response::HTTP_UNAUTHORIZED);
            }
        } catch (ResourceNotFoundException $exception) {
            $response = new Response('', Response::HTTP_NOT_FOUND);
        } catch (MethodNotAllowedException $exception) {
            $response = new Response('', Response::HTTP_METHOD_NOT_ALLOWED);
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
     * The attribute routes of every controller (core + extensions), loaded lazily: only api/doc
     * requests ever match against them (see RunSpecialPages()), so ordinary page views no longer
     * pay for reflecting over every controller class on every request. The built collection is
     * cached (serialized, per environment like the compiled container) and freshness-checked
     * against the resources the attribute loaders register (controller file edits/additions)
     * plus the extension set itself (see YesWikiKernel::extensionSetResources()).
     */
    public function getRoutes(): RouteCollection
    {
        if ($this->routes instanceof RouteCollection) {
            return $this->routes;
        }

        // instance cache dir (cwd), same reasoning as YesWikiKernel::getCacheDir()
        $cache = new ConfigCache(getcwd() . '/cache/routes/' . $this->environment . '.php', true);

        if ($cache->isFresh()) {
            $routes = unserialize(require $cache->getPath());
            if ($routes instanceof RouteCollection) {
                return $this->routes = $routes;
            }
        }

        [$routes, $resources] = $this->buildRouteCollection();
        $cache->write('<?php return ' . var_export(serialize($routes), true) . ';', $resources);

        return $this->routes = $routes;
    }

    /**
     * @return array{0: RouteCollection, 1: FileResource[]} the collection plus the freshness
     *                                                      resources to guard its cache with
     *
     * The freshness resources are built by hand instead of taking $routes->getResources(): the
     * attribute loaders register ReflectionClassResources, whose isFresh() answer depends on the
     * content hash stored in each cache's meta file. SelfCheckingResourceChecker memoizes answers
     * process-wide keyed only on "resource:timestamp", so when two caches (compiled container +
     * this one) hold different stored hashes for the same class and happen to share an mtime
     * second, one cache's answer poisons the other's and a stale route cache is served as fresh.
     * Plain FileResources are fully determined by (path, timestamp), immune to that: one per
     * controller file catches edits, one per controllers directory catches added/removed files.
     */
    private function buildRouteCollection(): array
    {
        $routes = new RouteCollection();
        $resources = YesWikiKernel::extensionSetResources(\dirname(__DIR__));

        $loader = new AttributeDirectoryLoader(
            new FileLocator(__DIR__ . '/../'),
            new AttributeRouteControllerLoader()
        );

        // Route discovery is directory-driven, so it does not follow a class into a new
        // namespace: wave-two ticket 05 moved ApiController into src/Admin/Controller/ and
        // every /api/* route silently disappeared -- the only symptom was an endpoint
        // answering with an empty body. Module controller directories must be scanned too.
        // Same trap as FieldFactory's field scan and the console's command glob.
        $controllersDirs = [];
        // src/controllers/ is emptied as modules migrate (ticket 05) and eventually removed
        if (is_dir(__DIR__ . '/controllers')) {
            $controllersDirs[] = __DIR__ . '/controllers';
        }
        foreach (glob(__DIR__ . '/*/Controller', GLOB_ONLYDIR) ?: [] as $moduleControllersDir) {
            $controllersDirs[] = $moduleControllersDir;
        }
        // /api/* resource controllers live in src/<Module>/Api/ (ticket 08 split)
        foreach (glob(__DIR__ . '/*/Api', GLOB_ONLYDIR) ?: [] as $moduleApiDir) {
            $controllersDirs[] = $moduleApiDir;
        }
        foreach ($this->extensions as $extensionPath) {
            $controllersDir = $extensionPath . 'controllers';
            if (is_dir($controllersDir)) {
                $controllersDirs[] = $controllersDir;
            }
        }

        foreach ($controllersDirs as $dir) {
            $routes->addCollection($loader->load($dir));
            $resources[] = new FileResource($dir);
            foreach (glob($dir . '/*.php') ?: [] as $controllerFile) {
                $resources[] = new FileResource($controllerFile);
            }
        }

        return [$routes, $resources];
    }

    /**
     * Resolve/invoke $request's _controller through a real Symfony\Component\HttpKernel\HttpKernel,
     * so every request (api/doc's attribute-routed controllers via RunSpecialPages(), and ordinary
     * wiki tag/method pages via LegacyPageController) goes through the standard
     * kernel.controller/kernel.view/kernel.response/kernel.exception event flow instead of a
     * hand-rolled controller-resolution + try/catch.
     *
     * Routing itself (matching $request's attributes) stays manual: for api/doc it's YesWiki's own
     * ?wiki=Tag/method querystring scheme being translated to a path (see RunSpecialPages()), not
     * something a standard Symfony\Component\HttpKernel\EventListener\RouterListener could do
     * as-is; for ordinary pages there's no routing at all (see Run()). Either way $request already
     * carries its route attributes by the time this is called, so there's no kernel.request listener.
     */
    private function handleWithHttpKernel(Request $request): Response
    {
        if (!$this->httpKernel instanceof HttpKernel) {
            $eventDispatcher = $this->service(EventDispatcher::class);
            $eventDispatcher->addListener(KernelEvents::EXCEPTION, [$this, 'onDispatchException']);

            $this->httpKernel = new HttpKernel(
                $eventDispatcher,
                new YesWikiControllerResolver($this->services),
                null,
                new ArgumentResolver()
            );
        }

        return $this->httpKernel->handle($request);
    }

    /**
     * kernel.exception listener for handleWithHttpKernel(): maps a controller-thrown exception to
     * a Response the same way RunSpecialPages()'s try/catch used to for api/doc. For ordinary
     * pages this is only reached for bugs outside of Performer::run()'s own scope - that already
     * catches and renders exceptions from within actions/handlers/formatters as an inline "danger"
     * alert (see Performer::run()) - so today's behavior for those is an uncaught PHP fatal error;
     * this is a strict improvement (a real response instead of a blank/fatal error page), even
     * though the JSON shape below is written with the api/doc case in mind.
     */
    public function onDispatchException(ExceptionEvent $event): void
    {
        $th = $event->getThrowable();
        if ($th instanceof HttpException) {
            $event->setResponse(new Response($th->getMessage(), $th->getStatusCode(), $th->getHeaders()));
        } else {
            $event->setResponse(new ApiResponse(['exceptionMessage' => $th->__toString()], Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }

    /**
     * Load extensions from a directory.
     *
     * @return void
     */
    private function loadExtensionsFromDir($pPluginsRoot)
    {
        include_once __DIR__ . '/YesWikiPlugins.php';
        $objPlugins = new Plugins($pPluginsRoot);
        $objPlugins->getPlugins(true);
        $vExtensions = $objPlugins->getPluginsList();

        foreach ($vExtensions as $pluginName => $pluginInfo) {
            $vExtensions[$pluginName] = $pPluginsRoot . $pluginName . '/';
        }

        $this->extensions = array_merge($this->extensions, $vExtensions);
    }

    /**
     * Load extensions.
     *
     * @return void
     */
    private function loadExtensions() // make it private since once services are compiled, they cannot be modified - @YvesGufflet : contact@yvesgufflet.fr
    {
        // absolute paths: shared extensions come from the source tree (farm-wide, an
        // instance cannot write there), custom/extensions/ belongs to the instance -
        // everything downstream ($pluginBase . 'file' concatenations) works unchanged.
        // Loaded shared-first so an instance-local custom/extensions/{ext} shadows the
        // shared extensions/{ext} in the array_merge (ticket 25, formerly tools/ and
        // custom/tools/ with the exact same precedence).
        $this->loadExtensionsFromDir(YESWIKI_SOURCE_DIR . '/extensions/');
        $this->loadExtensionsFromDir(YESWIKI_INSTANCE_DIR . '/custom/extensions/');
        // TODO refactor as custom and actionsbuilder are not extensions
        $this->extensions['custom'] = YESWIKI_INSTANCE_DIR . '/custom/'; // Will load custom/actions, custom/handlers etc...
        $this->extensions['actionsbuilder'] = YESWIKI_SOURCE_DIR . '/docs/actions/'; // Will load langs inside docs/actions/lang

        $this->includeExtensionsBootstrapFiles();
        $this->boot();
        $this->loadLanguages();
        $this->enforceHerseGate();
        $this->loadTemplates();
    }

    /**
     * Site-wide HTTP Basic Auth gate (ticket 21, formerly the herse extension's
     * wiki.php bootstrap snippet): when herse_id/herse_password are configured,
     * every web request must present them as Basic Auth credentials. Runs after
     * loadLanguages() so _t() works (the extension had to manually include its
     * lang file because it ran earlier). CLI is exempt — a console command
     * cannot send Basic Auth; the extension version would have broken every
     * console run on a herse-protected wiki (disclosed adaptation).
     */
    private function enforceHerseGate(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        if (!self::herseGateAllows($this->config, $_SERVER)) {
            // the extension read wakka_name for the realm; core renamed the key
            // to yeswiki_name (old configs may still carry the legacy name)
            $realm = $this->config['yeswiki_name'] ?? $this->config['wakka_name'] ?? 'YesWiki';
            header('WWW-Authenticate: Basic realm="' . $realm . '"');
            header('HTTP/1.0 401 Unauthorized');
            echo _t('ACCESS_DENIED');
            exit;
        }
    }

    /**
     * The herse gate's pure decision: allowed when the gate is unconfigured, or
     * when the request carries the exact configured Basic Auth credentials.
     */
    public static function herseGateAllows(array $config, array $server): bool
    {
        if (empty($config['herse_id']) || empty($config['herse_password'])) {
            return true;
        }

        return isset($server['PHP_AUTH_USER'])
            && isset($server['PHP_AUTH_PW'])
            && $server['PHP_AUTH_USER'] == $config['herse_id']
            && $server['PHP_AUTH_PW'] == $config['herse_password'];
    }

    /**
     * Include each extension's wiki.php/vendor/autoload.php/libs/{key}.api.php.
     *
     * These establish global constants/functions used by legacy procedural code
     * (actions/handlers/formatters, Performer) - unlike service/parameter registration
     * (see YesWikiKernel::build()), they have to run on every request even when the
     * compiled container is served from cache, since build() is skipped on a cache hit.
     *
     * @return void
     */
    private function includeExtensionsBootstrapFiles()
    {
        // This is necessary for retrocompatibility reasons, as these variables are used by the extensions
        // TODO refactor all extensions to use the correct variable name
        // TODO remove this when the retrocompatibility is no longer necessary
        $wiki = $this;
        $page = $this->tag;
        $yeswikiConfig = &$this->config;

        foreach ($this->extensions as $k => $pluginBase) {
            // Load the initialization file (constants and includes)
            if (file_exists($pluginBase . 'wiki.php')) {
                include $pluginBase . 'wiki.php';
            }

            if (file_exists($pluginBase . 'vendor/autoload.php')) {
                include $pluginBase . 'vendor/autoload.php';
            }

            // api functions
            if (file_exists($pluginBase . 'libs/' . $k . '.api.php')) {
                include $pluginBase . 'libs/' . $k . '.api.php';
            }
        }
    }

    /**
     * Build (or reuse the compiled/cached) DI container via YesWikiKernel, then wire in the
     * synthetic services it can't dump (see src/services.yaml).
     *
     * @return void
     */
    private function boot()
    {
        $kernel = new YesWikiKernel($this, $this->environment);
        $kernel->boot();
        $this->services = $kernel->getContainer();

        $parameterBag = $this->services->getParameterBag();
        $this->services->set(ParameterBagInterface::class, $parameterBag);
        $this->services->set(CsrfTokenManager::class, new CsrfTokenManager());
        $this->services->set(Wiki::class, $this);

        // need to be executed after the container is compiled because the %paramName% are resolved there
        $this->config = $parameterBag->all();
        // one storage: element writes through either side stay visible in both
        $this->service(RuntimeConfig::class)->bind($this->config);
        $this->service(CurrentRequest::class)->replace($this->request);
        $this->service(ExtensionRegistry::class)->bind($this->extensions);
        $this->service(RouteProvider::class)->setResolver(fn () => $this->getRoutes());
    }

    /**
     * Load languages.
     *
     * @return void
     */
    private function loadLanguages()
    {
        // This must be done after service initialization, as it uses services
        $languageService = $this->service(LanguageService::class);
        $languageService->loadPreferredLanguage($this, $this->tag);

        // translations
        foreach ($this->extensions as $k => $pluginBase) {
            // language files : first default language, then preferred language
            if (file_exists($pluginBase . 'lang/' . $k . '_fr.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . '_fr.inc.php';
                $languageService->loadTranslations($returnedArray);
            }
            if (file_exists($pluginBase . 'lang/' . $k . 'js_fr.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . 'js_fr.inc.php';
                $languageService->loadTranslations($returnedArray, true);
            }
            if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php';
                $languageService->loadTranslations($returnedArray);
            }
            if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . 'js_' . $GLOBALS['prefered_language'] . '.inc.php')) {
                $returnedArray = include $pluginBase . 'lang/' . $k . 'js_' . $GLOBALS['prefered_language'] . '.inc.php';
                $languageService->loadTranslations($returnedArray, true);
            }
        }
    }

    /**
     * Load templates.
     *
     * @return void
     */
    private function loadTemplates()
    {
        $metadata = $this->service(PageManager::class)->getMetadata($this->tag);

        if (isset($metadata['lang'])) {
            $this->config['lang'] = $metadata['lang'];
        } elseif (!isset($this->config['lang'])) {
            $this->config['lang'] = 'fr';
        }

        $this->service(ThemeManager::class)->loadTemplates($metadata);
    }
}

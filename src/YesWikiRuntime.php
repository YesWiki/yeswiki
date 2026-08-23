<?php

namespace YesWiki;

require_once __DIR__ . '/bootstrap_paths.php';
require_once __DIR__ . '/constants.php';
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
use Symfony\Component\DependencyInjection\Container;
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
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiControllerResolver;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Search\Service\SearchIndexer;

// base translations and language detection (also defines YW_CHARSET); runs at load
// time, before anything (Init, the installer, error paths) calls _t()
LanguageService::getInstance()->initialize();

/**
 * The per-request runtime: bootstraps configuration, extensions, the DI container
 * and languages, then dispatches the request (historic YesWiki\Wiki, ticket 08 --
 * every service responsibility it used to carry now lives in a real service; what
 * remains here is kernel lifecycle, dispatch, and the maintenance sweep).
 */
class YesWikiRuntime
{
    /** @var array<string, mixed> */
    public $config;

    /** @var Request */
    public $request;

    // what Init derived from the URL, seeded into PageContext at boot()
    /** @var string */
    private $initialTag;

    /** @var string */
    private $initialMethod;

    /** @var string */
    public $CookiePath = '/';

    /** @var array<string, string> extension name => the folder it was loaded from */
    public $extensions = [];

    // lazily populated RouteCollection - always read it through getRoutes()
    /** @var RouteCollection|array<mixed> */
    public $routes = [];
    /**
     * The service container, assigned from the kernel in boot().
     *
     * @var ContainerInterface
     */
    public $services;

    /** @var string */
    private $environment;

    /** @var HttpKernel|null built on the first routed request, then reused */
    private $httpKernel;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config
     */
    public function __construct($config = [])
    {
        $init = new Init($config);
        $this->config = $init->config;
        $this->CookiePath = $init->initCookies();
        $this->initialTag = $init->page;
        $this->initialMethod = $init->method;

        $this->environment = defined('PHPUNIT_COMPOSER_INSTALL')
            ? 'test'
            : ($this->getConfigValue('debug') ? 'dev' : 'prod');
        $this->request = Request::createFromGlobals();

        $this->loadExtensions();
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Typed service lookup for the delegation shims: Symfony's ContainerInterface::get() is declared `?object`, so chaining a call on it fails static analysis even though every service asked for here is a compiled, always-present one.
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

    /**
     * @param string $name
     * @param mixed  $default answered when the key is missing or holds null
     *
     * @return mixed the configured value, trimmed when it is a string; '' when there is neither a value nor a default
     */
    private function getConfigValue($name, $default = null)
    {
        return isset($this->config[$name])
            ? is_array($this->config[$name]) ? $this->config[$name] : trim($this->config[$name])
            : ($default != null ? $default : '');
    }

    /**
     * Make the purge of page versions that are older than the last version older than "pages_purge_time"
     * This method permits to allways keep a not latest version that is older than that period.
     *
     * @return void
     */
    public function purgePages()
    {
        if (($days = $this->getConfigValue('pages_purge_time')) && !$this->service(HibernationService::class)->isWikiHibernated()) {
            $wnPages = $this->getConfigValue('table_prefix') . 'pages';
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
                $sql = 'DELETE FROM ' . $wnPages . ' WHERE id IN (';
                foreach ($ids as $key => $line) {
                    $sql .= ($key ? ', ' : '') . $line['id'];
                }
                $sql .= ')';

                $this->service(DbService::class)->query($sql);
            }
        }
    }

    // MAINTENANCE
    protected const MAINTENANCE_INTERVAL = 1800; // run at most once every 30 minutes
    protected const MAINTENANCE_LOCK_FILE = 'cache/maintenance.lock';

    /** When maintenance last ran, read before this run claimed the lock. */
    private ?int $previousMaintenanceRun = null;

    /**
     * The wiki's housekeeping, and the one place an extension can hang its own on.
     *
     * @return void
     */
    public function maintenance()
    {
        $startedAt = time();
        $began = microtime(true);
        $context = [
            'startedAt' => $startedAt,
            'interval' => self::MAINTENANCE_INTERVAL,
            'previousRun' => $this->previousMaintenanceRun,
        ];

        $this->service(EventDispatcher::class)->yesWikiDispatch('maintenance.before', $context);

        $this->purgePages();
        $this->service(UserManager::class)->purgeExpiredPasswordRecoveryKeys();
        $this->service(AccountActivationService::class)->purgeExpiredActivationKeys();
        $this->drainSearchIndexQueue();

        $this->service(EventDispatcher::class)->yesWikiDispatch(
            'maintenance.after',
            $context + ['duration' => microtime(true) - $began]
        );
    }

    /** The search index's fallback drain. */
    protected function drainSearchIndexQueue(): void
    {
        try {
            $this->service(SearchIndexer::class)->drain(200, 5);
        } catch (\Throwable $failed) {
        }
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
        $this->previousMaintenanceRun = $lastRun ?: null;
        if (!is_dir('cache')) {
            mkdir('cache', 0777, true);
        }
        touch(self::MAINTENANCE_LOCK_FILE);

        return true;
    }

    // THE BIG EVIL NASTY ONE!
    /**
     * @param string $tag
     * @param string $method
     *
     * @return void
     */
    public function run($tag = '', $method = '')
    {
        try {
            $this->doRun($tag, $method);
        } catch (ExitException $th) {
            if (YesWikiKernel::isCli()) {
                throw $th;
            }
            echo $th->getMessage();
        }
    }

    /**
     * @param string $tag
     * @param string $method
     *
     * @return void
     */
    private function doRun($tag, $method)
    {
        $this->service(RequestScope::class)->startNewRequest();

        if ($this->shouldRunMaintenance()) {
            $this->maintenance();
        }

        $pageContext = $this->service(PageContext::class);

        if ($tag == '') {
            $tag = $pageContext->getTag();
        }
        if ($method == '') {
            $method = $pageContext->getRawMethod();
        }

        $pageContext->setMethod(trim($method) ?: 'show');

        $tag = trim($tag);
        if (!$tag) {
            $this->service(Redirector::class)->redirect($this->service(UrlFormatter::class)->href('', $this->config['root_page']));
        }
        $pageContext->setTag($tag);
        $pageContext->setRequestedTag($tag);

        $this->applyPreferredLanguage();

        $this->service(AuthenticationService::class)->connectUser();

        if (ReservedTags::isReserved($tag)) {
            $this->runSpecialPages();
        } else {
            $request = $this->service(CurrentRequest::class)->get();
            $request->attributes->set('_controller', LegacyPageController::class);
            $request->attributes->set('_tag', $pageContext->getTag());
            $request->attributes->set('_method', $pageContext->getMethod());

            $this->handleWithHttpKernel($request)->send();

            if (!empty($_SESSION['redirects'])) {
                unset($_SESSION['redirects']);
            }
        }
    }

    // Find and run controller action based on route declaration, instead of using page Tag
    /**
     * @return void
     */
    private function runSpecialPages()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'PATCH') {
            if (empty($_POST)) {
                $rawBody = file_get_contents('php://input');
                $_POST = ($rawBody === false ? null : json_decode($rawBody, true)) ?? [];
            }
        }
        $context = new RequestContext();
        $context->fromRequest($this->service(CurrentRequest::class)->get());

        $pageContext = $this->service(PageContext::class);
        $extract = explode('&', $context->getQueryString());
        $path = $extract[0];
        if (strpos($path, '=') !== false) {
            if ($pageContext->getRawMethod() !== '') {
                if ($pageContext->getRawMethod() === 'show' && $path === 'wiki=api') {
                    $path = 'api';
                } else {
                    $path = $pageContext->getTag() . '/' . $pageContext->getRawMethod();
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

        ob_start();
        try {
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
            $this->foldRawOutputInto($response, $rawOutput);
        }
        $response->send();
    }

    /** Fold whatever a controller echoed straight to the output buffer into the response body. */
    private function foldRawOutputInto(Response $response, string $rawOutput): void
    {
        if ($response instanceof JsonResponse) {
            $json = $response->getContent();
            $previousContent = $json === false ? null : json_decode($json, true);
            $response->setData(
                is_array($previousContent)
                ? ['rawOutput' => $rawOutput] + $previousContent
                : (
                    is_string($previousContent)
                    ? $previousContent . $rawOutput
                    : $rawOutput
                )
            );

            return;
        }

        $previousContent = $response->getContent();
        $response->setContent(($previousContent === false ? '' : $previousContent) . $rawOutput);
    }

    /**
     * The attribute routes of every controller (core + extensions), loaded lazily: only api/doc requests ever match against them (see RunSpecialPages()), so ordinary page views no longer pay for reflecting over every controller class on every request.
     */
    public function getRoutes(): RouteCollection
    {
        if ($this->routes instanceof RouteCollection) {
            return $this->routes;
        }

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

        $controllersDirs = [];
        if (is_dir(__DIR__ . '/controllers')) {
            $controllersDirs[] = __DIR__ . '/controllers';
        }
        foreach (glob(__DIR__ . '/*/Controller', GLOB_ONLYDIR) ?: [] as $moduleControllersDir) {
            $controllersDirs[] = $moduleControllersDir;
        }
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
            $loaded = $loader->load($dir);
            if ($loaded !== null) {
                $routes->addCollection($loaded);
            }
            $resources[] = new FileResource($dir);
            foreach (glob($dir . '/*.php') ?: [] as $controllerFile) {
                $resources[] = new FileResource($controllerFile);
            }
        }

        return [$routes, $resources];
    }

    /**
     * Resolve/invoke $request's _controller through a real Symfony\Component\HttpKernel\HttpKernel, so every request (api/doc's attribute-routed controllers via RunSpecialPages(), and ordinary wiki tag/method pages via LegacyPageController) goes through the standard kernel.controller/kernel.view/kernel.response/kernel.exception event flow instead of a hand-rolled controller-resolution + try/catch.
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
     * kernel.exception listener for handleWithHttpKernel(): maps a controller-thrown exception to a Response the same way RunSpecialPages()'s try/catch used to for api/doc.
     */
    public function onDispatchException(ExceptionEvent $event): void
    {
        $th = $event->getThrowable();
        $exit = $this->exitExceptionIn($th);
        if ($exit !== null) {
            $event->setResponse($this->exitToResponse($exit));
        } elseif ($th instanceof HttpException) {
            $event->setResponse(new Response($th->getMessage(), $th->getStatusCode(), $th->getHeaders()));
        } else {
            $event->setResponse(new ApiResponse(['exceptionMessage' => $th->__toString()], Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }

    /** The ExitException in a throwable, however deeply it was wrapped -- or null. */
    private function exitExceptionIn(\Throwable $th): ?ExitException
    {
        for ($candidate = $th; $candidate !== null; $candidate = $candidate->getPrevious()) {
            if ($candidate instanceof ExitException) {
                return $candidate;
            }
        }

        return null;
    }

    /** `Redirector::redirect()`/`terminate()` unwinding out of a *routed* controller. */
    private function exitToResponse(ExitException $th): Response
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                return new Response('', Response::HTTP_FOUND, [
                    'Location' => trim(substr($header, strlen('Location:'))),
                ]);
            }
        }

        return new Response(YesWikiKernel::isCli() ? '' : $th->getMessage());
    }

    /**
     * Load extensions from a directory.
     *
     * @param string $pPluginsRoot
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
        $this->loadExtensionsFromDir(YESWIKI_PROGRAM_DIR . '/extensions/');
        $this->loadExtensionsFromDir(YESWIKI_INSTANCE_DIR . '/custom/extensions/');
        $this->extensions['custom'] = YESWIKI_INSTANCE_DIR . '/custom/';

        $this->includeExtensionsBootstrapFiles();
        $this->boot();
        $this->loadLanguages();
        $this->enforceHerseGate();
        $this->loadTemplates();
    }

    /**
     * Site-wide HTTP Basic Auth gate (ticket 21, formerly the herse extension's wiki.php bootstrap snippet): when herse_id/herse_password are configured, every web request must present them as Basic Auth credentials.
     */
    private function enforceHerseGate(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        if (!self::herseGateAllows($this->config, $_SERVER)) {
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
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $server the request's $_SERVER
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
     * @return void
     */
    private function includeExtensionsBootstrapFiles()
    {
        $wiki = $this;
        $page = $this->initialTag;
        $yeswikiConfig = &$this->config;

        foreach ($this->extensions as $k => $pluginBase) {
            if (file_exists($pluginBase . 'wiki.php')) {
                include $pluginBase . 'wiki.php';
            }

            if (file_exists($pluginBase . 'vendor/autoload.php')) {
                include $pluginBase . 'vendor/autoload.php';
            }

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
        $container = $kernel->getContainer();
        if (!$container instanceof Container) {
            throw new \RuntimeException('the kernel built a container with no parameter bag');
        }
        $this->services = $container;

        $parameterBag = $container->getParameterBag();
        $this->services->set(ParameterBagInterface::class, $parameterBag);
        $this->services->set(CsrfTokenManager::class, new CsrfTokenManager());
        $this->services->set(YesWikiRuntime::class, $this);
        $GLOBALS['yeswikiServices'] = $this->services;

        $this->config = $parameterBag->all();
        $this->service(RuntimeConfig::class)->bind($this->config);
        $this->service(CurrentRequest::class)->replace($this->request);
        $this->service(ExtensionRegistry::class)->bind($this->extensions);
        $this->service(RouteProvider::class)->setResolver(fn () => $this->getRoutes());
        $this->service(PageContext::class)->setTag($this->initialTag);
        $this->service(PageContext::class)->setMethod((string)$this->initialMethod);
    }

    /**
     * Load the half of the catalogue that is the same for every reader.
     *
     * @return void
     */
    private function loadLanguages()
    {
        $languageService = $this->service(LanguageService::class);

        foreach ($this->extensions as $k => $pluginBase) {
            $languageService->loadCatalogueFile($pluginBase . 'lang/' . $k . '_fr.inc.php');
            $languageService->loadCatalogueFile($pluginBase . 'lang/' . $k . 'js_fr.inc.php', true);
        }

        $languageService->rememberBaseline();
        $this->applyPreferredLanguage();
    }

    /**
     * Lay the reader's own language over the baseline, once per request.
     *
     * @return void
     */
    private function applyPreferredLanguage()
    {
        $languageService = $this->service(LanguageService::class);
        $languageService->loadPreferredLanguage($this, $this->service(PageContext::class)->getTag());

        $lang = $languageService->preferredLanguage();
        if ($lang === 'fr') {
            return;
        }

        foreach ($this->extensions as $k => $pluginBase) {
            $languageService->loadCatalogueFile($pluginBase . 'lang/' . $k . '_' . $lang . '.inc.php');
            $languageService->loadCatalogueFile($pluginBase . 'lang/' . $k . 'js_' . $lang . '.inc.php', true);
        }
    }

    /**
     * Load templates.
     *
     * @return void
     */
    private function loadTemplates()
    {
        $metadata = $this->service(PageManager::class)->getMetadata($this->service(PageContext::class)->getTag());

        if (isset($metadata['lang'])) {
            $this->config['lang'] = $metadata['lang'];
        } elseif (!isset($this->config['lang'])) {
            $this->config['lang'] = 'fr';
        }

        $this->service(ThemeManager::class)->loadTemplates($metadata);
    }
}

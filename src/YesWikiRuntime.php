<?php

namespace YesWiki\Core;

require_once __DIR__ . '/bootstrap_paths.php';
require_once __DIR__ . '/constants.php';
// defines LanguageService and the global _t() translation function; loaded
// explicitly because the autoloader may not be registered yet at this point
require_once __DIR__ . '/Kernel/Service/LanguageService.php';
require_once __DIR__ . '/YesWikiInit.php';
require_once __DIR__ . '/YesWikiKernel.php';
require_once __DIR__ . '/YesWikiPerformable.php';

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
use YesWiki\Admin\Service\MaintenanceService;
use YesWiki\Content\Controller\LegacyPageController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\ThemeManager;

// base translations and language detection (also defines YW_CHARSET); runs at load
// time, before anything (YesWikiInit, the installer, error paths) calls _t()
LanguageService::getInstance()->initialize();

/**
 * The per-request runtime: bootstraps configuration, extensions, the DI container
 * and languages, then dispatches the request (historic YesWiki\Wiki, ticket 08 --
 * every service responsibility it used to carry now lives in a real service; what
 * remains here is kernel lifecycle and dispatch).
 */
class YesWikiRuntime
{
    /** @var array<string, mixed> */
    public $config;

    /** @var YesWikiInit the boot-time reader of configuration, kept so a request can start its own session */
    private YesWikiInit $init;

    /** @var Request */
    public $request;

    // what YesWikiInit derived from the URL, seeded into PageContext at boot()
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

    /** Whether boot() has run: until it has, $services is not a container and nothing may ask it for one. */
    private bool $booted = false;

    /** Where the compiled container's per-service files are, so a worker can tell when they go. */
    private ?string $containerDirectory = null;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config
     */
    public function __construct($config = [])
    {
        $this->init = new YesWikiInit($config);
        $init = $this->init;
        $this->config = $init->config;
        $this->CookiePath = $init->initCookies();
        $this->initialTag = $init->page;
        $this->initialMethod = $init->method;

        $this->environment = defined('PHPUNIT_COMPOSER_INSTALL')
            ? 'test'
            : ($this->getConfigValue('debug') ? 'dev' : 'prod');
        $this->request = Request::createFromGlobals();

        $this->installExceptionHandler();
        $this->loadExtensions();
    }

    /**
     * Nothing had one: an uncaught exception on a production wiki went wherever the host happened to point PHP, and the webmaster saw a blank page (ADR-0025).
     *
     * Installed before the container is built, so the exceptions thrown while building it are
     * covered too -- which is why it degrades to the static stderr sink rather than asking for a
     * service that may be what failed.
     */
    private function installExceptionHandler(): void
    {
        // Not under the suite, which installs its own and reports a run that replaced it as risky.
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            return;
        }

        set_exception_handler(function (\Throwable $throwable): void {
            if ($this->exitExceptionIn($throwable) !== null) {
                return;
            }

            $this->report($throwable);

            if (YesWikiKernel::isCli()) {
                return;
            }

            if (!headers_sent()) {
                http_response_code(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            echo $this->failurePage($throwable);
        });
    }

    /**
     * What a visitor sees instead of a blank page. The detail is debug-only: the trail a webmaster needs is on stderr and in the Journal, and a production wiki does not put it on the screen.
     */
    private function failurePage(\Throwable $throwable): string
    {
        $page = '<h1>' . htmlspecialchars(_t('ERROR')) . '</h1>';

        if (!empty($this->config['debug']) && $this->containerIsBuilt()) {
            $page .= '<p>' . $this->service(ThrowableFormatter::class)->dump($throwable) . '</p>';
        }

        return $page;
    }

    /**
     * One diagnostic in the Journal and on stderr, however little of the wiki is standing.
     *
     * @return void
     */
    public function report(\Throwable $throwable)
    {
        if ($this->containerIsBuilt()) {
            try {
                $this->service(Journal::class)->error($throwable->getMessage(), ['exception' => $throwable]);

                return;
            } catch (\Throwable $noJournal) {
            }
        }

        Journal::toStderr((string)($this->config['base_url'] ?? ''), [
            'channel' => 'diagnostic',
            'level' => 'error',
            'action' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);
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

    // MAINTENANCE
    /**
     * The wiki's housekeeping, kept here as the name an extension has always called (ticket 54).
     *
     * The sweep itself is `MaintenanceService`, so `./yeswicli core:maintenance` runs the same
     * steps with an exit code rather than a second copy of them.
     *
     * @return void
     */
    public function maintenance()
    {
        $this->service(MaintenanceService::class)->sweep();
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
        $this->readThisRequest();

        if ($this->service(MaintenanceService::class)->dueOnRequest()) {
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
                $response = $this->accessRefused($context->getPathInfo());
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

    /**
     * A routed screen refusing whoever asked for it.
     *
     * `/admin/*` answered a signed-out visitor with the words "Not enough access rights" on a blank
     * page: no chrome, no way in, and no clue that signing in was the answer. A visitor with no
     * session is sent to the sign-in screen carrying where they were going, so signing in takes
     * them there. Somebody already signed in is told they may not, because offering them a sign-in
     * form for an account they are already using explains nothing.
     *
     * `/api/*` keeps the bare 401. A client asking for JSON wants a status code, not a page.
     */
    private function accessRefused(string $path): Response
    {
        $route = ltrim($path, '/');
        if ($route === 'api' || str_starts_with($route, 'api/')) {
            return new Response('Not enough access rights', Response::HTTP_UNAUTHORIZED);
        }

        if ($this->service(AuthenticationService::class)->getLoggedUser()) {
            return new Response(
                $this->service(TemplateEngine::class)->renderFullPage('@core/alert-message-with-back.twig', [
                    'type' => 'danger',
                    'message' => _t('ERROR_NO_ACCESS'),
                ]),
                Response::HTTP_FORBIDDEN
            );
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $this->service(UrlFormatter::class)->href('', 'user', [
                LoginAction::RETURN_PARAM => WikiUrls::absoluteUrl(),
            ], false),
        ]);
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
            if ($th->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                $this->report($th);
            }
            $event->setResponse(new Response($th->getMessage(), $th->getStatusCode(), $th->getHeaders()));
        } else {
            $this->report($th);
            $event->setResponse(new ApiResponse(['exceptionMessage' => $th->__toString()], Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }

    /** Whether boot() has got as far as assigning the container this all hangs off. */
    private function containerIsBuilt(): bool
    {
        return $this->booted;
    }

    /** The ExitException in a throwable, however deeply it was wrapped -- or null. */
    private function exitExceptionIn(\Throwable $th): ?ExitException
    {
        return ExitException::in($th);
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
        $objPlugins = new YesWikiPlugins($pPluginsRoot);
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
        $this->containerDirectory = $this->directoryOf($container);

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
        $this->booted = true;
    }

    /**
     * Where the container this process is running on was dumped.
     *
     * Read off the class rather than recomputed from the fingerprint: a rebuild under the same
     * fingerprint writes a *differently named* directory, so "the fingerprint directory exists" is
     * not the same question as "the files this container requires still exist".
     */
    private function directoryOf(Container $container): ?string
    {
        $file = (new \ReflectionClass($container))->getFileName();

        return $file === false ? null : \dirname($file);
    }

    /**
     * Whether the compiled container this process booted on has been deleted underneath it.
     *
     * Under php-fpm nobody can be holding one: the process ends with the request. A worker boots
     * once and serves hundreds (ADR-0024), and its container resolves services by `require`-ing a
     * file per service from the directory it was dumped into -- so `./yeswicli cache:clear`, a
     * `migrate`, or anything else that empties `cache/container` leaves that worker unable to
     * build another service for the rest of its life. Every page then fails on whichever service
     * it reaches for first, which reads as the wiki being broken rather than as a cache being
     * cleared. `worker.php` asks this between requests and stops, so the next request is served by
     * a worker that built itself a fresh container.
     */
    public function containerCacheIsGone(): bool
    {
        return $this->containerDirectory !== null && !is_dir($this->containerDirectory);
    }

    /**
     * Re-read everything derived from the request rather than from the configuration.
     *
     * A worker boots once and serves many requests, so a value read in the constructor belongs to
     * whoever arrived first: the page asked for, the request object and the session were all being
     * settled at boot and reused for every visitor afterwards (ADR-0024, single-binary 07).
     *
     * @return void
     */
    private function readThisRequest()
    {
        $this->request = Request::createFromGlobals();
        $this->service(CurrentRequest::class)->replace($this->request);

        $this->init->getRoute();
        $this->initialTag = $this->init->page;
        $this->initialMethod = $this->init->method;
        $this->service(PageContext::class)->setTag($this->initialTag);
        $this->service(PageContext::class)->setMethod((string)$this->initialMethod);

        $this->CookiePath = $this->init->initCookies();
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

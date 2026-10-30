<?php

namespace YesWiki\Core;

require_once __DIR__ . '/bootstrap_paths.php';
require_once __DIR__ . '/constants.php';
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
use YesWiki\Admin\Service\DoryphoreArrival;
use YesWiki\Content\Controller\LegacyPageController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Entity\ExtensionFolders;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\HttpCacheHeaders;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\LazySessionTokenStorage;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\BoostedNavigation;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\ThemeManager;

LanguageService::getInstance()->initialize();

/** The per-request runtime: bootstraps configuration, extensions, the DI container and languages, then dispatches the request (historic YesWiki\Wiki, ticket 08 -- every service responsibility it used to carry now lives in a real service; what remains here is kernel lifecycle and dispatch). */
class YesWikiRuntime
{
    /** @var array<string, mixed> */
    public $config;

    private YesWikiInit $init;

    /** @var Request */
    public $request;

    /** @var string */
    private $initialTag;

    /** @var string */
    private $initialMethod;

    /** @var string */
    public $CookiePath = '/';

    /** @var array<string, string> */
    public $extensions = [];

    /** @var RouteCollection|array<mixed> */
    public $routes = [];
    /** @var ContainerInterface */
    public $services;

    /** @var string */
    private $environment;

    /** @var HttpKernel|null */
    private $httpKernel;

    private bool $booted = false;

    private ?string $containerDirectory = null;

    private ?string $bootedCacheDir = null;

    private string $templatesStamp = '';

    /** @param array<string, mixed> $config */
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

    /** Nothing had one: an uncaught exception on a production wiki went wherever the host happened to point PHP, and the webmaster saw a blank page (ADR-0025). */
    private function installExceptionHandler(): void
    {
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

    /** What a visitor sees instead of a blank page. The detail is debug-only: the trail a webmaster needs is on stderr and in the Journal, and a production wiki does not put it on the screen. */
    private function failurePage(\Throwable $throwable): string
    {
        $page = '<h1>' . htmlspecialchars(_t('ERROR')) . '</h1>';

        if (!empty($this->config['debug']) && $this->containerIsBuilt()) {
            $page .= '<p>' . $this->service(ThrowableFormatter::class)->dump($throwable) . '</p>';
        }

        return $page;
    }

    /** @return void */
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
     */
    private function getConfigValue($name, mixed $default = null): mixed
    {
        return isset($this->config[$name])
            ? is_array($this->config[$name]) ? $this->config[$name] : trim($this->config[$name])
            : ($default != null ? $default : '');
    }

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
        if (!YesWikiKernel::isCli() && $this->service(DoryphoreArrival::class)->isPending() && $this->finishDoryphoreArrival()) {
            return;
        }
        $this->readThisRequest();

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
        if ($this->refuseForgedRequest(ReservedTags::isReserved($tag))) {
            return;
        }

        if (ReservedTags::isReserved($tag)) {
            $this->runSpecialPages();
        } else {
            $request = $this->service(CurrentRequest::class)->get();
            $request->attributes->set('_controller', LegacyPageController::class);
            $request->attributes->set('_tag', $pageContext->getTag());
            $request->attributes->set('_method', $pageContext->getRawMethod());

            $this->handleWithHttpKernel($request)->send();

            if (!empty($_SESSION['redirects'])) {
                unset($_SESSION['redirects']);
            }
        }
    }

    /** Answers 403 and returns true when another site, or a page without the session's token, sent a request that would change something. */
    private function refuseForgedRequest(bool $routed): bool
    {
        $refusal = $this->service(RequestGuard::class)->refusal($this->service(CurrentRequest::class)->get());
        if ($refusal === null) {
            return false;
        }
        $response = $routed
            ? new ApiResponse(['error' => $refusal], Response::HTTP_FORBIDDEN)
            : new Response(htmlspecialchars($refusal), Response::HTTP_FORBIDDEN);
        $response->send();

        return true;
    }

    /** Finishes a Doryphore wiki's move before anything reads a database it has not migrated yet, and answers whether it said something. */
    private function finishDoryphoreArrival(): bool
    {
        [$state, $messages] = $this->service(DoryphoreArrival::class)->finish();
        if ($state === DoryphoreArrival::IDLE) {
            return false;
        }
        if ($state === DoryphoreArrival::BUSY) {
            (new Response(htmlspecialchars(_t('AU_DORYPHORE_BUSY')), Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '10']))->send();

            return true;
        }
        if ($state === DoryphoreArrival::AGAIN) {
            (new CacheClearer())->clear(CacheClearer::ALL, YESWIKI_INSTANCE_DIR);
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            (new Response('', Response::HTTP_FOUND, ['Location' => $_SERVER['REQUEST_URI'] ?? '/']))->send();

            return true;
        }
        $title = htmlspecialchars(_t('AU_DORYPHORE_ARRIVAL'));
        $items = '';
        foreach ($messages->toArray() as $message) {
            $items .= '<li>' . nl2br(htmlspecialchars(str_replace('\\n', "\n", (string)($message['text'] ?? '')))) . ' : <strong>' . htmlspecialchars((string)($message['status'] ?? '')) . '</strong></li>';
        }
        $home = htmlspecialchars($this->service(UrlFormatter::class)->href('', (string)$this->config['root_page']));
        (new Response(
            "<!doctype html><html><head><meta charset=\"utf-8\"><title>{$title}</title></head><body><h1>{$title}</h1><ul>{$items}</ul><p><a href=\"{$home}\">" . htmlspecialchars(_t('AU_DORYPHORE_CONTINUE')) . '</a></p></body></html>',
            $messages->hasError() ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK
        ))->send();

        return true;
    }

    /** @return void */
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
            $pageContext->setMethod('');
            if ($this->service(ApiService::class)->isCrossOriginWrite()) {
                $response = new Response('', Response::HTTP_FORBIDDEN);
            } elseif ($this->service(ApiService::class)->isAuthorized($attributes, $this->getRoutes())) {
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

    /** A routed screen refusing whoever asked for it. */
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

    /** The attribute routes of every controller (core + extensions), loaded lazily: only api/doc requests ever match against them (see RunSpecialPages()), so ordinary page views no longer pay for reflecting over every controller class on every request. */
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

    /** @return array{0: RouteCollection, 1: array<FileResource|ConfigFileHashResource>} */
    private function buildRouteCollection(): array
    {
        $routes = new RouteCollection();
        $resources = YesWikiKernel::extensionSetResources(\dirname(__DIR__));
        $resources[] = new ConfigFileHashResource(ConfigurationFileProvider::getConfigFileFromEnv());

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

    /** Resolve/invoke $request's _controller through a real Symfony\Component\HttpKernel\HttpKernel, so every request (api/doc's attribute-routed controllers via RunSpecialPages(), and ordinary wiki tag/method pages via LegacyPageController) goes through the standard kernel.controller/kernel.view/kernel.response/kernel.exception event flow instead of a hand-rolled controller-resolution + try/catch. */
    private function handleWithHttpKernel(Request $request): Response
    {
        if (!$this->httpKernel instanceof HttpKernel) {
            $eventDispatcher = $this->service(EventDispatcher::class);
            $eventDispatcher->addListener(KernelEvents::EXCEPTION, [$this, 'onDispatchException']);
            $eventDispatcher->addListener(KernelEvents::RESPONSE, [$this->service(RequestGuard::class), 'onResponse'], 10);
            $eventDispatcher->addListener(KernelEvents::RESPONSE, [$this->service(HttpCacheHeaders::class), 'onResponse']);
            $eventDispatcher->addListener(KernelEvents::RESPONSE, [$this->service(BoostedNavigation::class), 'onResponse']);

            $this->httpKernel = new HttpKernel(
                $eventDispatcher,
                new YesWikiControllerResolver($this->services),
                null,
                new ArgumentResolver()
            );
        }

        return $this->httpKernel->handle($request);
    }

    /** kernel.exception listener for handleWithHttpKernel(): maps a controller-thrown exception to a Response the same way RunSpecialPages()'s try/catch used to for api/doc. */
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
                header_remove('Location');

                return $this->service(BoostedNavigation::class)->redirectResponse(trim(substr($header, strlen('Location:'))));
            }
        }

        return new Response(YesWikiKernel::isCli() ? '' : $th->getMessage());
    }

    /** Load the extensions this Instance switched on in `active_extensions` and does not list in `forbidden_extensions`; any other folder stays inert (ADR-0029). */
    private function loadExtensions(): void
    {
        $this->extensions = array_merge(
            $this->extensions,
            ExtensionFolders::active(ExtensionFolders::visible(YESWIKI_PROGRAM_DIR, YESWIKI_INSTANCE_DIR), $this->config['active_extensions'] ?? [], $this->config['forbidden_extensions'] ?? [])
        );
        $this->extensions['custom'] = YESWIKI_INSTANCE_DIR . '/custom/';

        $this->includeExtensionsBootstrapFiles();
        $this->boot();
        $this->loadLanguages();
        $this->enforceHerseGate();
        $this->loadTemplates();
    }

    /** Site-wide HTTP Basic Auth gate (ticket 21, formerly the herse extension's wiki.php bootstrap snippet): when herse_id/herse_password are configured, every web request must present them as Basic Auth credentials. */
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
     * @param array<string, mixed> $config
     * @param array<string, mixed> $server
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

    /** @return void */
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

    /** @return void */
    private function boot()
    {
        $kernel = new YesWikiKernel($this, $this->environment);
        $kernel->boot();
        $this->bootedCacheDir = $kernel->getCacheDir();
        $this->templatesStamp = $this->readTemplatesStamp();
        $container = $kernel->getContainer();
        if (!$container instanceof Container) {
            throw new \RuntimeException('the kernel built a container with no parameter bag');
        }
        $this->services = $container;
        $this->containerDirectory = $this->directoryOf($container);

        $parameterBag = $container->getParameterBag();
        $this->services->set(ParameterBagInterface::class, $parameterBag);
        $this->services->set(CsrfTokenManager::class, new CsrfTokenManager(null, new LazySessionTokenStorage()));
        $this->services->set(YesWikiRuntime::class, $this);
        $GLOBALS['yeswikiServices'] = $this->services;

        $this->config = $parameterBag->all();
        $this->service(RuntimeConfig::class)->bind($this->config);
        $this->service(CurrentRequest::class)->replace($this->request);
        $this->service(ExtensionRegistry::class)->bind($this->extensions);
        $this->service(RouteProvider::class)->setResolver(fn () => $this->getRoutes());
        // The same two questions worker.php asks between requests: an upgrade or `cache:clear` empties the container cache with the configuration unchanged.
        $this->service(ConfigurationWatch::class)->bind(fn (): bool => $this->containerCacheIsGone() || $this->configurationChanged());
        $this->service(PageContext::class)->setTag($this->initialTag);
        $this->service(PageContext::class)->setMethod((string)$this->initialMethod);
        $this->booted = true;
    }

    /** Where the container this process is running on was dumped. */
    private function directoryOf(Container $container): ?string
    {
        $file = (new \ReflectionClass($container))->getFileName();

        return $file === false ? null : \dirname($file);
    }

    /** Whether the compiled container this process booted on has been deleted underneath it. */
    public function containerCacheIsGone(): bool
    {
        return $this->containerDirectory !== null && !is_dir($this->containerDirectory);
    }

    /** Whether the compiled templates were cleared since this process booted, by an override saved or `cache:clear`. */
    public function templatesChanged(): bool
    {
        return $this->readTemplatesStamp() !== $this->templatesStamp;
    }

    private function readTemplatesStamp(): string
    {
        $stamp = @file_get_contents(CacheClearer::TEMPLATES_STAMP);

        return $stamp === false ? '' : $stamp;
    }

    /** Whether what this container was built from -- the configuration, `.env`, the extensions -- has changed since this process booted. */
    public function configurationChanged(): bool
    {
        return $this->bootedCacheDir !== null
            && (new YesWikiKernel($this, $this->environment))->getCacheDir() !== $this->bootedCacheDir;
    }

    /** @return void */
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

    /** @return void */
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

    /** @return void */
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

    /** @return void */
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

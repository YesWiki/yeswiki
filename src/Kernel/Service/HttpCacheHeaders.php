<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/** Keeps the session only when a request put something in it, and tells shared caches which anonymous page views they may keep. */
class HttpCacheHeaders
{
    public const TTL = 'http_cache_ttl';

    private const CACHEABLE_STATUSES = [200, 301, 404, 410];

    /** Page handlers whose anonymous answer depends only on the URL, query string included. */
    private const CACHEABLE_HANDLERS = ['', 'show', 'rss'];
    private const STALE_IF_ERROR = 86400;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly LanguageService $languageService,
    ) {
    }

    /** kernel.response listener: persist a deferred session, then choose the response's cache headers. */
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        self::persistDeferredSession();
        $this->decide($event->getRequest(), $event->getResponse());
    }

    /** Opens the session a request did not start with if it wrote something worth keeping, merging what it wrote. */
    public static function persistDeferredSession(): bool
    {
        if (session_status() !== PHP_SESSION_NONE || !self::holdsSomething($_SESSION ?? []) || headers_sent()) {
            return false;
        }
        $pending = $_SESSION;
        session_start();
        $_SESSION = array_replace($_SESSION, $pending);

        return true;
    }

    /** Whether a session array carries anything besides the empty keys vendor code seeds. */
    public static function holdsSomething(mixed $value): bool
    {
        if (!is_array($value)) {
            return $value !== null && $value !== '';
        }
        foreach ($value as $item) {
            if (self::holdsSomething($item)) {
                return true;
            }
        }

        return false;
    }

    /** Public for an anonymous page view when a TTL is configured, private and unstored whenever a session is open. */
    public function decide(Request $request, Response $response): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');

            return;
        }

        $configured = $this->params->has(self::TTL) ? $this->params->get(self::TTL) : 0;
        $ttl = is_numeric($configured) ? (int)$configured : 0;
        if ($ttl <= 0 || !$this->isAnonymousPageView($request, $response)) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge($ttl);
        $response->setStaleWhileRevalidate($ttl);
        $response->setStaleIfError(self::STALE_IF_ERROR);
        $vary = ['Cookie', 'HX-Request'];
        if (count($this->languageService->availableLanguages()) > 1) {
            $vary[] = 'Accept-Language';
        }
        $response->setVary($vary, false);
    }

    /** A GET of a page's default view or feed (the runtime tags page views with `_tag`), answered without cookies, that nothing marked no-store. */
    private function isAnonymousPageView(Request $request, Response $response): bool
    {
        if (!$request->isMethodCacheable() || !in_array($response->getStatusCode(), self::CACHEABLE_STATUSES, true)) {
            return false;
        }
        if (!$request->attributes->has('_tag') || !in_array((string)$request->attributes->get('_method'), self::CACHEABLE_HANDLERS, true)) {
            return false;
        }
        if ($response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }
        if ($response->headers->getCookies() !== []) {
            return false;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                return false;
            }
        }

        return true;
    }
}

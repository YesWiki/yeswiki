<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/** Access to the attribute-route collection (historic Wiki::getRoutes()). */
class RouteProvider
{
    /**
     * The route option of a route another site may send a state-changing request to, which the request guards let through
     * without a token: the sign-in callback, where an identity server posts its answer. What protects such a route is its own.
     */
    public const CROSS_SITE = 'cross_site';

    /**
     * @var ?callable(): RouteCollection
     */
    protected $resolver;

    public function __construct(private readonly PageContext $pageContext)
    {
    }

    /**
     * @param callable(): RouteCollection $resolver
     */
    public function setResolver(callable $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function get(): RouteCollection
    {
        if ($this->resolver === null) {
            throw new \LogicException('no route collection yet: the runtime sets it while it boots');
        }

        return ($this->resolver)();
    }

    /**
     * Whether the route the dispatcher runs for this request declares CROSS_SITE: the page and method YesWikiInit read from its
     * address (PageContext), whatever else the URL contains. Exempting anything else would open every other route to forged requests.
     */
    public function acceptsCrossSite(Request $request): bool
    {
        $tag = $this->pageContext->getRequestedTag();
        if ($this->resolver === null || $tag === '') {
            return false;
        }
        $method = $this->pageContext->getRawMethod();
        $routes = $this->get();
        try {
            $name = (new UrlMatcher($routes, new RequestContext(method: $request->getMethod())))->match('/' . $tag . ($method ? '/' . $method : ''))['_route'] ?? null;
        } catch (ExceptionInterface) {
            return false;
        }

        return is_string($name) && $routes->get($name)?->getOption(self::CROSS_SITE) === true;
    }
}

<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\Routing\RouteCollection;

/**
 * Access to the attribute-route collection (historic Wiki::getRoutes()). The
 * collection is built and cached by the dispatch layer, which installs itself
 * here at boot -- lazily, so ordinary page views still never pay for it.
 */
class RouteProvider
{
    /** @var callable(): RouteCollection */
    protected $resolver;

    /** @param callable(): RouteCollection $resolver */
    public function setResolver(callable $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function get(): RouteCollection
    {
        return ($this->resolver)();
    }
}

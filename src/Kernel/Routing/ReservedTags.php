<?php

namespace YesWiki\Kernel\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * The tags no Content may take, because a route already answers to them (ticket 20, ADR-0001 as amended).
 */
final class ReservedTags
{
    /**
     * Every first path segment the router owns.
     *
     * @var list<string>
     */
    public const NAMES = ['admin', 'api', 'dashboard', 'doc', 'search', 'user'];

    /** Whether a name belongs to the router rather than to Content. */
    public static function isReserved(string $tag): bool
    {
        return in_array(self::canonical(explode('/', $tag)[0]), self::NAMES, true);
    }

    /**
     * The spelling a reserved tag is dispatched under, so `?API/...` and `?api/...` reach the same controller instead of one of them falling through to a page lookup.
     */
    public static function canonical(string $tag): string
    {
        return mb_strtolower(trim($tag));
    }

    /**
     * The reserved names implied by an actual RouteCollection: the first path segment of every route.
     *
     * @return list<string>
     */
    public static function fromRoutes(RouteCollection $routes): array
    {
        $names = [];
        foreach ($routes->all() as $route) {
            $first = explode('/', ltrim($route->getPath(), '/'))[0];

            if ($first === '' || str_starts_with($first, '{')) {
                continue;
            }
            $names[self::canonical($first)] = true;
        }

        return array_keys($names);
    }
}

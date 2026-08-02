<?php

namespace YesWiki\Kernel\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * The tags no Content may take, because a route already answers to them (ticket 20,
 * ADR-0001 as amended).
 *
 * ADR-0001 made a tag unique across Content types. It said nothing about a tag colliding
 * with a *route*, and the two ends of that gap had drifted apart: dispatch reserved
 * `['api', 'doc']` in one hardcoded list (YesWikiRuntime) while URL parsing reserved
 * everything matching `^api` in another (YesWikiInit) -- so `apiculture` was parsed as the
 * API with method `culture`, and nothing anywhere stopped a page, form or user from being
 * created on a reserved tag in the first place. This class is the one declaration both ends
 * now read.
 *
 * ## Why a declared constant rather than the route table itself
 *
 * `fromRoutes()` below derives the same list from the real RouteCollection, and
 * ReservedTagsTest asserts the two agree -- so the constant cannot drift from routing
 * without a test failing. It is not read at runtime because dispatch consults this on every
 * single request, and building the route collection means reflecting over every controller
 * class in core and every extension. Ticket 08 deliberately made that lazy so ordinary page
 * views stop paying for it; deriving the reserved list live would hand the cost straight
 * back. A constant plus a test buys the same guarantee for free.
 *
 * ## Case
 *
 * Matching is case-insensitive. MySQL's default collation is case-insensitive, so on a
 * MySQL wiki a page tagged `Api` already answers to `getOne('api')` -- reserving only the
 * exact lowercase spelling would leave the collision reachable on the most common install
 * while closing it on SQLite. The canonical spelling is the lowercase one.
 *
 * ## Scope: first segment only
 *
 * A URL is `?Tag/handler`, so only the *first* segment competes with a tag. Handler names
 * (`edit`, `raw`, `xml`, `revisions`, `iframe`, ...) live in the second segment and cannot
 * collide: a page tagged `edit` is `?edit`, and its editor is `?edit/edit`. They are
 * deliberately not reserved.
 */
final class ReservedTags
{
    /**
     * Every first path segment the router owns. Kept in sync with the route table by
     * ReservedTagsTest, not by hand.
     *
     * @var list<string>
     */
    public const NAMES = ['api', 'doc', 'search'];

    public static function isReserved(string $tag): bool
    {
        return in_array(self::canonical($tag), self::NAMES, true);
    }

    /**
     * The spelling a reserved tag is dispatched under, so `?API/...` and `?api/...` reach
     * the same controller instead of one of them falling through to a page lookup.
     */
    public static function canonical(string $tag): string
    {
        return mb_strtolower(trim($tag));
    }

    /**
     * The reserved names implied by an actual RouteCollection: the first path segment of
     * every route. Used by the test that guards NAMES, and by the `tags:reserved` check.
     *
     * @return list<string>
     */
    public static function fromRoutes(RouteCollection $routes): array
    {
        $names = [];
        foreach ($routes->all() as $route) {
            $first = explode('/', ltrim($route->getPath(), '/'))[0];
            // a route whose first segment is itself a placeholder (`/{tag}/...`) reserves
            // nothing -- it matches everything, and reserving `{tag}` would be nonsense
            if ($first === '' || str_starts_with($first, '{')) {
                continue;
            }
            $names[self::canonical($first)] = true;
        }

        return array_keys($names);
    }
}

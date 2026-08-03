<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use YesWiki\Kernel\Routing\ReservedTags;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 20: the declared reserved list, and the guard that stops it drifting away from
 * the routes it is supposed to mirror.
 */
class ReservedTagsTest extends TestCase
{
    public function testTheDeclaredListMatchesTheRealRouteTable(): void
    {
        $routes = $this->realRouteCollection();
        if ($routes === null) {
            $this->markTestSkipped('no built route cache to compare against (run any api/doc request first)');
        }

        $fromRoutes = ReservedTags::fromRoutes($routes);
        sort($fromRoutes);
        $declared = ReservedTags::NAMES;
        sort($declared);

        $this->assertSame(
            $declared,
            $fromRoutes,
            'ReservedTags::NAMES has drifted from the route table. It is a constant rather than a '
            . 'live derivation because dispatch reads it on every request and building the route '
            . 'collection is expensive -- this test is the price of that. Add or remove the names '
            . 'listed in the diff.'
        );
    }

    public function testReservedNamesAreRefusedWhateverTheirCase(): void
    {
        // MySQL's default collation is case-insensitive, so a page tagged `Api` already
        // answers to a lookup for `api`; reserving only one spelling would close the hole
        // on SQLite and leave it open on the commonest install
        $this->assertTrue(ReservedTags::isReserved('api'));
        $this->assertTrue(ReservedTags::isReserved('Api'));
        $this->assertTrue(ReservedTags::isReserved('API'));
        $this->assertTrue(ReservedTags::isReserved('doc'));
        $this->assertTrue(ReservedTags::isReserved('Doc'));
    }

    /**
     * The bug the old `preg_match('^api')` prefix match caused: a page tagged `apiculture`
     * was parsed as the API with method `culture`, so it could never be reached.
     */
    public function testATagMerelyStartingWithAReservedNameIsNotReserved(): void
    {
        $this->assertFalse(ReservedTags::isReserved('apiculture'));
        $this->assertFalse(ReservedTags::isReserved('ApiDocumentation'));
        $this->assertFalse(ReservedTags::isReserved('documentation'));
        $this->assertFalse(ReservedTags::isReserved('api2'));
    }

    /**
     * A URL is `?Tag/handler`, so handler names live in the second segment and never
     * compete with a tag: a page tagged `edit` is `?edit`, and its editor is `?edit/edit`.
     */
    public function testHandlerNamesAreNotReserved(): void
    {
        foreach (['edit', 'raw', 'xml', 'revisions', 'iframe', 'show', 'deletepage'] as $handler) {
            $this->assertFalse(
                ReservedTags::isReserved($handler),
                "handler name '{$handler}' must stay usable as a tag -- it lives in the second URL segment"
            );
        }
    }

    public function testSurroundingWhitespaceDoesNotSmuggleAReservedTagThrough(): void
    {
        $this->assertTrue(ReservedTags::isReserved(' api '));
        $this->assertSame('api', ReservedTags::canonical("  API\t"));
    }

    public function testAPlaceholderFirstSegmentReservesNothing(): void
    {
        $routes = new RouteCollection();
        $routes->add('catchall', new Route('/{tag}/edit'));
        $routes->add('real', new Route('/search/results'));

        $this->assertSame(['search'], ReservedTags::fromRoutes($routes));
    }

    public function testDerivationDeduplicatesAndLowercases(): void
    {
        $routes = new RouteCollection();
        $routes->add('a', new Route('/api/forms'));
        $routes->add('b', new Route('/api/entries/{id}'));
        $routes->add('c', new Route('/API/legacy'));

        $this->assertSame(['api'], ReservedTags::fromRoutes($routes));
    }

    /** The route cache the app itself uses, if one has been built. */
    private function realRouteCollection(): ?RouteCollection
    {
        foreach (glob(__DIR__ . '/../../../cache/routes/*.php') ?: [] as $file) {
            $routes = @unserialize(require $file);
            if ($routes instanceof RouteCollection && count($routes) > 0) {
                return $routes;
            }
        }

        return null;
    }

    /**
     * A routed screen with more than one segment names itself by its whole path
     * (`dashboard/forms`), because an action linking to "this page" reads that name. It is
     * still the router's, not Content's: the edit bar renders on a page, and a route that
     * did not answer to this offered "edit this page" for a tag nothing can ever occupy.
     */
    public function testAMultiSegmentRouteIsReservedByItsFirstSegment(): void
    {
        $this->assertTrue(ReservedTags::isReserved('dashboard/forms'));
        $this->assertTrue(ReservedTags::isReserved('admin/users'));
        $this->assertTrue(ReservedTags::isReserved('API/forms/1'), 'matching stays case-insensitive');
        // a tag merely starting with a reserved name is ordinary, path or not
        $this->assertFalse(ReservedTags::isReserved('apiculture'));
        $this->assertFalse(ReservedTags::isReserved('dashboards/forms'));
    }
}

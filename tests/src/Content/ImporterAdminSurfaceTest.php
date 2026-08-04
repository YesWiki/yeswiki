<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * The surfaces an admin actually reaches the importers through.
 *
 * Route and action discovery are both directory-driven, and both fail quietly: an action that
 * is not registered renders as literal text in the page, and a route that is not discovered
 * answers an empty body rather than an error (the afternoon ADR-0013 records). Neither shows
 * up in a unit test of the code behind them, so the wiring is asserted on its own.
 */
class ImporterAdminSurfaceTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testBothActionsAreRegisteredUnderTheirStatedNames(YesWikiRuntime $wiki): void
    {
        $registry = $wiki->services->get(ActionRegistry::class);

        $this->assertTrue($registry->has('action', 'adminimporters'), '{{adminimporters}} is not an action');
        $this->assertTrue($registry->has('action', 'sync'), '{{sync}} is not an action');
    }

    #[Depends('testWikiExisting')]
    public function testTheAdminTemplatesResolve(YesWikiRuntime $wiki): void
    {
        $twig = $wiki->services->get(TemplateEngine::class);

        $this->assertTrue($twig->hasTemplate('@core/admin-importers.twig'));
        $this->assertTrue($twig->hasTemplate('@core/importer-sync.twig'));
    }

    #[Depends('testWikiExisting')]
    public function testTheSyncRouteIsDiscovered(YesWikiRuntime $wiki): void
    {
        $paths = [];
        foreach ($wiki->getRoutes() as $route) {
            $paths[] = $route->getPath();
        }

        $this->assertContains('/api/import/sync', $paths);
        $this->assertContains('/api/import/mapping-fields', $paths);
    }
}

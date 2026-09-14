<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The surfaces an admin actually reaches the importers through. */
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
    public function testTheTargetFormSelectIsBuiltFromFormLabels(YesWikiRuntime $wiki): void
    {
        $html = $wiki->services->get(TemplateEngine::class)->render('@core/admin-importers.twig', [
            'currentUrl' => '/?AdminImporters',
            'lastSync' => [],
            'importers' => [],
            'importerFields' => [],
            'importersWithoutForm' => [],
            'importersWithFieldMapping' => [],
            'forms' => $wiki->services->get(FormManager::class)->getAllLabels(),
            'dataSources' => [],
            'dataSourcesJson' => '{}',
            'message' => null,
            'syncOutput' => null,
            'syncedSourceId' => null,
        ]);

        foreach ($wiki->services->get(FormManager::class)->getAllLabels() as $formId => $label) {
            $this->assertStringContainsString(
                '<option value="' . $formId . '">' . htmlspecialchars((string)$label, ENT_QUOTES) . ' (' . $formId . ')</option>',
                $html,
                "form {$formId} is not offered as a target form"
            );
        }
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

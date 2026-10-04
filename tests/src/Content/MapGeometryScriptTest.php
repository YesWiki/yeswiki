<?php

namespace YesWiki\Test\Content;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Action\EntryListAction;
use YesWiki\Kernel\Asset\AssetEntry;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The static map draws each entry's geometries in a block of its own, so one broken geometry leaves the others on the map. */
class MapGeometryScriptTest extends YesWikiTestCase
{
    private const VALID = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[2.35,48.85]}}]}';
    private const NOT_GEOJSON = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null}]}';
    private const TRUNCATED = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polyg';

    private function mapScript(): string
    {
        $wiki = $this->getWiki();
        $action = new EntryListAction();
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(ParameterBagInterface::class));
        $action->setTwig($wiki->services->get(TemplateEngine::class));
        $arguments = ['template' => 'map'];
        $action->setArguments($arguments);
        $params = (new \ReflectionProperty(EntryListAction::class, 'arguments'))->getValue($action);
        $params['listindex'] = 7;

        $entries = [];
        foreach (['GeoValide' => self::VALID, 'GeoSansForme' => self::NOT_GEOJSON, 'GeoTronquee' => self::TRUNCATED] as $tag => $geometries) {
            $entries[] = ['tag' => $tag, 'bf_titre' => $tag, 'html_data' => "data-id=\"{$tag}\"", 'bf_geolocation' => ['geometries' => $geometries]];
        }
        $renderMap = new \ReflectionMethod(EntryListAction::class, 'renderMap');
        $assets = $wiki->services->get(AssetRegistry::class)->capture(
            fn () => $renderMap->invoke($action, ['params' => $params, 'entries' => $entries, 'resultsInfo' => ''])
        );

        foreach ($assets->entries() as $asset) {
            if ($asset->kind === AssetEntry::JS_INLINE && str_contains($asset->payload, 'drawGeometries')) {
                return $asset->payload;
            }
        }
        $this->fail('the map declared no script drawing geometries');
    }

    public function testEachReadableGeometryIsDrawnInItsOwnTryBlock(): void
    {
        $script = $this->mapScript();

        $this->assertStringContainsString('"coordinates":[2.35,48.85]', $script);
        $this->assertStringContainsString('drawGeometries(drawnFeatures, geo.features, popup, "GeoValide")', $script);
        $this->assertStringContainsString('drawGeometries(drawnFeatures, geo.features, popup, "GeoSansForme")', $script);
        $this->assertSame(2, substr_count($script, "try {\n"));
        $this->assertSame(2, substr_count($script, '} catch (e) { console.error("Error drawing geometry for " + '));
    }

    public function testAGeometryThatIsNotJsonIsLeftOutRatherThanBreakingTheScript(): void
    {
        $script = $this->mapScript();

        $this->assertStringNotContainsString('"Polyg', $script);
        $this->assertStringContainsString('console.error("Unreadable geometry for " + "GeoTronquee")', $script);
    }

    public function testTheGeneratedScriptParses(): void
    {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed');
        }
        $file = tempnam(sys_get_temp_dir(), 'yw-map-') . '.mjs';
        file_put_contents($file, $this->mapScript());
        try {
            exec(escapeshellarg($node) . ' --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        } finally {
            unlink($file);
        }

        $this->assertSame(0, $status, implode("\n", $output));
    }
}

<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredPerformable;
use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Render\Component\SourceRegistry;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The seam ticket 37 exists to create: a Source declares itself and every Presentation
 * offers it, without a presentation being edited.
 *
 * These are drift checks. Each one fails on the way the seam would realistically be lost --
 * a Source added but not tagged, a Presentation that hard-codes the sources it knows, a
 * source setting that quietly becomes a written parameter.
 */
class SourceRegistryTest extends YesWikiTestCase
{
    private static function sources(): SourceRegistry
    {
        return self::getWiki()->services->get(SourceRegistry::class);
    }

    /** @return array<string, array<string, mixed>> */
    private static function presentations(): array
    {
        $components = self::getWiki()->services->get(ComponentRegistry::class)->byId();

        return array_filter(
            $components,
            static fn (string $id) => str_starts_with($id, 'presentation-'),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function testEveryActionThatSuppliesItemsIsRegisteredAsASource(): void
    {
        /** @var list<string> $declared */
        $declared = [];
        foreach (glob('src/*/Action/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = 'YesWiki\\' . str_replace('/', '\\', substr($file, 4, -4));
            if (
                !class_exists($class)
                || !is_a($class, SuppliesItems::class, true)
                || !is_a($class, RegisteredPerformable::class, true)
            ) {
                continue;
            }
            $declared[] = $class::performableName();
        }
        sort($declared);

        $registered = self::sources()->tags();
        sort($registered);

        $this->assertSame(
            $declared,
            $registered,
            'an action implementing SuppliesItems is a Source; if it is missing here the '
            . '`yeswiki.item_source` tag in services.yaml stopped matching'
        );
    }

    public function testEveryPresentationOffersEverySource(): void
    {
        $tags = self::sources()->tags();
        $this->assertNotEmpty($tags);

        foreach (self::presentations() as $id => $component) {
            $this->assertSame(
                $tags,
                $component['tags'],
                "{$id} writes every Source's tag -- which one depends on the source picked"
            );
            $this->assertSame(
                $tags,
                array_keys($component['properties']['source']['options'] ?? []),
                "{$id} offers every Source"
            );
        }
    }

    /** There is one Presentation per shape the renderer knows, and no more. */
    public function testThePaletteOffersExactlyTheShapesTheRendererCanDraw(): void
    {
        $offered = array_map(
            static fn (string $id) => substr($id, strlen('presentation-')),
            array_keys(self::presentations())
        );
        sort($offered);

        $known = PresentationRenderer::PRESENTATIONS;
        sort($known);

        $this->assertSame($known, $offered);
    }

    /**
     * The source setting decides which TAG is written, so writing it as a parameter would
     * put `source="syndication"` inside `{{syndication}}` -- and `source` already means
     * something else there (the feed's name).
     */
    public function testTheSourceSettingIsNotWrittenIntoTheTag(): void
    {
        foreach (self::presentations() as $id => $component) {
            $source = $component['properties']['source'] ?? [];
            $this->assertTrue($source['decidesTag'] ?? false, "{$id}");
            $this->assertFalse($source['mapped'] ?? true, "{$id} must not write `source=`");
        }
    }

    /**
     * A Source's own settings are shown only when it is the one chosen. Without this every
     * presentation would show a feed url, a form picker and a page filter at once.
     */
    public function testEachSourceSettingIsShownOnlyForItsOwnSource(): void
    {
        $card = self::presentations()['presentation-card'];

        foreach (self::sources()->all() as $source) {
            foreach ($source['settings'] as $setting) {
                $this->assertInstanceOf(Setting::class, $setting);
                $declared = $card['properties'][$setting->name()] ?? null;
                $this->assertNotNull($declared, "{$source['tag']}'s `{$setting->name()}` is offered");
                $this->assertSame(
                    ['source' => $source['tag']],
                    $declared['showif'] ?? null,
                    "{$source['tag']}'s `{$setting->name()}` is shown only for it"
                );
            }
        }
    }
}

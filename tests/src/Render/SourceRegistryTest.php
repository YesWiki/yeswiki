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
 * The seam ticket 37 exists to create: a Source declares itself and every Presentation offers it, without a presentation being edited.
 */
class SourceRegistryTest extends YesWikiTestCase
{
    private static function sources(): SourceRegistry
    {
        return self::getWiki()->services->get(SourceRegistry::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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
        /**
         * @var list<string> $declared
         */
        $declared = [];
        foreach (glob('src/*/Action/*.php') ?: [] as $file) {
            /**
             * @var class-string $class
             */
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
     * The source setting decides which TAG is written, so writing it as a parameter would put `source="syndication"` inside `{{syndication}}` -- and `source` already means something else there (the feed's name).
     */
    public function testTheSourceSettingIsNotWrittenIntoTheTag(): void
    {
        foreach (self::presentations() as $id => $component) {
            $source = $component['properties']['source'] ?? [];
            $this->assertTrue($source['decidesTag'] ?? false, "{$id}");
            $this->assertFalse($source['mapped'] ?? true, "{$id} must not write `source=`");
        }
    }

    /** A Source's own settings are shown only when it is the one chosen. */
    public function testEachSourceSettingIsShownOnlyForTheSourcesThatDeclareIt(): void
    {
        $card = self::presentations()['presentation-card'];
        $sources = self::sources()->all();

        foreach ($sources as $source) {
            foreach ($source['settings'] as $setting) {
                $this->assertInstanceOf(Setting::class, $setting);
                $name = $setting->name();
                $declared = $card['properties'][$name] ?? null;
                $this->assertNotNull($declared, "{$source['tag']}'s `{$name}` is offered");

                $shownFor = explode('|', (string)($declared['showif']['source'] ?? ''));
                $this->assertContains(
                    $source['tag'],
                    $shownFor,
                    "{$source['tag']}'s `{$name}` is shown when it is the source"
                );
                foreach ($shownFor as $tag) {
                    $this->assertTrue(
                        self::declares($sources, $tag, $name),
                        "`{$name}` is shown for {$tag}, which does not declare it"
                    );
                }
            }
        }
    }

    /** A Presentation over a form can be told everything `{{entrylist}}` could. */
    public function testAPresentationOverAFormCanBeToldWhichEntriesAndInWhatOrder(): void
    {
        $properties = self::presentations()['presentation-card']['properties'];

        foreach (['query', 'nb', 'pagination', 'field', 'order'] as $name) {
            $this->assertArrayHasKey($name, $properties, "a card list can be told `{$name}`");
            $this->assertStringContainsString(
                'entrylist',
                (string)($properties[$name]['showif']['source'] ?? ''),
                "`{$name}` is shown when a form is what is listed"
            );
        }

        $this->assertSame('query', $properties['query']['type'], 'and `query` is built, not typed');
    }

    /** A slot offers the kinds of field that can play its part, and no others. */
    public function testTheSlotsThatTakeOneKindOfFieldSayWhichKind(): void
    {
        $slots = self::presentations()['presentation-card']['properties']['displayfields']['subproperties'];

        $this->assertSame(['text'], $slots['title']['fieldTypes'] ?? null);
        $this->assertSame(['image'], $slots['visual']['fieldTypes'] ?? null);
        $this->assertSame(
            ['jour', 'listedatedeb', 'listedatefin'],
            $slots['date']['fieldTypes'] ?? null
        );

        $this->assertSame(['created_at', 'updated_at'], $slots['date']['extraFields'] ?? null);
        $this->assertSame(['title'], $slots['title']['extraFields'] ?? null);

        $this->assertArrayNotHasKey('fieldTypes', $slots['description']);
        $this->assertArrayNotHasKey('fieldTypes', $slots['subtitle']);
    }

    /** ...the boxes a reader narrows it with included. */
    public function testAPresentationOverAFormCanOfferFacets(): void
    {
        $properties = self::presentations()['presentation-card']['properties'];

        foreach (['facets', 'filterposition', 'groupsexpanded', 'resetfiltersbutton'] as $name) {
            $this->assertArrayHasKey($name, $properties, "a card list can be told `{$name}`");
            $this->assertStringContainsString(
                'entrylist',
                (string)($properties[$name]['showif']['source'] ?? ''),
                "`{$name}` is shown when a form is what is listed"
            );
        }

        $this->assertSame(
            ['left', 'right', 'top'],
            array_keys($properties['filterposition']['options'] ?? []),
            'and they can be laid out above the list, not only beside it'
        );
    }

    /** The condition a Source's setting was declared with survives being folded in. */
    public function testASourceSettingKeepsItsOwnCondition(): void
    {
        $properties = self::presentations()['presentation-card']['properties'];

        $this->assertSame(
            ['random' => '', 'source' => 'entrylist'],
            $properties['order']['showif'] ?? null
        );
    }

    /**
     * @param list<array{tag: string, label: string, settings: list<Setting>}> $sources
     */
    private static function declares(array $sources, string $tag, string $name): bool
    {
        foreach ($sources as $source) {
            if ($source['tag'] !== $tag) {
                continue;
            }
            foreach ($source['settings'] as $setting) {
                if ($setting->name() === $name) {
                    return true;
                }
            }
        }

        return false;
    }
}

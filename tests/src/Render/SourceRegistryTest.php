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
     *
     * "Its own" is per setting, not per Source: two of them may want the same parameter --
     * `nb` is "how many" to a form and to a feed alike -- and then it is shown for both.
     * What must never happen is a setting shown for a Source that does not declare it,
     * which is what a Component holding its settings by name did to the first declaration.
     */
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

    /**
     * A Presentation over a form can be told everything `{{entrylist}}` could.
     *
     * Which entries, how many, in what order is the Source's question -- so a Cards is not
     * a lesser list than the accordion one it replaced in the palette. It shipped as one:
     * the presentations carried the form picker and the field mapping and nothing else, so
     * a card list could not be filtered, limited, paginated or sorted at all.
     */
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

    /**
     * A slot offers the kinds of field that can play its part, and no others.
     *
     * Every field of the form in every slot is a choice that mostly does not work: an
     * image slot filled with `bf_description` renders a broken picture and says nothing
     * about why, and a date slot filled with a text field renders whatever that text is.
     */
    public function testTheSlotsThatTakeOneKindOfFieldSayWhichKind(): void
    {
        $slots = self::presentations()['presentation-card']['properties']['displayfields']['subproperties'];

        $this->assertSame(['text'], $slots['title']['fieldTypes'] ?? null);
        $this->assertSame(['image'], $slots['visual']['fieldTypes'] ?? null);
        $this->assertSame(
            ['jour', 'listedatedeb', 'listedatefin'],
            $slots['date']['fieldTypes'] ?? null
        );
        // ...and the pseudo-fields a slot asked for come through the filter, since they
        // have no type of their own
        $this->assertSame(['created_at', 'updated_at'], $slots['date']['extraFields'] ?? null);
        $this->assertSame(['title'], $slots['title']['extraFields'] ?? null);

        // the ones that take prose take what they are given
        $this->assertArrayNotHasKey('fieldTypes', $slots['description']);
        $this->assertArrayNotHasKey('fieldTypes', $slots['subtitle']);
    }

    /**
     * ...the boxes a reader narrows it with included.
     *
     * The facets are declared over a form's fields, so they were offered on the entrylist
     * family's own panel and nowhere else -- while `entries/index.twig` draws them around
     * whatever a presentation rendered. A card list therefore had facets that could not be
     * turned on, and a `{{entrylist template="card" groups="bf_type"}}` written by hand got
     * boxes that filtered nothing (the browser hid `.bazar-entry` elements, which a card is
     * not).
     */
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

    /**
     * The condition a Source's setting was declared with survives being folded in.
     *
     * `showIf()` replaces, so the "when the source is a form" condition used to throw away
     * the setting's own: "which way to sort" would have been offered beside "in a random
     * order", where it does nothing.
     */
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

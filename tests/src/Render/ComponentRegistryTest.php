<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The palette's declaration, and the drift checks YAML never had (ticket 36).
 */
class ComponentRegistryTest extends YesWikiTestCase
{
    private static function registry(): ComponentRegistry
    {
        return self::getWiki()->services->get(ComponentRegistry::class);
    }

    public function testProvidersAreDiscoveredByTagAlone(): void
    {
        $ids = array_map(static fn ($c) => $c->id(), self::registry()->all());

        $this->assertContains('section', $ids, 'a tagged provider enrols by implementing the interface');
    }

    /**
     * The rule the whole model rests on: a Component writes a `{{tag}}`. Asserted rather
     * than assumed, so the exception the callouts nearly became cannot creep back -- see
     * ADR-0017.
     */
    public function testEveryComponentWritesAtLeastOneTag(): void
    {
        foreach (self::registry()->all() as $component) {
            $this->assertNotEmpty(
                $component->tags(),
                "Component '{$component->id()}' writes no tag. Markup syntax belongs in the "
                . 'editor toolbars, not in the palette.'
            );
        }
    }

    /**
     * A pinned setting must name a parameter its action actually reads.
     *
     * This is the check the YAML never had, and its absence is why sixteen palette entries
     * pointed at actions that no longer existed while nobody noticed. A pin that names
     * nothing writes a parameter the action ignores -- the component looks inserted and
     * does nothing.
     */
    public function testEveryPinNamesAParameterItsActionReads(): void
    {
        $components = self::registry()->all();
        // or the sweep below passes by looking at nothing, which is how the YAML managed to
        // hold sixteen entries pointing at actions that no longer existed
        $this->assertNotEmpty($components, 'no Components were discovered at all');

        foreach ($components as $component) {
            foreach (array_keys($component->pins()) as $parameter) {
                foreach ($component->tags() as $tag) {
                    $this->assertTrue(
                        $this->actionReadsParameter($tag, $parameter),
                        "Component '{$component->id()}' pins '{$parameter}', but {{{$tag}}} never reads it."
                    );
                }
            }
        }
    }

    /**
     * No label, hint or option renders as its own translation key.
     *
     * `_t()` returns the key it was given when nothing defines it, so a mistyped key does
     * not fail -- it renders as `AB_templates_actions_class` in the rail and waits for
     * someone to notice. This caught exactly that during the port (a `templates_` for a
     * `template_`), which is a mistake YAML could make just as easily and nothing checked.
     */
    public function testNoLabelIsAnUntranslatedKey(): void
    {
        $unresolved = [];
        $walk = static function (mixed $value, string $path) use (&$walk, &$unresolved): void {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $walk($child, "$path.$key");
                }

                return;
            }
            // a key is SHOUTY_SNAKE_CASE and nothing a human would write as a label
            if (is_string($value) && preg_match('/^[A-Z][A-Za-z0-9]*(_[A-Za-z0-9]+){2,}$/', $value) === 1) {
                $unresolved[] = "$path = $value";
            }
        };

        foreach (self::registry()->all() as $component) {
            $walk($component->toArray(), $component->id());
        }

        $this->assertSame([], $unresolved, "translation keys reaching the palette untranslated:\n"
            . implode("\n", $unresolved));
    }

    public function testCategoriesOrderThePalette(): void
    {
        $positions = array_map(
            static fn (array $group) => Category::from($group['id'])->position(),
            self::registry()->palette()
        );

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'the palette comes out in category order');
    }

    /** Most pins wins; an unpinned Component for the same tag is the fallback. */
    public function testMatchingPrefersTheMostSpecificComponent(): void
    {
        $registry = self::registry();

        $this->assertSame('section', $registry->match('section', [])?->id());
        $this->assertNull($registry->match('no-such-action-exists', []));
    }

    public function testAComponentIsImmutableSoSharedSettingsCannotBeEditedByOneUser(): void
    {
        $base = Component::for('x')->label('first');
        $other = $base->label('second');

        $this->assertNotSame($base, $other);
        $this->assertSame('first', $base->toArray()['label']);
        $this->assertSame('second', $other->toArray()['label']);
    }

    public function testASettingIsImmutableToo(): void
    {
        $base = Setting::text('n')->label('first');
        $other = $base->label('second');

        $this->assertSame('first', $base->toArray()['label']);
        $this->assertSame('second', $other->toArray()['label']);
    }

    /**
     * Whether `{{tag}}`'s class ever looks at `$parameter`.
     *
     * Reads the source rather than running the action: an action reads its parameters
     * through `$this->arguments[...]` and `$arg[...]`, which is a literal in the file, and
     * running one would need a page, a request and a form.
     */
    private function actionReadsParameter(string $tag, string $parameter): bool
    {
        $registry = self::getWiki()->services->get(\YesWiki\Kernel\Performable\ActionRegistry::class);
        $action = $registry->get('action', $tag);
        if ($action === null) {
            return false;
        }
        $file = (new \ReflectionClass($action))->getFileName();
        $source = $file === false ? false : file_get_contents($file);

        return $source !== false
            && preg_match('/[\'"]' . preg_quote($parameter, '/') . '[\'"]/', $source) === 1;
    }
}

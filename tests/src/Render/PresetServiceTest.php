<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The preset vocabulary (ticket 30).
 *
 * Everything asserted here is a *query*: what presets exist, what a file says, what a typed
 * name would become. The three writers -- select(), save(), delete() -- rewrite the wiki's
 * configuration file and the contents of custom/css-presets/, so they are exercised through
 * the browser (tests/e2e/tests/preset.spec.ts) rather than against the working tree. What is
 * covered here instead is that each of them refuses the case that would do damage, since
 * every one of those refusals happens before anything is written.
 */
class PresetServiceTest extends YesWikiTestCase
{
    private function service(): PresetService
    {
        return $this->getWiki()->services->get(PresetService::class);
    }

    public function testTheShippedPresetsAreFound(): void
    {
        $names = array_column($this->service()->all(), 'name');

        // the five the yeswiki theme ships; a theme losing its presets would leave the
        // screen with nothing but "no preset" and look like a rendering bug
        foreach (['default', 'fun', 'landes', 'red', 'yellow'] as $preset) {
            $this->assertContains($preset, $names);
        }
    }

    public function testAShippedPresetIsNotTheWikisOwn(): void
    {
        $default = $this->service()->find('default.css');

        $this->assertNotNull($default);
        $this->assertFalse($default['custom'], 'themes/ is code -- nothing here may write to it');
    }

    /** A preset is read as a whole: every variable has a value, declared or defaulted. */
    public function testEveryVariableHasAValue(): void
    {
        $values = $this->service()->valuesFor('default.css');

        foreach (array_keys(PresetService::VARIABLES) as $variable) {
            $this->assertArrayHasKey($variable, $values);
            $this->assertNotSame('', $values[$variable]);
        }
    }

    public function testAnUnknownPresetFallsBackToTheDefaults(): void
    {
        $this->assertSame(
            $this->service()->valuesFor(''),
            $this->service()->valuesFor('there-is-no-such-preset.css'),
            'the rail has to open on something valid whatever it is asked for'
        );
    }

    public function testTheVariablesOfAStylesheetAreRead(): void
    {
        $values = $this->service()->valuesOf(":root {\n  --primary-color: #123456;\n  --main-text-fontsize: 19px;\n}\n");

        $this->assertSame('#123456', $values['primary-color']);
        $this->assertSame('19px', $values['main-text-fontsize']);
    }

    /**
     * Deliberately more forgiving than ThemeManager's own parser, which returns nothing at
     * all when one colour is not a hex literal -- a hand-edited file would then show as
     * having no colours rather than as having one unusual one.
     */
    public function testOneOddValueDoesNotHideTheOthers(): void
    {
        $values = $this->service()->valuesOf(":root {\n  --primary-color: rgb(1, 2, 3);\n  --neutral-color: #abcdef;\n}");

        $this->assertSame('rgb(1, 2, 3)', $values['primary-color']);
        $this->assertSame('#abcdef', $values['neutral-color']);
    }

    /** Anything the themes do not consume stays in the file but is not offered for editing. */
    public function testAVariableThatIsNotAPresetVariableIsIgnored(): void
    {
        $values = $this->service()->valuesOf(':root { --primary-color: #000000; --something-else: 4px; }');

        $this->assertArrayNotHasKey('something-else', $values);
    }

    public function testAStylesheetWithoutARootBlockYieldsNothing(): void
    {
        $this->assertSame([], $this->service()->valuesOf('.foo { color: red; }'));
    }

    /**
     * The name is concatenated into a path, so it is reduced to a plain name rather than
     * escaped. A traversal must come out as an ordinary file in custom/css-presets/.
     */
    public function testATypedNameBecomesAPlainFileName(): void
    {
        $service = $this->service();

        $this->assertSame('my-preset.css', $service->fileNameFor('My Preset'));
        $this->assertSame('evil.css', $service->fileNameFor('../../evil'));
        $this->assertSame('etc-passwd.css', $service->fileNameFor('/etc/passwd'));
        $this->assertSame('accentue.css', $service->fileNameFor('accentué'));
    }

    public function testANameThatIsNothingIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->fileNameFor('   ');
    }

    /**
     * `favorite_preset` names a file CoreAssets will link into the head of every page, so an
     * id nobody offers is refused rather than written.
     */
    public function testSelectingAPresetThatDoesNotExistIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->select('../../../etc/passwd');
    }

    public function testAShippedPresetCannotBeDeleted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->delete('default.css');
    }

    public function testAPresetThatDoesNotExistCannotBeDeleted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->delete(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . 'there-is-no-such-preset.css');
    }

    /** The native colour input reads anything it cannot parse as black, and says so back. */
    public function testAColourIsNormalisedForThePickerWithoutLosingTheValue(): void
    {
        $service = $this->service();

        $this->assertSame('#123456', $service->asHex('#123456'));
        $this->assertSame('#aabbcc', $service->asHex('#abc'), 'shorthand is a colour a picker can show');
        $this->assertSame('#000000', $service->asHex('rgb(1, 2, 3)'), 'what it cannot show, it shows as black');
    }
}

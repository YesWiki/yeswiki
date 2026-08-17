<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The Preset vocabulary (ticket 30, rewritten onto Design tokens by ADR-0020).
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
    /**
     * The eighteen properties ADR-0021 took out of a Preset's hands, and the two it always
     * computed. Core declares every one of them, from the tokens above it; nothing here may
     * appear in PresetService::TOKENS, and nothing may stop being declared.
     */
    private const DERIVED = [
        'yw-primary-hover',
        'yw-surface-hover',
        'yw-overlay',
        'yw-text-muted',
        'yw-border-strong',
        'yw-border-subtle',
        'yw-focus-ring',
        'yw-success-surface', 'yw-success-text',
        'yw-danger-surface', 'yw-danger-text',
        'yw-warning-surface', 'yw-warning-text',
        'yw-info-surface', 'yw-info-text',
        'yw-navbar-border', 'yw-navbar-active',
        'yw-shadow-color', 'yw-shadow-color-strong',
        'yw-radius-sm', 'yw-radius-md', 'yw-radius-lg', 'yw-radius-full',
    ];

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

    /**
     * The shipped presets are complete, which is the property every other one is judged by.
     *
     * If a shipped preset can be incomplete, the rule is decoration: the screen would be
     * flagging the wiki's own defaults.
     */
    public function testTheShippedPresetsAreComplete(): void
    {
        foreach ($this->service()->all() as $preset) {
            if ($preset['custom']) {
                continue;
            }
            $this->assertSame([], $preset['missing'], $preset['name'] . ' leaves tokens out');
            $this->assertTrue($preset['complete']);
        }
    }

    /** Core declares the same token set a Preset does -- it *is* the default Preset. */
    public function testCoreDeclaresEveryToken(): void
    {
        $this->assertSame([], $this->service()->missingIn($this->service()->coreDefaults()));
    }

    /**
     * `default` IS core's own token set, value for value.
     *
     * The one thing that file must never do is disagree with core: "no preset" and "the
     * default preset" would then look different, and the preset offered as the one to copy
     * from would not be the wiki you were looking at. Nothing enforces this at write time --
     * they are two files -- so it is enforced here.
     */
    public function testTheDefaultPresetIsExactlyCoresOwnValues(): void
    {
        $service = $this->service();
        $default = $service->find('default.css');

        $this->assertNotNull($default);
        $core = $service->coreDefaults();

        foreach (PresetService::TOKENS as $token => $definition) {
            $this->assertSame(
                $core['light'][$token],
                $default['values']['light'][$token],
                $token . ' differs between core and the default preset'
            );
            if ($definition['kind'] === PresetService::KIND_COLOR) {
                $this->assertSame(
                    $core['dark'][$token],
                    $default['values']['dark'][$token],
                    $token . ' (dark) differs between core and the default preset'
                );
            }
        }
    }

    /**
     * Core's own ink clears WCAG AA against the ground it sits on, in both schemes.
     *
     * The rail scores this live for whoever is editing; nothing was scoring what YesWiki
     * itself ships, and `--yw-secondary` sat at 3.69 and `--yw-heading-sub` at 2.76 on white
     * -- the wiki's own h4-h6 failing the check the screen asks webmasters to pass.
     *
     * **Core and `default` only, deliberately.** `fun`, `landes`, `red` and `yellow` do not
     * all clear AA and are not held to it here: a bright yellow or a pale cyan cannot be
     * AA-legible as body-size ink on white at any lightness, so the only way to pass would be
     * to stop being that colour -- which is a decision about YesWiki's palettes, not one a
     * test gets to make. What core ships as its *default* is a different matter: it is what
     * every wiki wears until somebody chooses otherwise.
     *
     * AA (4.5) and not AAA: AAA on white forces a brand colour to near-black. Only pairs the
     * token table declares are checked -- a fill like `--yw-tertiary` is not ink, and neither
     * is a status colour, whose read value is the derived `--yw-*-text`.
     */
    public function testCoreAndTheDefaultPresetClearAaOnEveryPairTheyDeclare(): void
    {
        foreach ($this->service()->all() as $preset) {
            if ($preset['name'] !== 'default') {
                continue;
            }

            foreach (PresetService::TOKENS as $token => $definition) {
                if (!isset($definition['contrast'])) {
                    continue;
                }
                foreach (PresetService::SCHEMES as $scheme) {
                    $ink = $preset['values'][$scheme][$token] ?? '';
                    $ground = $preset['values'][$scheme][$definition['contrast']] ?? '';
                    $ratio = $this->contrastRatio($ink, $ground);
                    if ($ratio === null) {
                        continue;
                    }
                    $this->assertGreaterThanOrEqual(
                        4.5,
                        $ratio,
                        sprintf(
                            '%s: %s (%s) on %s (%s) in %s scores %.2f -- below AA',
                            $preset['name'],
                            $token,
                            $ink,
                            $definition['contrast'],
                            $ground,
                            $scheme,
                            $ratio
                        )
                    );
                }
            }
        }
    }

    /** WCAG 2.1 contrast ratio between two six-digit hex colours, or null if not both. */
    private function contrastRatio(string $a, string $b): ?float
    {
        $luminance = static function (string $hex): ?float {
            if (!preg_match('/^#[0-9a-f]{6}$/i', trim($hex))) {
                return null;
            }
            $hex = ltrim(trim($hex), '#');
            $channel = static function (float $value): float {
                $value /= 255;

                return $value <= 0.03928 ? $value / 12.92 : ((($value + 0.055) / 1.055) ** 2.4);
            };

            return 0.2126 * $channel((float)hexdec(substr($hex, 0, 2)))
                + 0.7152 * $channel((float)hexdec(substr($hex, 2, 2)))
                + 0.0722 * $channel((float)hexdec(substr($hex, 4, 2)));
        };

        $first = $luminance($a);
        $second = $luminance($b);
        if ($first === null || $second === null) {
            return null;
        }

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * A colour may point at another, and a LOOP of them is refused before it is written.
     *
     * The loop is the reason this exists. A custom property whose value refers back to itself,
     * however long the chain, is invalid at computed-value time: the browser does not warn,
     * does not fall back, and does not leave the property unset in a way a rule can notice --
     * every colour in the loop simply computes to black. Measured in a browser, not assumed.
     * So nothing else will catch it, and a webmaster who pointed the brand at the heading that
     * was already pointing at the brand would get a black wiki and no clue why.
     */
    public function testALoopOfReferencesIsRefused(): void
    {
        $service = $this->service();
        $base = $service->coreDefaults();

        $this->assertNull($service->cycleIn($base), 'core itself must be clean');

        // pointing one colour at another is fine, and is the whole point of the palette
        $pointed = $base;
        $pointed['light']['yw-heading-1'] = 'var(--yw-primary)';
        $this->assertNull($service->cycleIn($pointed));

        // a colour that is its own value
        $self = $base;
        $self['light']['yw-primary'] = 'var(--yw-primary)';
        $this->assertSame(['yw-primary', 'yw-primary'], $service->cycleIn($self));

        // two of them pointing at each other, named in order so the message can show the loop
        $two = $base;
        $two['light']['yw-heading-1'] = 'var(--yw-secondary)';
        $two['light']['yw-secondary'] = 'var(--yw-heading-1)';
        $this->assertSame(
            ['yw-secondary', 'yw-heading-1', 'yw-secondary'],
            $service->cycleIn($two)
        );

        // ...including one buried in a function, in the dark scheme only. Any `var()` is a
        // reference, not only a value that is nothing but one.
        $mixed = $base;
        $mixed['dark']['yw-primary'] = 'color-mix(in oklab, var(--yw-heading-3) 50%, white)';
        $mixed['dark']['yw-heading-3'] = 'var(--yw-primary)';
        $this->assertNotNull($service->cycleIn($mixed), 'a loop through a color-mix is still a loop');
    }

    public function testASaveIsRefusedRatherThanWritingABlackWiki(): void
    {
        $values = $this->service()->coreDefaults();
        $values['light']['yw-primary'] = 'var(--yw-secondary)';
        $values['light']['yw-secondary'] = 'var(--yw-primary)';

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->save('', 'a-loop', $values);
    }

    /** Every `var()` in a value is a reference -- fallbacks and functions included. */
    public function testReferencesAreFoundWhereverTheyAre(): void
    {
        $service = $this->service();

        $this->assertSame(['yw-primary'], $service->referencesIn('var(--yw-primary)'));
        $this->assertSame(
            ['yw-primary', 'yw-text'],
            $service->referencesIn('color-mix(in oklab, var(--yw-primary) 50%, var(--yw-text))')
        );
        $this->assertSame(['yw-a', 'yw-b'], $service->referencesIn('var( --yw-a , var(--yw-b) )'));
        $this->assertSame([], $service->referencesIn('#0c5d6a'));
    }

    /** The palette offers colours, and only ones that exist. */
    public function testThePaletteIsMadeOfRealColours(): void
    {
        $this->assertNotEmpty(PresetService::PALETTE);

        foreach (PresetService::PALETTE as $token) {
            $this->assertArrayHasKey($token, PresetService::TOKENS, $token . ' is not a token');
            $this->assertContains(
                PresetService::TOKENS[$token]['kind'],
                [PresetService::KIND_COLOR, PresetService::KIND_COLOR_FIXED],
                $token . ' is offered as a colour but is not one'
            );
        }

        // curated: every authored colour would be a wall rather than a palette
        $colours = array_filter(
            PresetService::TOKENS,
            fn ($definition) => in_array(
                $definition['kind'],
                [PresetService::KIND_COLOR, PresetService::KIND_COLOR_FIXED],
                true
            )
        );
        $this->assertLessThan(
            count($colours),
            count(PresetService::PALETTE),
            'the palette is a selection, not the whole colour set'
        );
    }

    /** A rail opens on a complete set of values, whatever the file it was opened on says. */
    public function testEveryTokenHasAValueForTheEditor(): void
    {
        $values = $this->service()->valuesFor('default.css');

        foreach (PresetService::TOKENS as $token => $definition) {
            $this->assertArrayHasKey($token, $values['light']);
            $this->assertNotSame('', $values['light'][$token]);
            if ($definition['kind'] === PresetService::KIND_COLOR) {
                $this->assertArrayHasKey($token, $values['dark']);
                $this->assertNotSame('', $values['dark'][$token]);
            }
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

    public function testTheTokensOfAStylesheetAreRead(): void
    {
        $values = $this->service()->valuesOf(":root {\n  --yw-primary: #123456;\n  --yw-font-size-base: 19px;\n}\n");

        $this->assertSame('#123456', $values['light']['yw-primary']);
        $this->assertSame('19px', $values['light']['yw-font-size-base']);
    }

    /**
     * The dark set lives inside a media query, and a preset carries it twice -- once for the
     * system preference and once for the explicit choice. Both blocks have to be read, or a
     * preset that leads with either would list as missing every colour it actually declares.
     */
    public function testTheDarkSchemeIsReadFromBothOfItsBlocks(): void
    {
        $service = $this->service();

        $fromMedia = $service->valuesOf(
            ":root { --yw-primary: #111111; }\n"
            . "@media (prefers-color-scheme: dark) {\n  :root:not([data-theme='light']) { --yw-primary: #eeeeee; }\n}\n"
        );
        $this->assertSame('#111111', $fromMedia['light']['yw-primary']);
        $this->assertSame('#eeeeee', $fromMedia['dark']['yw-primary'], 'the media query wraps the block, its brace is not the block\'s');

        $fromAttribute = $service->valuesOf(
            ":root { --yw-primary: #111111; }\n:root[data-theme='dark'] { --yw-primary: #eeeeee; }\n"
        );
        $this->assertSame('#eeeeee', $fromAttribute['dark']['yw-primary']);
    }

    /**
     * A Preset is complete or it is an error, and the error names what is missing -- the one
     * thing that turns "your preset is wrong" into something a webmaster can act on.
     */
    public function testAnIncompletePresetNamesWhatItLeavesOut(): void
    {
        $missing = $this->service()->missingIn(
            $this->service()->valuesOf(':root { --yw-primary: #123456; }')
        );

        $this->assertContains('--yw-primary (dark)', $missing, 'a colour is authored once per scheme');
        $this->assertContains('--yw-surface', $missing);
        $this->assertNotContains('--yw-primary', $missing, 'the one value it does declare is not missing');
        // the Layout setting is not a token: a Preset neither declares it nor is judged on it
        $this->assertNotContains('--yw-navbar-height', $missing);
    }

    /** Saving refuses a gap rather than filling it from somebody else's brand. */
    public function testAnIncompletePresetIsRefusedOnSave(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->save('', 'half-a-preset', ['light' => ['yw-primary' => '#123456'], 'dark' => []]);
    }

    /** What is written is what is read back: the round trip is the file format's contract. */
    public function testWhatIsWrittenIsWhatIsReadBack(): void
    {
        $service = $this->service();
        $values = $service->valuesFor('default.css');

        $this->assertSame($values, $service->valuesOf($service->toCss($values)));
    }

    /**
     * Deliberately more forgiving than ThemeManager's own parser, which returns nothing at
     * all when one colour is not a hex literal -- a hand-edited file would then show as
     * having no colours rather than as having one unusual one.
     */
    public function testOneOddValueDoesNotHideTheOthers(): void
    {
        $values = $this->service()->valuesOf(":root {\n  --yw-primary: rgb(1, 2, 3);\n  --yw-text: #abcdef;\n}");

        $this->assertSame('rgb(1, 2, 3)', $values['light']['yw-primary']);
        $this->assertSame('#abcdef', $values['light']['yw-text']);
    }

    /**
     * Core DERIVES what a Preset used to be asked for, and asks for nothing it derives.
     *
     * This is the ADR-0021 contract from both sides, and both halves fail silently on their
     * own. A derived property that stopped being declared in core leaves every rule that
     * consumes it resolving to nothing -- `border: 1px solid` with no colour, which renders
     * as the initial `currentColor` and looks like a styling choice. And a derived property
     * that came BACK into TOKENS would make every existing preset incomplete overnight, and
     * be written into new ones as a value core immediately overrides.
     */
    public function testEveryDerivedValueIsDeclaredByCoreAndAskedOfNobody(): void
    {
        $core = $this->service()->coreDefaults();

        foreach (self::DERIVED as $property) {
            $this->assertArrayNotHasKey(
                $property,
                PresetService::TOKENS,
                $property . ' is computed by core: a Preset must not be asked for it'
            );
        }

        // and core really does declare them: read out of the stylesheet rather than restated
        // here, which is the same reason coreDefaults() parses the file instead of listing it
        $declared = $this->declaredByCore();
        foreach (self::DERIVED as $property) {
            $this->assertContains(
                $property,
                $declared,
                $property . ' is consumed by rules and declared by nobody'
            );
        }

        // the authored half is in the same file, which is what makes core the default Preset
        foreach (array_keys(PresetService::TOKENS) as $token) {
            $this->assertArrayHasKey($token, $core['light'], $token);
        }
    }

    /**
     * A derived colour is written once and re-resolves per scheme -- that is what it buys.
     *
     * The dark blocks restate the AUTHORED colours and nothing else. If one of them started
     * restating a derived property too, the derivation would be decorative for that property
     * and the two copies would drift, which is exactly the failure the tier exists to end.
     */
    public function testTheDarkSchemeRestatesOnlyWhatItAuthors(): void
    {
        $css = (string)file_get_contents(YESWIKI_SOURCE_DIR . '/styles/yw-core.css');
        $dark = $this->service()->valuesOf($css)['dark'];

        foreach (self::DERIVED as $property) {
            $this->assertArrayNotHasKey($property, $dark, $property . ' is derived: the dark set must not restate it');
        }
        // ...and it does restate every colour it authors, or the dark scheme has a gap
        foreach (PresetService::TOKENS as $token => $definition) {
            if ($definition['kind'] === PresetService::KIND_COLOR) {
                $this->assertArrayHasKey($token, $dark, $token . ' has no dark value');
            }
        }
    }

    /**
     * A measure is a slider's value: bounded, and reachable in the steps the slider has.
     *
     * The rail has no text box for one any more, so a default the slider cannot land on is a
     * value a webmaster could look at, not touch, and never get back after touching anything.
     */
    public function testEveryMeasureDefaultSitsOnItsSlider(): void
    {
        $sets = ['core' => $this->service()->coreDefaults()['light']];
        // and every SHIPPED preset, which is the case that actually bites: `landes` asked
        // for `0.219rem`, a value its slider could not land on, so opening it in the rail
        // silently snapped it -- and saving without touching that field would have written
        // the snapped value back over the one the theme shipped
        foreach ($this->service()->all() as $preset) {
            if (!$preset['custom']) {
                $sets[$preset['name']] = $preset['values']['light'];
            }
        }

        foreach ($sets as $where => $values) {
            $this->assertMeasuresSitOnTheirSliders($values, $where);
        }
    }

    /** @param array<string, string> $values */
    private function assertMeasuresSitOnTheirSliders(array $values, string $where): void
    {
        foreach (PresetService::TOKENS as $token => $definition) {
            if ($definition['kind'] !== PresetService::KIND_SIZE) {
                continue;
            }

            $label = $where . ':' . $token;
            $value = $values[$token];
            $unit = $definition['unit'];
            $this->assertMatchesRegularExpression(
                '/^[0-9.]+' . preg_quote($unit, '/') . '$/',
                $value,
                $label . " must be a plain number in '" . $unit . "'"
            );

            $number = (float)$value;
            $this->assertGreaterThanOrEqual($definition['min'], $number, $label . ' is below its slider');
            $this->assertLessThanOrEqual($definition['max'], $number, $label . ' is above its slider');
            $steps = ($number - $definition['min']) / $definition['step'];
            $this->assertEqualsWithDelta(
                round($steps),
                $steps,
                0.001,
                $label . ' does not land on a step of its slider'
            );
        }
    }

    /**
     * The `--yw-*` properties core's own stylesheet declares, at the top level.
     *
     * @return list<string>
     */
    private function declaredByCore(): array
    {
        $css = (string)file_get_contents(YESWIKI_SOURCE_DIR . '/styles/yw-core.css');
        preg_match_all('/^\s*--(yw-[a-z0-9-]+)\s*:/m', $css, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** Anything that is not a token stays in the file but is not offered for editing. */
    public function testAPropertyThatIsNotATokenIsIgnored(): void
    {
        $values = $this->service()->valuesOf(':root { --yw-primary: #000000; --something-else: 4px; --yw-navbar-height: 60px; }');

        $this->assertArrayNotHasKey('something-else', $values['light']);
        $this->assertArrayNotHasKey('yw-navbar-height', $values['light'], 'a Layout setting is not a token');
    }

    public function testAStylesheetWithoutARootBlockYieldsNothing(): void
    {
        $this->assertSame(['light' => [], 'dark' => []], $this->service()->valuesOf('.foo { color: red; }'));
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

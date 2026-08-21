<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The Preset vocabulary (ticket 30, rewritten onto Design tokens by ADR-0020). */
class PresetServiceTest extends YesWikiTestCase
{
    /** The eighteen properties ADR-0021 took out of a Preset's hands, and the two it always computed. */
    private const DERIVED = [
        'yw-text',
        'yw-text-inverse',
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

    /** The shipped presets are complete, which is the property every other one is judged by. */
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

    /** `default` IS core's own token set, value for value. */
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

    /** Core's own ink clears WCAG AA against the ground it sits on, in both schemes. */
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

    /** A colour may point at another, and a LOOP of them is refused before it is written. */
    public function testALoopOfReferencesIsRefused(): void
    {
        $service = $this->service();
        $base = $service->coreDefaults();

        $this->assertNull($service->cycleIn($base), 'core itself must be clean');

        $pointed = $base;
        $pointed['light']['yw-heading-1'] = 'var(--yw-primary)';
        $this->assertNull($service->cycleIn($pointed));

        $self = $base;
        $self['light']['yw-primary'] = 'var(--yw-primary)';
        $this->assertSame(['yw-primary', 'yw-primary'], $service->cycleIn($self));

        $two = $base;
        $two['light']['yw-heading-1'] = 'var(--yw-secondary)';
        $two['light']['yw-secondary'] = 'var(--yw-heading-1)';
        $this->assertSame(
            ['yw-secondary', 'yw-heading-1', 'yw-secondary'],
            $service->cycleIn($two)
        );

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

    /** A stack the CSS formatter has wrapped is still the stack it was. */
    public function testAWrappedFontStackIsStillRecognised(): void
    {
        $service = $this->service();
        $wrapped = ":root {\n  --yw-font-mono:\n    ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas,\n    'DejaVu Sans Mono', monospace;\n}";

        $read = $service->valuesOf($wrapped)['light']['yw-font-mono'];

        $this->assertSame(PresetService::FONT_STACKS['Monospace Code'], $read);
        $this->assertTrue(
            PresetService::isSystemStack($read),
            'a stack the formatter wrapped must not be mistaken for a webfont to download'
        );
    }

    /** Every shipped preset names fonts the rail can offer back to it. */
    public function testShippedPresetsUseOfferableFonts(): void
    {
        $service = $this->service();
        $offered = array_merge(
            array_values(PresetService::FONT_STACKS),
            array_values($service->webfonts())
        );

        foreach ($service->all() as $preset) {
            if ($preset['custom']) {
                continue;
            }
            foreach (['yw-font-body', 'yw-font-heading', 'yw-font-mono'] as $token) {
                $this->assertContains(
                    $preset['values']['light'][$token],
                    $offered,
                    $preset['name'] . "'s " . $token . ' is a value the select cannot offer back'
                );
            }
        }
    }

    /** What installFont() refuses before it touches the network. */
    public function testInstallingAFontRefusesWhatItShouldNotFetch(): void
    {
        $service = $this->service();

        foreach (['', '   ', 'https://evil.example/x', '../../etc/passwd', str_repeat('a', 80)] as $family) {
            try {
                $service->installFont($family);
                $this->fail('a family of "' . $family . '" should not have been fetched');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->installFont(PresetService::FONT_STACKS['Neo-Grotesque']);
    }

    public function testTheWikiAFontIsCopiedFromMustBeAnOrdinaryAddress(): void
    {
        $service = $this->service();

        foreach ([
            'file:///etc/passwd',
            'ftp://example.org',
            'http://127.0.0.1/wiki',
            'https://192.168.1.10',
            'not a url',
        ] as $source) {
            try {
                $service->installFontsFromWiki($source);
                $this->fail($source . ' should not be fetched from');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            } catch (\RuntimeException) {
                $this->fail($source . ' reached the network before being checked');
            }
        }
    }

    /** A preset describes its own fonts, because its `@font-face` blocks already say it all. */
    public function testAPresetDescribesTheFontsItNeeds(): void
    {
        $service = $this->service();

        $this->assertSame([], $service->fontsOf('default.css', 'https://wiki.example'));
        $this->assertSame([], $service->fontsOf('there-is-no-such-preset.css'));

        $css = <<<'CSS'
            :root { --yw-font-body: 'Nunito', sans-serif; }
            /* latin */
            @font-face {
              font-family: 'Nunito';
              font-style: italic;
              font-weight: 700;
              src: url(../../custom/fonts/nunito/nunito-italic-700-latin.woff2) format('woff2');
              unicode-range: U+0000-00FF;
            }
            CSS;
        $path = sys_get_temp_dir() . '/yw-preset-fonts-' . getmypid() . '.css';
        file_put_contents($path, $css);

        try {
            $fonts = $this->fontsOfFile($service, $path, 'https://wiki.example');

            $this->assertCount(1, $fonts);
            $this->assertSame('Nunito', $fonts[0]['family']);
            $this->assertSame('italic', $fonts[0]['style'], 'the slope has to survive, or bold italic is lost');
            $this->assertSame('700', $fonts[0]['weight'], 'the weight is the whole point');
            $this->assertSame('latin', $fonts[0]['subset']);
            $this->assertSame('U+0000-00FF', $fonts[0]['unicodeRange']);

            $this->assertSame(
                'https://wiki.example/custom/fonts/nunito/nunito-italic-700-latin.woff2',
                $fonts[0]['url']
            );
        } finally {
            @unlink($path);
        }
    }

    /** A downloaded family is declared to the browser, whether or not a preset names it. */
    public function testEveryInstalledFamilyIsDeclaredWhateverNamesIt(): void
    {
        $directory = ThemeManager::CUSTOM_FONT_PATH . '/zz-test-face';
        @mkdir($directory, 0777, true);
        file_put_contents($directory . '/' . ThemeManager::FONT_FACES_FILE, <<<'CSS'
            @font-face {
              font-family: 'Zz Test Face';
              font-style: italic;
              font-weight: 700;
              src: url(../../custom/fonts/zz-test-face/zz-test-face-italic-700-latin.woff2) format('woff2');
              unicode-range: U+0000-00FF;
            }
            CSS);

        try {
            $css = $this->service()->installedFontFaces('https://wiki.example');

            $this->assertStringContainsString("font-family: 'Zz Test Face'", $css);

            $this->assertStringContainsString('unicode-range: U+0000-00FF', $css);

            $this->assertStringContainsString(
                'url(https://wiki.example/custom/fonts/zz-test-face/zz-test-face-italic-700-latin.woff2)',
                $css
            );
            $this->assertStringNotContainsString('../../', $css);
        } finally {
            @unlink($directory . '/' . ThemeManager::FONT_FACES_FILE);
            @rmdir($directory);
        }
    }

    /** A family installed before its rules were kept is described from its file names. */
    public function testAFamilyWithNoStoredRulesIsDescribedFromItsFiles(): void
    {
        $directory = ThemeManager::CUSTOM_FONT_PATH . '/zz-test-old';
        @mkdir($directory, 0777, true);
        file_put_contents($directory . '/zz-test-old-italic-700-latin.woff2', 'not really a font');

        try {
            $css = $this->service()->installedFontFaces('https://wiki.example');

            $this->assertStringContainsString("font-family: 'Zz Test Old'", $css);
            $this->assertStringContainsString('font-style: italic', $css);
            $this->assertStringContainsString('font-weight: 700', $css);
            $this->assertStringContainsString(
                'url(https://wiki.example/custom/fonts/zz-test-old/zz-test-old-italic-700-latin.woff2)',
                $css
            );
        } finally {
            @unlink($directory . '/zz-test-old-italic-700-latin.woff2');
            @rmdir($directory);
        }
    }

    /** The mono face is fetched and declared like the other two. */
    public function testTheMonospaceFontIsInstalledLikeTheOthers(): void
    {
        $source = (string)file_get_contents(YESWIKI_PROGRAM_DIR . '/src/Render/Service/PresetService.php');
        $call = substr($source, (int)strpos($source, 'writeCustomCSSPreset'), 300);

        foreach (['yw-font-body', 'yw-font-heading', 'yw-font-mono'] as $token) {
            $this->assertStringContainsString($token, $call, $token . ' is not handed to the writer');
        }
    }

    /**
     * @return list<array{family: string, style: string, weight: string, subset: string, unicodeRange: string, url: string}>
     */
    private function fontsOfFile(PresetService $service, string $path, string $baseUrl): array
    {
        $copy = ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . basename($path);
        @mkdir(ThemeManager::CUSTOM_CSS_PRESETS_PATH, 0777, true);
        copy($path, $copy);

        try {
            return $service->fontsOf(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . basename($path), $baseUrl);
        } finally {
            @unlink($copy);
        }
    }

    /** The two inks are the ONLY text colours a Preset sets, and everything else follows. */
    public function testTextIsDerivedFromTheTwoInks(): void
    {
        $this->assertArrayNotHasKey('yw-text', PresetService::TOKENS, 'the page ink is not authored');
        $this->assertArrayNotHasKey('yw-text-inverse', PresetService::TOKENS);
        $this->assertArrayHasKey('yw-ink-on-light', PresetService::TOKENS);
        $this->assertArrayHasKey('yw-ink-on-dark', PresetService::TOKENS);

        foreach (['yw-ink-on-light', 'yw-ink-on-dark'] as $ink) {
            $this->assertSame(PresetService::KIND_COLOR_FIXED, PresetService::TOKENS[$ink]['kind'], $ink);
        }

        $this->assertSame('light.yw-surface', PresetService::TOKENS['yw-ink-on-light']['contrast']);
        $this->assertSame('dark.yw-surface', PresetService::TOKENS['yw-ink-on-dark']['contrast']);
    }

    /** A background a page author typed gets an ink core chose -- where core can tell. */
    public function testCoreChoosesTheInkForABackgroundItCanRead(): void
    {
        $service = $this->service();

        $this->assertSame('var(--yw-ink-on-light)', $service->inkForBackground('#f9c401'), 'bright yellow wants dark ink');
        $this->assertSame('var(--yw-ink-on-dark)', $service->inkForBackground('#0c5d6a'), 'deep teal wants light ink');

        $this->assertSame('var(--yw-on-primary)', $service->inkForBackground('var(--yw-primary)'));
        $this->assertSame('var(--yw-on-warning)', $service->inkForBackground('var(--yw-warning)'));

        foreach ([
            'var(--yw-surface)',
            'color-mix(in oklab, red, blue)',
            'rebeccapurple',
            'linear-gradient(red, blue)',
            '',
        ] as $unreadable) {
            $this->assertSame('', $service->inkForBackground($unreadable), $unreadable . ' is not knowable');
        }
    }

    /** The Google catalogue is vendored, and a family is checked against it before anything is fetched. */
    public function testAFamilyIsCheckedAgainstTheVendoredCatalogue(): void
    {
        $service = $this->service();
        $catalogue = $service->googleFonts();

        $this->assertGreaterThan(1000, count($catalogue), 'the vendored list must actually be there');
        $this->assertContains('Nunito', $catalogue);
        $this->assertContains('JetBrains Mono', $catalogue);

        $this->assertSame('Open Sans', $service->googleFontNamed('open sans'));
        $this->assertSame('Open Sans', $service->googleFontNamed('  OPEN SANS  '));

        $this->assertSame('', $service->googleFontNamed('Opne Sans'));
        $this->assertSame('', $service->googleFontNamed(''));
    }

    /** Installing refuses what is not in the catalogue WITHOUT reaching the network. */
    public function testAnUnknownFamilyIsRefusedBeforeAnyDownload(): void
    {
        $service = $this->service();

        foreach (['Opne Sans', 'Definitely Not A Font', '', '   ', '../../etc/passwd'] as $unknown) {
            try {
                $service->installFont($unknown);
                $this->fail('"' . $unknown . '" should not have been fetched');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            } catch (\RuntimeException) {
                $this->fail('"' . $unknown . '" reached the network before being checked');
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->installFont(PresetService::FONT_STACKS['Neo-Grotesque']);
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
     * The dark set lives inside a media query, and a preset carries it twice -- once for the system preference and once for the explicit choice.
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
     * A Preset is complete or it is an error, and the error names what is missing -- the one thing that turns "your preset is wrong" into something a webmaster can act on.
     */
    public function testAnIncompletePresetNamesWhatItLeavesOut(): void
    {
        $missing = $this->service()->missingIn(
            $this->service()->valuesOf(':root { --yw-primary: #123456; }')
        );

        $this->assertContains('--yw-primary (dark)', $missing, 'a colour is authored once per scheme');
        $this->assertContains('--yw-surface', $missing);
        $this->assertNotContains('--yw-primary', $missing, 'the one value it does declare is not missing');

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
     * Deliberately more forgiving than ThemeManager's own parser, which returns nothing at all when one colour is not a hex literal -- a hand-edited file would then show as having no colours rather than as having one unusual one.
     */
    public function testOneOddValueDoesNotHideTheOthers(): void
    {
        $values = $this->service()->valuesOf(":root {\n  --yw-primary: rgb(1, 2, 3);\n  --yw-surface: #abcdef;\n}");

        $this->assertSame('rgb(1, 2, 3)', $values['light']['yw-primary']);
        $this->assertSame('#abcdef', $values['light']['yw-surface']);
    }

    /** Core DERIVES what a Preset used to be asked for, and asks for nothing it derives. */
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

        $declared = $this->declaredByCore();
        foreach (self::DERIVED as $property) {
            $this->assertContains(
                $property,
                $declared,
                $property . ' is consumed by rules and declared by nobody'
            );
        }

        foreach (array_keys(PresetService::TOKENS) as $token) {
            $this->assertArrayHasKey($token, $core['light'], $token);
        }
    }

    /** A derived colour is written once and re-resolves per scheme -- that is what it buys. */
    public function testTheDarkSchemeRestatesOnlyWhatItAuthors(): void
    {
        $css = (string)file_get_contents(YESWIKI_PROGRAM_DIR . '/styles/yw-core.css');
        $dark = $this->service()->valuesOf($css)['dark'];

        foreach (self::DERIVED as $property) {
            $this->assertArrayNotHasKey($property, $dark, $property . ' is derived: the dark set must not restate it');
        }

        foreach (PresetService::TOKENS as $token => $definition) {
            if ($definition['kind'] === PresetService::KIND_COLOR) {
                $this->assertArrayHasKey($token, $dark, $token . ' has no dark value');
            }
        }
    }

    /** A measure is a slider's value: bounded, and reachable in the steps the slider has. */
    public function testEveryMeasureDefaultSitsOnItsSlider(): void
    {
        $sets = ['core' => $this->service()->coreDefaults()['light']];

        foreach ($this->service()->all() as $preset) {
            if (!$preset['custom']) {
                $sets[$preset['name']] = $preset['values']['light'];
            }
        }

        foreach ($sets as $where => $values) {
            $this->assertMeasuresSitOnTheirSliders($values, $where);
        }
    }

    /**
     * @param array<string, string> $values
     */
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
        $css = (string)file_get_contents(YESWIKI_PROGRAM_DIR . '/styles/yw-core.css');
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

    /** The name is concatenated into a path, so it is reduced to a plain name rather than escaped. */
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
     * `favorite_preset` names a file CoreAssets will link into the head of every page, so an id nobody offers is refused rather than written.
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

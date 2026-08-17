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

    /**
     * A stack the CSS formatter has wrapped is still the stack it was.
     *
     * A long `font-family` gets broken over several lines when the file is formatted, so the
     * value came back with newlines and indentation inside it. Nothing rendered wrong -- CSS
     * treats them as whitespace -- but every exact comparison against it failed: the rail did
     * not recognise its own `Monospace Code` stack and showed the raw text as a bespoke
     * option, and isSystemStack() stopped recognising it and had ThemeManager ask Google for a
     * webfont called `ui-monospace`.
     */
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

    /**
     * What installFont() refuses before it touches the network.
     *
     * Each of these would otherwise become a curl call: a nonsense family is a request to
     * Google for nothing, and a source URL is a request THIS SERVER makes to an address
     * somebody typed into an admin form. `file://` would read the disk and a bare IP is the
     * shape of somebody probing the network the server sits in, so both are refused before a
     * connection is opened rather than after.
     */
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

        // a local stack needs no download, and asking for one is a sign of a confused screen
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

    /**
     * A preset describes its own fonts, because its `@font-face` blocks already say it all.
     *
     * This is what makes copying a look between wikis work: the stylesheet alone is useless
     * without the files it names, and a *guess* at those file names cannot know which weights
     * exist. Reading the blocks gives family, style, weight, `unicode-range` and a URL each --
     * every weight and both slopes, as facts rather than a convention.
     */
    public function testAPresetDescribesTheFontsItNeeds(): void
    {
        $service = $this->service();

        // a preset with no webfont has nothing to describe, and must not invent any
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
            // absolute, because the wiki asking is not this one and `../../` means nothing there
            $this->assertSame(
                'https://wiki.example/custom/fonts/nunito/nunito-italic-700-latin.woff2',
                $fonts[0]['url']
            );
        } finally {
            @unlink($path);
        }
    }

    /**
     * A downloaded family is declared to the browser, whether or not a preset names it.
     *
     * **This is the bug that made the font selector look broken.** A family's `@font-face`
     * rules were written into a *preset*, when that preset was saved -- so a font could be
     * fully downloaded, offered in the select, chosen, and still be a name no browser had
     * ever heard of. Picking it set `font-family` on the document and every word carried on
     * rendering in the fallback: no error, no console warning, nothing to see. Identical, from
     * the webmaster's side, to the choice not registering at all.
     */
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
            // the subset survives, because it came from Google's own answer rather than
            // from a file name -- it is the reason the rules are kept at all
            $this->assertStringContainsString('unicode-range: U+0000-00FF', $css);
            // absolute: a preset stores `../../custom/fonts/…` relative to ITSELF, and this
            // is served from a route, so nothing a browser resolved it against would find it
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

    /**
     * A family installed before its rules were kept is described from its file names.
     *
     * `<family>-<style>-<weight>-<subset>.woff2` is what importFontFile writes, so style and
     * weight are recoverable -- which is what stops an upgrade turning every already-installed
     * font into one the rail offers and cannot draw.
     */
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

    /**
     * The mono face is fetched and declared like the other two.
     *
     * It was left out of the list handed to the writer: a preset whose code blocks named a
     * webfont was written with no `@font-face` for it at all. Same silent nothing as an
     * uninstalled font, on the one token nobody looks at twice.
     */
    public function testTheMonospaceFontIsInstalledLikeTheOthers(): void
    {
        $source = (string)file_get_contents(YESWIKI_SOURCE_DIR . '/src/Render/Service/PresetService.php');
        $call = substr($source, (int)strpos($source, 'writeCustomCSSPreset'), 300);

        foreach (['yw-font-body', 'yw-font-heading', 'yw-font-mono'] as $token) {
            $this->assertStringContainsString($token, $call, $token . ' is not handed to the writer');
        }
    }

    /** fontsOf() addressed by path rather than by preset id, for a fixture on disk. */
    /** @return list<array{family: string, style: string, weight: string, subset: string, unicodeRange: string, url: string}> */
    private function fontsOfFile(PresetService $service, string $path, string $baseUrl): array
    {
        // find() resolves an id against the theme and custom directories, so a fixture has to
        // be put where one of them will see it
        $copy = ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . basename($path);
        @mkdir(ThemeManager::CUSTOM_CSS_PRESETS_PATH, 0777, true);
        copy($path, $copy);

        try {
            return $service->fontsOf(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . basename($path), $baseUrl);
        } finally {
            @unlink($copy);
        }
    }

    /**
     * The two inks are the ONLY text colours a Preset sets, and everything else follows.
     *
     * `--yw-text` and `--yw-text-inverse` used to be authored, and authored per scheme -- four
     * values expressing one decision, with four chances to disagree. They are derived now:
     * the page's ink is whichever of the pair suits the scheme in force, and the inverse is
     * the other. Asked once, answered everywhere.
     */
    public function testTextIsDerivedFromTheTwoInks(): void
    {
        $this->assertArrayNotHasKey('yw-text', PresetService::TOKENS, 'the page ink is not authored');
        $this->assertArrayNotHasKey('yw-text-inverse', PresetService::TOKENS);
        $this->assertArrayHasKey('yw-ink-on-light', PresetService::TOKENS);
        $this->assertArrayHasKey('yw-ink-on-dark', PresetService::TOKENS);

        // both are scheme-independent: a light ground is light at midnight too
        foreach (['yw-ink-on-light', 'yw-ink-on-dark'] as $ink) {
            $this->assertSame(PresetService::KIND_COLOR_FIXED, PresetService::TOKENS[$ink]['kind'], $ink);
        }

        // ...and each is scored against the surface of the scheme where it IS the page's ink;
        // against the other scheme's surface it would report a pairing that never happens
        $this->assertSame('light.yw-surface', PresetService::TOKENS['yw-ink-on-light']['contrast']);
        $this->assertSame('dark.yw-surface', PresetService::TOKENS['yw-ink-on-dark']['contrast']);
    }

    /**
     * A background a page author typed gets an ink core chose -- where core can tell.
     *
     * This is the one ground a stylesheet cannot measure, so it used to be left to
     * `class="white"`/`"black"`: an author guessing, and guessing again every time the
     * preset's colours moved. Two shapes are answerable and the rest deliberately are not --
     * guessing wrong here is unreadable text on somebody's cover image.
     */
    public function testCoreChoosesTheInkForABackgroundItCanRead(): void
    {
        $service = $this->service();

        // a literal is measured against the wiki's own two inks
        $this->assertSame('var(--yw-ink-on-light)', $service->inkForBackground('#f9c401'), 'bright yellow wants dark ink');
        $this->assertSame('var(--yw-ink-on-dark)', $service->inkForBackground('#0c5d6a'), 'deep teal wants light ink');

        // a fill already has a resolved ink: use that answer rather than computing a second
        $this->assertSame('var(--yw-on-primary)', $service->inkForBackground('var(--yw-primary)'));
        $this->assertSame('var(--yw-on-warning)', $service->inkForBackground('var(--yw-warning)'));

        // and everything else is left alone rather than guessed at
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

    /**
     * The Google catalogue is vendored, and a family is checked against it before anything
     * is fetched.
     *
     * A shape regex was the old check, and `Opne Sans` passes one: it is a perfectly
     * well-formed family name, Google answers a request for it with nothing at all, and the
     * webmaster gets a blank failure after a network round trip. The catalogue turns that
     * into "no such font" before a connection is opened, and hands back Google's own casing,
     * which is what the download URL and the folder name are built from.
     */
    public function testAFamilyIsCheckedAgainstTheVendoredCatalogue(): void
    {
        $service = $this->service();
        $catalogue = $service->googleFonts();

        $this->assertGreaterThan(1000, count($catalogue), 'the vendored list must actually be there');
        $this->assertContains('Nunito', $catalogue);
        $this->assertContains('JetBrains Mono', $catalogue);

        // matched case-insensitively, answered in the catalogue's casing
        $this->assertSame('Open Sans', $service->googleFontNamed('open sans'));
        $this->assertSame('Open Sans', $service->googleFontNamed('  OPEN SANS  '));

        // ...and a typo is not a font
        $this->assertSame('', $service->googleFontNamed('Opne Sans'));
        $this->assertSame('', $service->googleFontNamed(''));
    }

    /**
     * Installing refuses what is not in the catalogue WITHOUT reaching the network.
     *
     * Asserted by the exception type: an unknown family is an argument problem, and a family
     * that is real but could not be fetched is a runtime one. If this ever started throwing
     * the latter, the check had stopped happening before the download.
     */
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

        // a local stack is not a download either
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
        $values = $this->service()->valuesOf(":root {\n  --yw-primary: rgb(1, 2, 3);\n  --yw-surface: #abcdef;\n}");

        $this->assertSame('rgb(1, 2, 3)', $values['light']['yw-primary']);
        $this->assertSame('#abcdef', $values['light']['yw-surface']);
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

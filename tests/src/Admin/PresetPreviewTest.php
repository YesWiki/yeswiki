<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Controller\AdminController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\PresetService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The Preset screen's component gallery (ticket 30). */
class PresetPreviewTest extends YesWikiTestCase
{
    private static ?string $rendered = null;

    /**
     * component => a string only that component can have produced.
     *
     * @return array<string, list<string>>
     */
    public static function components(): array
    {
        return [
            'heading level 6' => ['<h6'],
            'bold' => ['<strong>gras</strong>'],
            'italic' => ['<em>italique</em>'],
            'strikethrough' => ['<del>texte'],
            'inline code' => ['<code>code en'],
            'table' => ['<th>Colonne</th>'],
            'blockquote' => ['<blockquote>'],
            'nested list' => ['un point imbriqué'],
            'ordered list' => ['<ol>'],
            'internal link' => ['data-tag="PagePrincipale"'],
            'button' => ['yw-btn--danger'],
            'button with an icon' => ['icons.svg#star'],
            'block button' => ['yw-btn--block'],
            'label' => ['yw-label--warning'],

            'progress bar' => ['<progress class="yw-progressbar" value="35"'],
            'grid and columns' => ['yw-col'],
            'panel' => ['yw-panel--primary'],
            'accordion' => ['yw-accordion'],

            'tabs' => ['Premier onglet'],
            'section' => ['shape-rounded'],
        ];
    }

    #[DataProvider('components')]
    public function testTheGalleryRendersEveryComponent(string $marker): void
    {
        $this->assertStringContainsString(
            $marker,
            $this->screen(),
            'the gallery must actually render this component -- an action that renders nothing leaves no trace'
        );
    }

    /**
     * The gallery titles each block with the markup that draws it, inside a code span, so the preview doubles as a syntax reference.
     */
    public function testNoComponentIsPrintedInsteadOfRendered(): void
    {
        $withoutCodeSpans = preg_replace('~<code>[^<]*</code>~', '', $this->screen());

        $this->assertSame(
            0,
            preg_match_all('/\{\{\s*(button|label|panel|accordion|tabs|grid|col|section)\b/', (string)$withoutCodeSpans),
            'a component printed as literal markup did not run'
        );
    }

    /** Every component the gallery opens, it closes. */
    public function testTheGalleryClosesEveryComponentItOpens(): void
    {
        $screen = $this->screen();

        $this->assertSame(
            6,
            substr_count($screen, 'start of label'),
            'six labels -- if this changes, so must the count below'
        );
        $this->assertGreaterThanOrEqual(
            6,
            substr_count($screen, '</span>'),
            'a label that never closes swallows the rest of the gallery'
        );
        foreach (['col', 'grid', 'panel', 'accordion'] as $element) {
            $this->assertSame(
                substr_count($screen, 'start of ' . $element),
                substr_count($screen, 'end of ' . $element),
                "every {{{$element}}} the gallery opens must close"
            );
        }
    }

    /** The gallery shows lists of entries and whole page layouts, not only single components. */
    public function testTheGalleryShowsListsAndLayouts(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('yw-items--card', $screen, 'a list of cards');
        $this->assertStringContainsString('yw-items--list', $screen, 'a list of rows');
        $this->assertStringContainsString('yw-items--table', $screen, 'a list as a table');

        $this->assertSame(6, substr_count($screen, 'yw-item--card'), 'six cards, or the grid never wraps');
        $this->assertSame(3, substr_count($screen, 'yw-item--list'), 'three rows');

        $this->assertSame(6, substr_count($screen, 'yw-item__image'), 'a list must mix items with and without an image');
        $this->assertStringNotContainsString('&lt;article', $screen, 'markup printed as source instead of rendered');

        $this->assertStringContainsString('yw-item__cta', $screen, 'a card renders its button');
        $this->assertStringContainsString('yw-item__badge', $screen, 'a card renders its badge');
        $this->assertStringNotContainsString('yw-items__empty', $screen, 'the gallery must not render an empty list');

        $this->assertStringContainsString('yw-form-row', $screen);
        $this->assertMatchesRegularExpression('/<select class="yw-input"/', $screen);
        $this->assertMatchesRegularExpression('/<input class="yw-input"[^>]*disabled/', $screen);

        $this->assertStringNotContainsString('<textarea', $screen);

        foreach (['success', 'info', 'warning', 'danger'] as $status) {
            $this->assertStringContainsString('yw-alert--' . $status, $screen);
        }
    }

    /** Around the gallery: the list of presets, and the rail that edits one. */
    public function testTheScreenCarriesThePresetListAndTheRail(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('yw-preset-cards', $screen, 'the list of presets');
        $this->assertStringContainsString('yw-preset-card__swatch', $screen, 'a preset is shown as its colours');
        $this->assertStringContainsString('data-yw-preset-new', $screen, 'the button that creates one');
        $this->assertStringContainsString('id="yw-preset-rail"', $screen, 'the drawer that holds both');

        $this->assertStringContainsString('yw-designer__sidebar', $screen);

        $this->assertStringContainsString('name="light[yw-primary]"', $screen);
        $this->assertStringContainsString('name="dark[yw-primary]"', $screen);
        $this->assertStringContainsString('name="light[yw-font-heading]"', $screen);

        $this->assertStringNotContainsString('name="dark[yw-space-md]"', $screen);
    }

    /** Choosing a preset and editing one are two screens of ONE drawer. */
    public function testTheDrawerHoldsTheListAndTheEditorAsTwoScreens(): void
    {
        $screen = $this->screen();

        $this->assertMatchesRegularExpression(
            '/<div class="yw-preset-rail__screen" data-yw-preset-screen="list">/',
            $screen,
            'the list screen must not open hidden -- the drawer opens on it'
        );
        $this->assertMatchesRegularExpression(
            '/<div class="yw-preset-rail__screen" data-yw-preset-screen="edit" hidden>/',
            $screen,
            'the editor is the screen you go to, never the one you arrive on'
        );
        $this->assertStringContainsString('data-yw-preset-back', $screen, 'the way back to the list');
        $this->assertStringContainsString('data-yw-preset-close-rail', $screen, 'the way to shut the drawer');
        $this->assertStringContainsString('data-yw-preset-open', $screen, 'and the way to bring it back');

        $opens = strpos($screen, 'id="yw-preset-rail"');
        $this->assertIsInt($opens, 'the drawer must be on the page');
        $rail = substr($screen, $opens);
        $closes = strpos($rail, '</aside>');
        $this->assertIsInt($closes, 'the drawer must be closed');
        $rail = substr($rail, 0, $closes);
        $this->assertStringContainsString('yw-preset-cards', $rail, 'the list is IN the drawer');
        $this->assertStringContainsString('name="preset_name"', $rail, 'so is the editor');

        $this->assertSame(
            0,
            $this->deepestFormNesting($screen),
            'a form inside a form: the browser drops the inner submit'
        );
    }

    /** How deep `<form>` gets nested inside `<form>` anywhere on the page. */
    private function deepestFormNesting(string $html): int
    {
        preg_match_all('/<(\/?)form\b/i', $html, $matches);

        $depth = 0;
        $deepest = 0;
        foreach ($matches[1] as $slash) {
            if ($slash === '/') {
                $depth = max(0, $depth - 1);
                continue;
            }
            $deepest = max($deepest, $depth);
            $depth++;
        }

        return $deepest;
    }

    /** The top bar, the footer and the headings are the Preset's now (ADR-0021). */
    public function testTheRailOffersTheChromeAndHeadingColours(): void
    {
        $screen = $this->screen();

        for ($level = 1; $level <= 6; $level++) {
            $this->assertStringContainsString(
                'data-yw-preset-slider="light.yw-heading-' . $level . '-size"',
                $screen,
                'h' . $level . ' must be sizeable on its own'
            );
        }

        foreach ([
            'yw-navbar-bg', 'yw-navbar-text', 'yw-footer-bg', 'yw-footer-text',

            'yw-heading-1', 'yw-heading-2', 'yw-heading-3',
            'yw-heading-4', 'yw-heading-5', 'yw-heading-6',
        ] as $token) {
            $this->assertStringContainsString('name="light[' . $token . ']"', $screen);
            $this->assertStringContainsString('name="dark[' . $token . ']"', $screen);
        }
    }

    /** Every measure is a slider, and no measure has a box to type in (ADR-0021). */
    public function testEveryMeasureIsASliderAndNoneIsTyped(): void
    {
        $screen = $this->screen();

        foreach (PresetService::TOKENS as $token => $definition) {
            if ($definition['kind'] !== PresetService::KIND_SIZE) {
                continue;
            }

            $this->assertStringContainsString(
                'data-yw-preset-slider="light.' . $token . '"',
                $screen,
                $token . ' must be a slider'
            );

            $this->assertMatchesRegularExpression(
                '/<input type="hidden" name="light\[' . preg_quote($token, '/') . '\]"/',
                $screen,
                $token . ' must post from a hidden field, not from a box somebody typed in'
            );
            $this->assertStringContainsString(
                'data-yw-preset-readout="light.' . $token . '"',
                $screen,
                $token . ' must say what the number it is on means'
            );
        }

        $this->assertMatchesRegularExpression(
            '/type="range"[^>]*min="12"[^>]*max="24"/',
            $screen,
            'a slider with no bounds is a slider over 0 to 100'
        );

        $this->assertStringNotContainsString('name="light[yw-space-6]"', $screen);
        $this->assertStringNotContainsString('name="light[yw-radius-md]"', $screen);
    }

    /** A derived value is not offered for editing, in either scheme. */
    public function testNoDerivedValueIsOfferedForEditing(): void
    {
        $screen = $this->screen();

        foreach ([
            'yw-primary-hover', 'yw-surface-hover', 'yw-overlay', 'yw-text-muted',
            'yw-border-strong', 'yw-border-subtle', 'yw-focus-ring',
            'yw-success-surface', 'yw-success-text', 'yw-danger-surface', 'yw-danger-text',
            'yw-warning-surface', 'yw-warning-text', 'yw-info-surface', 'yw-info-text',
            'yw-shadow-color', 'yw-shadow-color-strong',
            'yw-radius-sm', 'yw-radius-md', 'yw-radius-lg', 'yw-radius-full',
            'yw-navbar-border', 'yw-navbar-active',
        ] as $derived) {
            $this->assertArrayNotHasKey($derived, PresetService::TOKENS, $derived . ' is derived by core');
            $this->assertStringNotContainsString('name="light[' . $derived . ']"', $screen);
            $this->assertStringNotContainsString('name="dark[' . $derived . ']"', $screen);
        }
    }

    /** A colour that has to be legible on another one is scored against it, per scheme. */
    public function testEveryDeclaredContrastPairIsScoredInBothSchemes(): void
    {
        $screen = $this->screen();
        $scored = 0;

        foreach (PresetService::TOKENS as $token => $definition) {
            if (!isset($definition['contrast'])) {
                continue;
            }
            $scored++;

            if ($definition['contrast'] !== PresetService::CONTRAST_AUTO_INK) {
                $against = $definition['contrast'];
                $qualified = str_contains($against, '.');
                if ($qualified) {
                    [$onScheme, $against] = explode('.', $against, 2);
                    $this->assertContains($onScheme, PresetService::SCHEMES, $token);
                }
                $this->assertArrayHasKey(
                    $against,
                    PresetService::TOKENS,
                    $token . ' is scored against something that is not authored'
                );
            }

            foreach (PresetService::SCHEMES as $scheme) {
                $this->assertStringContainsString(
                    'data-yw-preset-contrast="' . $scheme . '.' . $token . '"',
                    $screen,
                    $token . ' has no ' . $scheme . ' score'
                );

                $expected = match (true) {
                    $definition['contrast'] === PresetService::CONTRAST_AUTO_INK => 'auto-ink',
                    str_contains($definition['contrast'], '.') => $definition['contrast'],
                    default => $scheme . '.' . $definition['contrast'],
                };
                $this->assertStringContainsString('data-against="' . $expected . '"', $screen);
            }
        }

        $this->assertGreaterThanOrEqual(7, $scored, 'the ink/ground pairs worth scoring');

        foreach ([
            'yw-primary', 'yw-secondary', 'yw-tertiary',
            'yw-success', 'yw-danger', 'yw-warning', 'yw-info',
        ] as $fill) {
            $this->assertSame(
                PresetService::CONTRAST_AUTO_INK,
                PresetService::TOKENS[$fill]['contrast'],
                $fill . ' is a fill: it is scored against the ink it gets, not a fixed partner'
            );
            $this->assertArrayHasKey(
                $fill,
                PresetService::INK_FOR,
                $fill . ' is scored against an ink it is never actually given'
            );
        }

        $this->assertNotSame('yw-surface', PresetService::TOKENS['yw-tertiary']['contrast']);
    }

    /** Every colour can be pointed at one of the preset's own, from one shared palette. */
    public function testEveryColourCanBePointedAtThePalette(): void
    {
        $screen = $this->screen();

        $this->assertSame(
            1,
            substr_count($screen, 'id="yw-preset-palette"'),
            'one palette, shared -- not one per field'
        );

        foreach (PresetService::TOKENS as $token => $definition) {
            if ($definition['kind'] !== PresetService::KIND_COLOR) {
                continue;
            }
            foreach (PresetService::SCHEMES as $scheme) {
                $this->assertStringContainsString(
                    'data-yw-preset-palette-open="' . $scheme . '.' . $token . '"',
                    $screen,
                    $token . ' (' . $scheme . ') cannot be pointed at another colour'
                );
            }
        }

        foreach (PresetService::PALETTE as $token) {
            $this->assertStringContainsString('data-yw-preset-palette-pick="' . $token . '"', $screen);
            $this->assertStringContainsString('data-yw-preset-palette-chip="' . $token . '"', $screen);
        }

        $this->assertStringContainsString('data-yw-preset-palette-pick=""', $screen);
    }

    /** The font select separates what costs a download from what does not. */
    public function testFontsAreSplitIntoLocalStacksAndWebfonts(): void
    {
        $screen = $this->screen();
        $presets = $this->getWiki()->services->get(PresetService::class);

        $this->assertStringContainsString('<optgroup label="' . _t('ADMIN_PRESET_FONTS_LOCAL'), $screen);
        $this->assertStringContainsString('<optgroup label="' . _t('ADMIN_PRESET_FONTS_WEB'), $screen);

        foreach (PresetService::FONT_STACKS as $name => $stack) {
            $this->assertStringContainsString('>' . $name . '</option>', $screen);
            $this->assertStringContainsString(
                'style="font-family: ' . htmlspecialchars($stack, ENT_QUOTES) . '"',
                $screen,
                $name . ' is not drawn in its own stack'
            );
        }

        foreach ($presets->webfonts() as $family => $stack) {
            $this->assertStringContainsString('>' . $family . '</option>', $screen, $family);
        }
    }

    /** A webfont is added from a modal, opened from the Type group. */
    public function testAWebfontIsAddedFromAModalOpenedByTheFontSelects(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('data-yw-modal-target="#yw-preset-font-modal"', $screen);
        $this->assertStringContainsString('id="yw-preset-font-modal"', $screen);
        $this->assertStringContainsString('value="install_font"', $screen);
        $this->assertStringContainsString('name="font_family"', $screen);

        $selectAt = strpos($screen, 'name="light[yw-font-mono]"');
        $openerAt = strpos($screen, 'data-yw-modal-target="#yw-preset-font-modal"');
        $this->assertIsInt($selectAt);
        $this->assertIsInt($openerAt);
        $this->assertGreaterThan($selectAt, $openerAt, 'the opener belongs under the font selects');

        $this->assertSame(0, $this->deepestFormNesting($screen), 'the installer must not nest in the editor');

        $this->assertStringNotContainsString('name="font_source"', $screen);
    }

    /** The picker shows a handful of families and draws each in its own face. */
    public function testTheFontPickerIsCappedSoItCanBePreviewed(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('data-yw-tag-input-limit="12"', $screen);
    }

    /** Fonts are picked from Google's catalogue, several at a time, and not free-typed. */
    public function testFontsArePickedFromTheCatalogue(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('data-yw-tag-input-closed', $screen, 'a family Google lacks is a silent failure');
        $this->assertStringContainsString('data-yw-tag-input-options="{}"', $screen, 'the catalogue must not be inlined');
        $this->assertStringContainsString('data-yw-google-fonts="', $screen);
        $this->assertStringContainsString('google-fonts.json', $screen);

        $this->assertMatchesRegularExpression('~data-yw-google-fonts="https?://~', $screen);

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="font_family"[^>]*data-yw-tag-input-value/',
            $screen
        );
    }

    /** The bar and the footer are groups of their own, so their fields need no suffix. */
    public function testTheBarAndTheFooterAreSeparateGroups(): void
    {
        $screen = $this->screen();

        $this->assertArrayHasKey('navbar', PresetService::GROUPS);
        $this->assertArrayHasKey('footer', PresetService::GROUPS);
        $this->assertArrayNotHasKey('chrome', PresetService::GROUPS);

        $this->assertSame('ADMIN_PRESET_TOKEN_BG', PresetService::TOKENS['yw-navbar-bg']['label']);
        $this->assertSame('ADMIN_PRESET_TOKEN_BG', PresetService::TOKENS['yw-footer-bg']['label']);

        $this->assertStringContainsString('name="light[yw-navbar-shadow]"', $screen);
        $this->assertStringContainsString('data-yw-preset-slider="light.yw-navbar-shadow-spread"', $screen);
    }

    /** Adding a font does not reload the screen, and the screen declares what is installed. */
    public function testTheScreenInstallsAFontWithoutLosingWhatIsBeingEdited(): void
    {
        $screen = $this->screen();

        $this->assertMatchesRegularExpression(
            '/data-yw-preset-font-form="[^"]*api\/presets\/fonts"/',
            $screen
        );
        $this->assertStringContainsString('preset_action" value="install_font"', $screen);

        $this->assertStringContainsString('data-yw-preset-font-result', $screen);

        $this->assertMatchesRegularExpression(
            '/<link rel="stylesheet" data-yw-preset-faces\s+href="[^"]*api\/presets\/fonts\.css"/',
            $screen
        );
    }

    /** The preset is named where it is saved, not fifty fields above. */
    public function testTheNameSitsWithTheSaveButton(): void
    {
        $screen = $this->screen();

        $footerAt = strpos($screen, 'yw-preset-rail__save');
        $nameAt = strpos($screen, 'id="yw-preset-name"');
        $this->assertIsInt($footerAt);
        $this->assertIsInt($nameAt);
        $this->assertGreaterThan($footerAt, $nameAt, 'the name belongs in the footer');

        $this->assertMatchesRegularExpression('/id="yw-preset-name"[^>]*aria-label=/', $screen);
    }

    /** Headings can be cased and aligned, per level, from a fixed set of CSS keywords. */
    public function testEachHeadingLevelHasCasingAndAlignment(): void
    {
        $screen = $this->screen();

        for ($level = 1; $level <= 6; $level++) {
            foreach (['transform', 'align'] as $property) {
                $this->assertMatchesRegularExpression(
                    '/<select[^>]*name="light\[yw-heading-' . $level . '-' . $property . '\]"/',
                    $screen,
                    'h' . $level . ' must be able to set its ' . $property
                );
            }
        }

        foreach (array_keys(PresetService::TEXT_TRANSFORMS) as $keyword) {
            $this->assertStringContainsString('value="' . $keyword . '"', $screen);
        }

        $this->assertArrayHasKey('start', PresetService::TEXT_ALIGNMENTS);
        $this->assertArrayNotHasKey('left', PresetService::TEXT_ALIGNMENTS);
    }

    /** The type is still chosen from a list drawn in itself, and the size still slides. */
    public function testTheRailOffersAFontSelect(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('data-yw-preset-slider="light.yw-font-size-base"', $screen);

        foreach (['yw-font-body', 'yw-font-heading', 'yw-font-mono'] as $token) {
            $this->assertMatchesRegularExpression(
                '/<select[^>]*name="light\[' . $token . '\]"/',
                $screen,
                $token . ' is chosen from a list, not typed'
            );
        }

        foreach (PresetService::FONT_STACKS as $name => $stack) {
            $this->assertStringContainsString('>' . $name . '</option>', $screen);
            $this->assertStringContainsString(
                'style="font-family: ' . htmlspecialchars($stack, ENT_QUOTES) . '"',
                $screen,
                $name . ' is not drawn in its own stack'
            );
        }
    }

    /** What left the screen, asserted so it cannot drift back. */
    public function testTheScreenNoLongerCarriesTheThemeSelectors(): void
    {
        $screen = $this->screen();

        $this->assertStringNotContainsString('preset-sidenav', $screen, 'the old theme selector');
        $this->assertStringNotContainsString('adminthemes', $screen, 'the per-page theme table');
        $this->assertStringNotContainsString('setwikidefaulttheme', $screen, 'the default-theme form');
    }

    /** Every preset the wiki ships is offered, plus the way back to no preset at all. */
    public function testEveryShippedPresetIsListed(): void
    {
        $screen = $this->screen();

        foreach (['default', 'fun', 'landes', 'red', 'yellow'] as $preset) {
            $this->assertStringContainsString('value="' . $preset . '.css"', $screen, "the $preset preset");
        }
        $this->assertStringContainsString(_t('ADMIN_PRESET_NONE'), $screen, 'the way back to the theme\'s own colours');
    }

    /** Which preset the wiki wears is said by a filled star, and by exactly one of them. */
    public function testExactlyOneStarIsFilled(): void
    {
        $screen = $this->screen();

        $this->assertSame(
            1,
            substr_count($screen, 'yw-preset-card__star--on'),
            'one filled star: the preset the wiki wears, or the "no preset" card when it wears none'
        );
        $this->assertStringContainsString(
            'icons.svg#star-filled',
            $screen,
            'filled and outline are two different symbols, not the same one tinted'
        );
    }

    public function testAnActionThatNoLongerExistsWouldBeNoticed(): void
    {
        $this->assertStringNotContainsString('does not exist', $this->screen());
        $this->assertStringNotContainsString('INVALID_ACTION', $this->screen());
    }

    private function screen(): string
    {
        if (self::$rendered !== null) {
            return self::$rendered;
        }

        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $acl = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $acl->isAdmin($user['name'])
        ));
        if ($admin === false) {
            $this->markTestSkipped('the preset screen needs an admin to render for');
        }

        $authentication = $wiki->services->get(AuthenticationService::class);
        $authentication->login($admin);

        try {
            $wiki->services->get(CurrentRequest::class)->replace(Request::create('/?admin/preset'));

            return self::$rendered = (string)$wiki->services->get(AdminController::class)->preset()->getContent();
        } finally {
            $authentication->logout();
        }
    }
}

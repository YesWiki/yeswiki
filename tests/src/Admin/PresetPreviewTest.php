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

/**
 * The Preset screen's component gallery (ticket 30).
 *
 * A colour preset is judged against components, not against a paragraph: a contrast that
 * reads well in body text can fail on a button, and a heading scale that works alone can
 * collide inside a panel. So the screen renders every graphical component the wiki has --
 * and this asserts that it really does, because the failure mode is silent. An action that
 * stops existing, or is renamed, leaves *nothing* on the page; the gallery would go on
 * looking fine while quietly previewing less and less.
 *
 * Asserted on what each component actually emits, taken from the renderer rather than
 * guessed: `{{section}}` produces a `<section>` carrying its classes, and `icon="star"`
 * produces an SVG sprite reference, not a font class.
 */
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
            // Markers the *gallery* is the only possible source of. Half of an earlier set
            // proved nothing: `<strong>`, `<em>`, `<code>`, `<table` and `yw-progress` all
            // appear on the screen with the gallery emptied -- the debug SQL panel emits
            // bold and italic, and htmx's loading indicator is a `yw-progress`. Each of
            // these was checked by rendering the screen with an empty gallery and
            // confirming it disappears.
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
            // `[35%]`, the wiki syntax that replaced {{progressbar}} (ticket 30)
            'progress bar' => ['<progress class="yw-progressbar" value="35"'],
            'grid and columns' => ['yw-col'],
            'panel' => ['yw-panel--primary'],
            'accordion' => ['yw-accordion'],
            // the tab's own label, not the id it is given: ids are made unique against
            // process-global state, so the same gallery yields `premier-onglet` alone and
            // something else in a suite where tabs have already been rendered
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
     * The gallery titles each block with the markup that draws it, inside a code span, so
     * the preview doubles as a syntax reference. Those are the only literal `{{…}}` allowed:
     * one anywhere else is a component that failed to run and was printed instead.
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

    /**
     * Every component the gallery opens, it closes.
     *
     * The markers above all assert on *opening* tags, and every one of them passed while
     * the six `{{label}}`s were emitting unclosed spans that nested the rest of the page
     * inside themselves (see ActionTagsOnOneLineTest). Counting is what would have caught
     * it: an element that opens six times and closes five is a broken document however
     * good its first six inches look.
     */
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

    /**
     * The gallery shows lists of entries and whole page layouts, not only single components.
     *
     * A Preset is judged on a page, and the things that go wrong on one are exactly what a
     * component in isolation cannot show: two cards of different title lengths in a row, the
     * blank between a sidebar panel and the one under it, a heading colour against the card
     * surface rather than against the page. So the gallery renders the three list
     * presentations and the two layouts every wiki actually writes.
     *
     * Through `@core/presentations/*`, the SAME templates a page uses, on fabricated items:
     * hand-written markup here would agree with itself and drift from what the wiki emits,
     * and a real entry list needs a form and entries -- which a wiki being set up for the
     * first time, on this very screen, has neither of.
     */
    public function testTheGalleryShowsListsAndLayouts(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('yw-items--card', $screen, 'a list of cards');
        $this->assertStringContainsString('yw-items--list', $screen, 'a list of rows');
        $this->assertStringContainsString('yw-items--table', $screen, 'a list as a table');
        // COUNTED, not merely present. The presentation templates emit indented markup, and
        // Markdown reads a line indented by four spaces as a code block: including one raw
        // rendered the first card and printed the other five as escaped source. Every class
        // name above was still in the page, so nothing but a count catches it -- and what a
        // card grid is being judged on here is precisely the second row.
        $this->assertSame(6, substr_count($screen, 'yw-item--card'), 'six cards, or the grid never wraps');
        $this->assertSame(3, substr_count($screen, 'yw-item--list'), 'three rows');
        // four of the six cards carry an image and two of the three rows do: a grid whose
        // items all have one, or none, is the one arrangement that never misaligns
        $this->assertSame(6, substr_count($screen, 'yw-item__image'), 'a list must mix items with and without an image');
        $this->assertStringNotContainsString('&lt;article', $screen, 'markup printed as source instead of rendered');

        $this->assertStringContainsString('yw-item__cta', $screen, 'a card renders its button');
        $this->assertStringContainsString('yw-item__badge', $screen, 'a card renders its badge');
        $this->assertStringNotContainsString('yw-items__empty', $screen, 'the gallery must not render an empty list');

        // a form: its border, corner, focus ring and label gap are a Preset's, and nothing
        // else in the gallery shows any of them
        $this->assertStringContainsString('yw-form-row', $screen);
        $this->assertMatchesRegularExpression('/<select class="yw-input"/', $screen);
        $this->assertMatchesRegularExpression('/<input class="yw-input"[^>]*disabled/', $screen);
        // and NOT a text area: the formatter escapes one, as it must -- wiki content able to
        // inject a text area is wiki content able to rewrite the form around it. Asserted so
        // that adding one back is a failing test rather than a silently unclosed element
        // swallowing the rest of the gallery.
        $this->assertStringNotContainsString('<textarea', $screen);

        // the four status colours as the alerts they paint -- the derived surface and ink
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
        // the drawer is the same docked panel the actions builder and file picker use
        $this->assertStringContainsString('yw-designer__sidebar', $screen);
        // one input per Design token per Colour scheme, named as the property it writes
        $this->assertStringContainsString('name="light[yw-primary]"', $screen);
        $this->assertStringContainsString('name="dark[yw-primary]"', $screen);
        $this->assertStringContainsString('name="light[yw-font-heading]"', $screen);
        // ...and nothing but the light block for what is not a colour: a spacing step that
        // could be authored twice would be a Preset whose rhythm changed at dusk
        $this->assertStringNotContainsString('name="dark[yw-space-md]"', $screen);
    }

    /**
     * Choosing a preset and editing one are two screens of ONE drawer.
     *
     * The list used to be a column beside the gallery and the editor a rail over it, so the
     * gallery -- the thing being judged -- got two thirds of the width, and the two controls
     * for it were in different places. Both are in the drawer now, one showing at a time.
     *
     * Three things are asserted because each fails silently on its own: the drawer opens on
     * the LIST (it carries no `hidden`, and the editor screen does), the way back to the list
     * exists, and no form is nested inside another -- the cards each post their own star,
     * copy and delete, and a browser handed `<form>` inside `<form>` simply drops the inner
     * one's submit, which looks like a button that does nothing.
     */
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

        // The cards and the editor live in the same <aside>, as siblings. Both offsets are
        // asserted before they are used: `substr($screen, false)` is `substr($screen, 0)`,
        // so a missing marker would quietly widen this to the whole page and every
        // assertion below it would pass without the drawer existing at all.
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

    /**
     * How deep `<form>` gets nested inside `<form>` anywhere on the page. 0 means never.
     *
     * A scan rather than a regex on purpose: the obvious pattern for this is
     * `<form\b(?:(?!<\/form>).)*<form\b`, and on a 200KB screen PCRE gives up and
     * `preg_match` returns **false** -- which `assertSame(0, ...)` catches but
     * `if (preg_match(...))` would have read as "no nesting found". A check that can say
     * "no" by running out of budget is not a check.
     */
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

    /**
     * The top bar, the footer and the headings are the Preset's now (ADR-0021).
     *
     * All three used to be a theme's hard-coded business, and the only way to get a coloured
     * top bar was to pick a second theme *style* -- a whole stylesheet, in a different
     * screen, whose entire content was three rules. These four colours replaced it, so if
     * they stop being offered here there is no way left to colour a bar at all.
     */
    public function testTheRailOffersTheChromeAndHeadingColours(): void
    {
        $screen = $this->screen();

        // ...and a size slider per level, which is the other half of "distinct per level"
        for ($level = 1; $level <= 6; $level++) {
            $this->assertStringContainsString(
                'data-yw-preset-slider="light.yw-heading-' . $level . '-size"',
                $screen,
                'h' . $level . ' must be sizeable on its own'
            );
        }

        foreach ([
            'yw-navbar-bg', 'yw-navbar-text', 'yw-footer-bg', 'yw-footer-text',
            // one colour per heading level: two (h1-h3, h4-h6) could not say "my h2 sits
            // too close to my h1", and no level could have a colour of its own
            'yw-heading-1', 'yw-heading-2', 'yw-heading-3',
            'yw-heading-4', 'yw-heading-5', 'yw-heading-6',
        ] as $token) {
            // a colour, so both schemes: a dark bar under a light page has to be able to
            // become a light bar under a dark one
            $this->assertStringContainsString('name="light[' . $token . ']"', $screen);
            $this->assertStringContainsString('name="dark[' . $token . ']"', $screen);
        }
    }

    /**
     * Every measure is a slider, and no measure has a box to type in (ADR-0021).
     *
     * This is the whole of what "simplify the presets" meant for this rail: sixteen typed
     * lengths -- eleven spacing steps, four radii, the type size -- became eight sliders,
     * because a measure is a multiple of the base size and the only way to choose one is to
     * drag it while watching the gallery repaint.
     *
     * Asserted in both directions, because both failures are silent: a slider whose bounds
     * went missing still slides (over 0 to 100), and a text input that came back still looks
     * like a control while quietly letting somebody type `0.375rem` again.
     */
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
            // what posts is hidden: the slider is the control, so a text box beside it would
            // be a second way to say the same thing, disagreeing with the first
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
        // the three spacing steps and the four multipliers: eleven steps and four radii went
        $this->assertStringNotContainsString('name="light[yw-space-6]"', $screen);
        $this->assertStringNotContainsString('name="light[yw-radius-md]"', $screen);
    }

    /**
     * A derived value is not offered for editing, in either scheme.
     *
     * The eighteen tokens ADR-0021 retired are computed by core from the ones above them. A
     * field for one reappearing here would not throw or look wrong -- it would simply start
     * writing a value into presets that core is about to override with its own, which is the
     * silent half of the failure this test exists for.
     */
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

    /**
     * A colour that has to be legible on another one is scored against it, per scheme.
     *
     * The pairing is the server's, because only the token table knows that
     * `--yw-navbar-text` sits on the bar rather than on the page. Two badges per colour and
     * not one: ink that clears AA on a white page can fail on the near-black one, which is
     * the commonest way a hand-authored dark set goes wrong and precisely what a single
     * badge would average away.
     */
    public function testEveryDeclaredContrastPairIsScoredInBothSchemes(): void
    {
        $screen = $this->screen();
        $scored = 0;

        foreach (PresetService::TOKENS as $token => $definition) {
            if (!isset($definition['contrast'])) {
                continue;
            }
            $scored++;

            // What it is scored against must be either a real token or the `auto-ink`
            // sentinel -- anything else and the badge reads a field that is not on the
            // screen, and silently shows nothing at all.
            if ($definition['contrast'] !== PresetService::CONTRAST_AUTO_INK) {
                $this->assertArrayHasKey(
                    $definition['contrast'],
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
                // `auto-ink` carries no scheme: the ink pair is scheme-independent, and
                // which of the two a fill gets is worked out in the browser
                $this->assertStringContainsString(
                    $definition['contrast'] === PresetService::CONTRAST_AUTO_INK
                        ? 'data-against="auto-ink"'
                        : 'data-against="' . $scheme . '.' . $definition['contrast'] . '"',
                    $screen
                );
            }
        }

        $this->assertGreaterThanOrEqual(7, $scored, 'the ink/ground pairs worth scoring');

        // Every FILL is scored against `auto-ink` -- the ink it will actually be given --
        // rather than against a named partner. A fill has no fixed partner: core picks the
        // more legible of the preset's two inks for it, so naming one here would score the
        // pair that does not happen. This is also what caught white-on-yellow: the warning
        // fill used to take `--yw-text-inverse`, which flips with the SCHEME rather than
        // with the colour underneath it.
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

        // ...and a fill that is not ink keeps no partner of its own: `--yw-tertiary` is a
        // background, so it is scored on what sits ON it, never against the page
        $this->assertNotSame('yw-surface', PresetService::TOKENS['yw-tertiary']['contrast']);
    }

    /**
     * Every colour can be pointed at one of the preset's own, from one shared palette.
     *
     * One popover for all forty-four colour fields rather than one each: its chips are
     * painted from the live values when it opens, so a per-field copy would be forty-four
     * sets of the same fourteen swatches to keep in step as the fields are dragged.
     */
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

        // the way back to a colour of its own: without it a field pointed at the brand could
        // be re-pointed but never un-pointed
        $this->assertStringContainsString('data-yw-preset-palette-pick=""', $screen);
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
        // every stack offered, each option drawn in itself -- the option's own preview
        foreach (PresetService::FONT_STACKS as $name => $stack) {
            $this->assertStringContainsString('>' . $name . '</option>', $screen);
            $this->assertStringContainsString(
                'style="font-family: ' . htmlspecialchars($stack, ENT_QUOTES) . '"',
                $screen,
                $name . ' is not drawn in its own stack'
            );
        }
    }

    /**
     * What left the screen, asserted so it cannot drift back.
     *
     * The theme/squelette/style selector is going to the configuration file and per-page
     * themes are a property of the content -- both are somebody else's screen now, and
     * either one reappearing here is the reorganisation quietly coming undone.
     */
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

    /**
     * Which preset the wiki wears is said by a filled star, and by exactly one of them.
     *
     * The star is on every card -- outline on the others -- because a state read off a mark
     * present on one card and absent on the rest is a state nobody sees. Two filled stars
     * would mean the screen is claiming the wiki wears two presets at once.
     */
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
        // how Performer reports an unknown action -- the gallery must contain no such report
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

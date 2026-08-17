<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * The wiki's Presets: what they are, which one is on, whether one is complete, and how to
 * write one.
 *
 * A Preset is a complete set of Design token values (ADR-0020), authored as one CSS file and
 * linked last by CoreAssets so it beats core's own defaults. Two kinds live side by side:
 *
 *  - **shipped** ones, `themes/<theme>/presets/*.css` (with `custom/themes/…` shadowing the
 *    source tree, as everywhere else). They belong to the code, which on a farm is shared
 *    between instances and replaced on upgrade -- so nothing here writes to them.
 *  - **instance** ones, `custom/css-presets/*.css`, named `custom/<file>` wherever a preset
 *    is referred to by name. Those are the wiki's own, and the only ones that can be edited.
 *
 * A shipped preset is therefore not editable at all. duplicate() copies one into the
 * instance and the copy is edited: that is the only honest answer on a shared code tree,
 * and it means an upgrade can never silently undo somebody's colours.
 *
 * save() *replaces* the preset it is given, renaming its file if the name changed, and only
 * creates when passed no id. Editing something must not quietly leave a second copy of it
 * behind for the wiki to choose between.
 *
 * **Complete or an error.** Every token, colour tokens once per Colour scheme and the
 * scheme-independent ones once. missingIn() names what a file leaves out and save() refuses
 * to write a gap; nothing here fills one in, because a hover colour quietly inherited from
 * somebody else's brand is the failure this rule exists to prevent. A preset migrated from
 * the pre-ADR-0020 nine variables is therefore *incomplete*, and says so on the screen until
 * a webmaster finishes it.
 *
 * The scanning here is filesystem-first rather than going through ThemeManager::getTemplates(),
 * which is only populated once a page render has reached loadTemplates() -- an admin screen
 * renders its body *before* that, and would have found no presets at all.
 */
class PresetService
{
    /** A colour token: declared once per Colour scheme. */
    public const KIND_COLOR = 'color';
    /**
     * A colour that does NOT flip: one value, both schemes.
     *
     * There is exactly one, and it is the exception that proves the rule -- `--yw-text-on-dark`
     * is the ink for a ground this stylesheet did not choose (an author's section colour, an
     * entry's own colour on a map marker, a lightbox's black). That ground does not change
     * with the viewer's scheme, so neither may the ink on it.
     */
    public const KIND_COLOR_FIXED = 'color-fixed';
    /**
     * A measure: a slider, one value, both schemes.
     *
     * There is one kind for all of them because they are all the same control -- ADR-0021's
     * rule is that a measure is chosen by dragging, never by typing a length. Each carries
     * `min`, `max`, `step` and `unit`; `unit` is what the number means:
     *
     *   `px`  -- the wiki's base type size, and the border width. Absolute by necessity:
     *            everything else is a multiple of the first of these.
     *   `rem` -- a spacing step. `rem` IS the base size (core sets `html`'s font-size from
     *            `--yw-font-size-base`), so this is literally "a multiple of the base size"
     *            and a preset that asks for bigger type gets bigger gaps for free.
     *   `''`  -- a bare multiplier: heading scale, corner roundness, shadow strength.
     */
    public const KIND_SIZE = 'size';
    /** A font stack: one value, both schemes. */
    public const KIND_FONT = 'font';
    /**
     * One of a fixed set of CSS keywords: a select, one value, both schemes.
     *
     * `options` is the set, as `css value => translation key`. The value goes into the
     * stylesheet verbatim, so it has to be a keyword the property actually takes -- this is
     * the one kind where a Preset writes CSS grammar rather than a colour or a measure.
     */
    public const KIND_CHOICE = 'choice';

    /**
     * Every Design token a Preset AUTHORS, in the order the editing rail offers them.
     *
     * This list *is* the contract: core's `:root` in styles/yw-core.css declares exactly
     * these, a Preset must declare exactly these, and validation is `array_diff` against
     * them. Anything else a preset file carries (an `@font-face` block, a rule of its own)
     * is kept on disk untouched but is not offered for editing.
     *
     * **What is NOT here is the point of ADR-0021.** The set was 49 tokens and is 31,
     * because core now DERIVES the values that were only ever a function of another one:
     * `--yw-primary-hover`, `--yw-text-muted`, `--yw-surface-hover`, the two border
     * shades, the focus ring, the navbar's own border and active colour, both shadow
     * colours, the three radius steps, and -- the eight that dominated the rail -- the
     * surface and text of each status colour. Core's derived block says why each one.
     * A file that still declares one of them is not an error; the declaration simply
     * wins over core's, which is what any hand-written CSS in a preset does.
     *
     * `scheme` is what the two-block structure hangs off: a colour is authored twice, once
     * light and once dark, and everything else once. `--yw-navbar-height` is deliberately
     * absent -- it is a Layout setting, written inline on <html> by /admin/layout, and a
     * Preset's copy of it could never take effect.
     *
     * The label keys live here rather than being built from the token name in the template:
     * `_t('ADMIN_PRESET_TOKEN_' ~ name|upper)` would work and would be invisible to every
     * tool that looks for translation keys by grepping for them.
     *
     * `row` puts two fields on one line. The rail was a single column forty-odd rows tall,
     * which meant the gallery it repaints was never on screen with the control being dragged
     * -- and the pairs it now makes are the ones you actually compare: a colour and the ink
     * on it, a surface and the card on it, success beside danger, each heading's colour
     * beside its size. Tokens sharing a value share a line; anything without one gets a line
     * to itself. Grouped in rowsByGroup() rather than in the template, because Twig cannot
     * chunk a filtered map without building the list anyway.
     *
     * `contrast` names the token this one has to be LEGIBLE AGAINST, and the rail scores the
     * pair live against WCAG. Only unambiguous pairs are declared -- ink and the ground it
     * actually sits on. The four status colours deliberately have none: what is read is the
     * derived `--yw-*-text`, not the authored colour, so scoring the authored one would
     * report a failure on a warning yellow that nothing ever draws text in.
     *
     * @var array<string, array{kind: string, group: string, label: string, min?: int|float, max?: int|float, step?: int|float, unit?: string, contrast?: string, row?: string, options?: array<string, string>}>
     */
    public const TOKENS = [
        'yw-primary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_PRIMARY', 'contrast' => 'auto-ink', 'row' => 'brand-a'],
        'yw-secondary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_SECONDARY', 'contrast' => 'auto-ink', 'row' => 'brand-b'],
        'yw-tertiary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_TERTIARY', 'contrast' => 'auto-ink', 'row' => 'brand-b'],

        // THE TWO INKS, and the only text colours a Preset sets. Scheme-INDEPENDENT on
        // purpose: "the ink for a light ground" means the same thing at midnight as at noon,
        // because a light ground is light in both schemes.
        //
        // Everything else about text falls out of them. `--yw-text` is whichever one suits
        // the scheme in force -- the light-ground ink on a light page, the dark-ground ink on
        // a dark one -- and `--yw-text-inverse` is the other. Core picks between them again
        // per fill (INK_FOR), and again for a ground a page author chose. So the question is
        // asked once and answered everywhere, instead of being asked four times and kept in
        // step by hand.
        //
        // `contrast` is scheme-qualified here, which nothing else needs: each ink is the page
        // text of ONE scheme, so scoring it against the surface of the other would report on
        // a pairing that never happens.
        'yw-ink-on-light' => ['kind' => self::KIND_COLOR_FIXED, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_INK_ON_LIGHT', 'contrast' => 'light.yw-surface', 'row' => 'ink'],
        'yw-ink-on-dark' => ['kind' => self::KIND_COLOR_FIXED, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_INK_ON_DARK', 'contrast' => 'dark.yw-surface', 'row' => 'ink'],

        'yw-surface' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE', 'row' => 'surf-a'],
        'yw-surface-raised' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE_RAISED', 'row' => 'surf-b'],
        'yw-surface-sunken' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE_SUNKEN', 'row' => 'surf-b'],

        'yw-border' => ['kind' => self::KIND_COLOR, 'group' => 'lines', 'label' => 'ADMIN_PRESET_TOKEN_BORDER', 'row' => 'lines'],
        // in pixels rather than a multiple of the type size: a hairline is a hairline at
        // every text size, and the useful settings are 0, 1 and 2 -- a range no ratio hits
        'yw-border-width' => ['kind' => self::KIND_SIZE, 'group' => 'lines', 'label' => 'ADMIN_PRESET_TOKEN_BORDER_WIDTH', 'min' => 0, 'max' => 4, 'step' => 1, 'unit' => 'px', 'row' => 'lines'],

        // one colour each: the panel behind a message and the ink on it are derived from
        // this against the page's own surface and text, and therefore correct in both
        // schemes without anybody authoring a second set (ADR-0021)
        'yw-success' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_SUCCESS', 'contrast' => 'auto-ink', 'row' => 'status-a'],
        'yw-danger' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_DANGER', 'contrast' => 'auto-ink', 'row' => 'status-a'],
        'yw-warning' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_WARNING', 'contrast' => 'auto-ink', 'row' => 'status-b'],
        'yw-info' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_INFO', 'contrast' => 'auto-ink', 'row' => 'status-b'],

        // Two groups, not one. As `chrome` they were four fields whose labels had to carry
        // "de la barre" and "du pied de page" to say which was which -- a group heading does
        // that better, and says it once.
        'yw-navbar-bg' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_BG', 'row' => 'navbar-a'],
        'yw-navbar-text' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_INK', 'contrast' => 'yw-navbar-bg', 'row' => 'navbar-a'],
        // The bar's own shadow: what lifts it off the page it is sticky over. Its colour is
        // separate from `--yw-shadow-color` because a coloured bar wants a shadow tinted to
        // match, and the spread is what decides whether the bar floats or sits flat -- 0 is a
        // legitimate setting and is what a bar with a hairline border wants.
        'yw-navbar-shadow' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_SHADOW', 'row' => 'navbar-b'],
        'yw-navbar-shadow-spread' => ['kind' => self::KIND_SIZE, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_SHADOW_SPREAD', 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px', 'row' => 'navbar-b'],

        'yw-footer-bg' => ['kind' => self::KIND_COLOR, 'group' => 'footer', 'label' => 'ADMIN_PRESET_TOKEN_BG', 'row' => 'footer-a'],
        'yw-footer-text' => ['kind' => self::KIND_COLOR, 'group' => 'footer', 'label' => 'ADMIN_PRESET_TOKEN_INK', 'contrast' => 'yw-footer-bg', 'row' => 'footer-a'],

        // ONE COLOUR AND ONE SIZE PER LEVEL. This was two colours (h1-h3, h4-h6) and a
        // single scale multiplying a ramp core wrote -- which meant a wiki could say "bigger
        // titles" but not "my h2 is too close to my h1", and could not give a level a colour
        // of its own at all. Six of each is more fields than anything else in this rail, and
        // that is the trade: a heading ramp is the one thing on a wiki that people really do
        // want to set level by level.
        //
        // The sizes are in `rem`, so they are multiples of the base type size like every
        // other measure here -- and `rem` rather than `em` because `em` compounds, which is
        // what used to make the same heading come out one size in a page and another inside
        // a panel.
        // Grouped BY LEVEL, not by property: four fields for h1, then four for h2. The
        // question anybody actually has is "what does my h2 look like", and answering it
        // meant reading four lists in parallel when the tokens were ordered the other way.
        // Two rows each -- colour beside size, casing beside alignment.

        'yw-heading-1' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_1', 'contrast' => 'yw-surface', 'row' => 'h1-a'],
        'yw-heading-1-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_1_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h1-a'],
        'yw-heading-1-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_1_TRANSFORM', 'row' => 'h1-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-1-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_1_ALIGN', 'row' => 'h1-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-heading-2' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_2', 'contrast' => 'yw-surface', 'row' => 'h2-a'],
        'yw-heading-2-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_2_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h2-a'],
        'yw-heading-2-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_2_TRANSFORM', 'row' => 'h2-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-2-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_2_ALIGN', 'row' => 'h2-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-heading-3' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_3', 'contrast' => 'yw-surface', 'row' => 'h3-a'],
        'yw-heading-3-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_3_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h3-a'],
        'yw-heading-3-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_3_TRANSFORM', 'row' => 'h3-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-3-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_3_ALIGN', 'row' => 'h3-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-heading-4' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_4', 'contrast' => 'yw-surface', 'row' => 'h4-a'],
        'yw-heading-4-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_4_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h4-a'],
        'yw-heading-4-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_4_TRANSFORM', 'row' => 'h4-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-4-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_4_ALIGN', 'row' => 'h4-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-heading-5' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_5', 'contrast' => 'yw-surface', 'row' => 'h5-a'],
        'yw-heading-5-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_5_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h5-a'],
        'yw-heading-5-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_5_TRANSFORM', 'row' => 'h5-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-5-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_5_ALIGN', 'row' => 'h5-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-heading-6' => ['kind' => self::KIND_COLOR, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_6', 'contrast' => 'yw-surface', 'row' => 'h6-a'],
        'yw-heading-6-size' => ['kind' => self::KIND_SIZE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_6_SIZE', 'min' => 0.5, 'max' => 4, 'step' => 0.05, 'unit' => 'rem', 'row' => 'h6-a'],
        'yw-heading-6-transform' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_6_TRANSFORM', 'row' => 'h6-b', 'options' => self::TEXT_TRANSFORMS],
        'yw-heading-6-align' => ['kind' => self::KIND_CHOICE, 'group' => 'titles', 'label' => 'ADMIN_PRESET_TOKEN_HEADING_6_ALIGN', 'row' => 'h6-b', 'options' => self::TEXT_ALIGNMENTS],

        'yw-font-body' => ['kind' => self::KIND_FONT, 'group' => 'type', 'label' => 'ADMIN_PRESET_TOKEN_FONT_BODY'],
        'yw-font-heading' => ['kind' => self::KIND_FONT, 'group' => 'type', 'label' => 'ADMIN_PRESET_TOKEN_FONT_HEADING'],
        'yw-font-mono' => ['kind' => self::KIND_FONT, 'group' => 'type', 'label' => 'ADMIN_PRESET_TOKEN_FONT_MONO'],
        // the bounds are the slider's: below 12px body text stops being readable and above
        // 24px a page of it stops fitting, so the range is where a *body* size can sensibly
        // land rather than everything a length can be
        'yw-font-size-base' => ['kind' => self::KIND_SIZE, 'group' => 'type', 'label' => 'ADMIN_PRESET_TOKEN_FONT_SIZE', 'min' => 12, 'max' => 24, 'step' => 1, 'unit' => 'px'],

        // THREE steps, not eleven -- and each on TWO AXES. What a rule actually chooses
        // between is "inside a control", "inside a component" and "between components";
        // the eleven-step ramp asked for eleven typed lengths whose middle six nobody
        // could tell apart. The axes are separate because text is wider than it is tall:
        // the blank that reads as comfortable beside a word is not the one that reads as
        // comfortable above a line of them, and a single number per step gives you either
        // squashed buttons or loose paragraphs, never both right.
        'yw-space-sm-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_SM_Y', 'min' => 0, 'max' => 1, 'step' => 0.05, 'unit' => 'rem', 'row' => 'sm'],
        'yw-space-sm-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_SM_X', 'min' => 0, 'max' => 1, 'step' => 0.05, 'unit' => 'rem', 'row' => 'sm'],
        'yw-space-md-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_MD_Y', 'min' => 0, 'max' => 2, 'step' => 0.05, 'unit' => 'rem', 'row' => 'md'],
        'yw-space-md-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_MD_X', 'min' => 0, 'max' => 2, 'step' => 0.05, 'unit' => 'rem', 'row' => 'md'],
        'yw-space-lg-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_LG_Y', 'min' => 0, 'max' => 6, 'step' => 0.05, 'unit' => 'rem', 'row' => 'lg'],
        'yw-space-lg-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_LG_X', 'min' => 0, 'max' => 6, 'step' => 0.05, 'unit' => 'rem', 'row' => 'lg'],

        // 0 is a real setting for both, and the reason they are sliders rather than
        // lengths: "square and flat" is one drag from "round and soft"
        'yw-radius-scale' => ['kind' => self::KIND_SIZE, 'group' => 'shape', 'label' => 'ADMIN_PRESET_TOKEN_RADIUS_SCALE', 'min' => 0, 'max' => 3, 'step' => 0.1, 'unit' => '', 'row' => 'shape'],
        'yw-shadow-strength' => ['kind' => self::KIND_SIZE, 'group' => 'shape', 'label' => 'ADMIN_PRESET_TOKEN_SHADOW_STRENGTH', 'min' => 0, 'max' => 3, 'step' => 0.1, 'unit' => '', 'row' => 'shape'],
    ];

    /**
     * The tokens of one group, chunked into the lines the rail lays them out on.
     *
     * @return array<string, list<list<string>>> group => rows => token names
     */
    public static function rowsByGroup(): array
    {
        $rows = [];
        foreach (self::TOKENS as $name => $definition) {
            $rows[$definition['group']][$definition['row'] ?? $name][] = $name;
        }

        return array_map(
            static fn (array $group): array => array_values($group),
            $rows
        );
    }

    /** The groups the rail lays the tokens out in, in order, with the heading for each. */
    public const GROUPS = [
        'brand' => 'ADMIN_PRESET_GROUP_BRAND',
        'surfaces' => 'ADMIN_PRESET_GROUP_SURFACES',
        'lines' => 'ADMIN_PRESET_GROUP_LINES',
        'status' => 'ADMIN_PRESET_GROUP_STATUS',
        'navbar' => 'ADMIN_PRESET_GROUP_NAVBAR',
        'footer' => 'ADMIN_PRESET_GROUP_FOOTER',
        'titles' => 'ADMIN_PRESET_GROUP_TITLES',
        'type' => 'ADMIN_PRESET_GROUP_TYPE',
        'spacing' => 'ADMIN_PRESET_GROUP_SPACING',
        'shape' => 'ADMIN_PRESET_GROUP_SHAPE',
    ];

    /**
     * `contrast => 'auto-ink'`: score this colour against whichever ink it will actually get.
     *
     * A fill has no fixed partner -- core picks the more legible of the two inks for it -- so
     * naming one in the table would score the pair that does not happen.
     */
    public const CONTRAST_AUTO_INK = 'auto-ink';

    /**
     * The fills core puts text on, and the property holding the ink it picked for each.
     *
     * **These are RESOLVED, not derived.** Everything else core computes is a `color-mix()`
     * in the stylesheet, re-resolving by itself whenever what it depends on changes. This one
     * cannot be: choosing between two authored colours needs the *luminance* of a third, and
     * CSS will not give you that as a number. Measured, not assumed -- `oklch(from …)` and
     * `contrast-color()` both work in the browsers this release supports, and both only ever
     * answer black or white, which is not what somebody who set an off-white ink asked for.
     *
     * So the choice is made where the numbers are: written into the file by save(), and
     * recomputed live by the rail while you drag. The cost is that it is the one computed
     * value that can go stale -- hand-edit `--yw-primary` in a preset file without saving
     * from the screen, and its ink keeps the answer for the old colour. resolve() is public
     * so anything rewriting a preset can put that right.
     *
     * @var array<string, string> fill token => the property its ink is written to
     */
    public const INK_FOR = [
        'yw-primary' => 'yw-on-primary',
        'yw-secondary' => 'yw-on-secondary',
        'yw-tertiary' => 'yw-on-tertiary',
        'yw-success' => 'yw-on-success',
        'yw-danger' => 'yw-on-danger',
        'yw-warning' => 'yw-on-warning',
        'yw-info' => 'yw-on-info',
    ];

    /**
     * How a heading's letters are cased. `none` leaves what the author typed.
     *
     * @var array<string, string>
     */
    public const TEXT_TRANSFORMS = [
        'none' => 'ADMIN_PRESET_TRANSFORM_NONE',
        'uppercase' => 'ADMIN_PRESET_TRANSFORM_UPPERCASE',
        'capitalize' => 'ADMIN_PRESET_TRANSFORM_CAPITALIZE',
        'lowercase' => 'ADMIN_PRESET_TRANSFORM_LOWERCASE',
    ];

    /**
     * Where a heading sits on its line.
     *
     * `start` and `end` rather than `left` and `right`: they mean the same thing in every
     * language YesWiki currently ships, and the right thing in one written right-to-left.
     * The labels say left and right, because that is what somebody choosing is looking at.
     *
     * @var array<string, string>
     */
    public const TEXT_ALIGNMENTS = [
        'start' => 'ADMIN_PRESET_ALIGN_LEFT',
        'center' => 'ADMIN_PRESET_ALIGN_CENTER',
        'end' => 'ADMIN_PRESET_ALIGN_RIGHT',
    ];

    /** The two Colour schemes a colour token is authored in. */
    public const SCHEMES = ['light', 'dark'];

    /**
     * The type a preset can be set in: the stacks from modernfontstacks.com, verbatim.
     *
     * Every one of these is a list of fonts already on the reader's machine, ending in a
     * generic -- so a preset that uses one downloads nothing, waits for nothing, and looks
     * the same on the second page as on the first. That is the whole argument for them here:
     * the alternative this screen used to offer was a webfont name, which means a file
     * fetched from Google on save (installAndGetCSSForFont), served from the instance
     * afterwards, and a paragraph that reflows once it arrives.
     *
     * The names are the taxonomy's, not descriptions -- "Didone" and "Neo-Grotesque" are what
     * these shapes are called -- so they are not translated. What tells you what one looks
     * like is the option being drawn in it, which no translation improves.
     *
     * Order is the site's. Kept as one map so that what the select offers and what
     * isSystemStack() recognises cannot drift apart.
     */
    public const FONT_STACKS = [
        'System UI' => 'system-ui, sans-serif',
        'Transitional' => "Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif",
        'Old Style' => "'Iowan Old Style', 'Palatino Linotype', 'URW Palladio L', P052, serif",
        'Humanist' => "Seravek, 'Gill Sans Nova', Ubuntu, Calibri, 'DejaVu Sans', source-sans-pro, sans-serif",
        'Geometric Humanist' => "Avenir, Montserrat, Corbel, 'URW Gothic', source-sans-pro, sans-serif",
        'Classical Humanist' => "Optima, Candara, 'Noto Sans', source-sans-pro, sans-serif",
        'Neo-Grotesque' => "Inter, Roboto, 'Helvetica Neue', 'Arial Nova', 'Nimbus Sans', Arial, sans-serif",
        'Monospace Slab Serif' => "'Nimbus Mono PS', 'Courier New', monospace",
        'Monospace Code' => "ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas, 'DejaVu Sans Mono', monospace",
        'Industrial' => "Bahnschrift, 'DIN Alternate', 'Franklin Gothic Medium', 'Nimbus Sans Narrow', sans-serif-condensed, sans-serif",
        'Rounded Sans' => "ui-rounded, 'Hiragino Maru Gothic ProN', Quicksand, Comfortaa, Manjari, 'Arial Rounded MT', 'Arial Rounded MT Bold', Calibri, source-sans-pro, sans-serif",
        'Slab Serif' => "Rockwell, 'Rockwell Nova', 'Roboto Slab', 'DejaVu Serif', 'Sitka Small', serif",
        'Antique' => "Superclarendon, 'Bookman Old Style', 'URW Bookman', 'URW Bookman L', 'Georgia Pro', Georgia, serif",
        'Didone' => "Didot, 'Bodoni MT', 'Noto Serif Display', 'URW Palladio L', P052, Sylfaen, serif",
        'Handwritten' => "'Segoe Print', 'Bradley Hand', Chilanka, TSCu_Comic, casual, cursive",
    ];

    /**
     * The colours a field can be pointed AT, rather than given a value of its own.
     *
     * Picking one writes `var(--yw-primary)` into the field, and the two then stay in step:
     * "my h1 is the brand colour" is a relationship the file keeps, not a hex somebody
     * copied once. With six heading colours in two schemes, the alternative was twelve
     * literals to re-edit by hand every time the brand moved.
     *
     * CURATED, and much shorter than the colour set. Every authored colour would be
     * twenty-two swatches, which is a wall rather than a palette; these are the ones another
     * colour plausibly wants to BE. Deliberately absent: the six heading colours and the four
     * chrome ones, which are the things that point at these rather than the other way round,
     * and `--yw-text-on-dark`, which is scheme-independent and would mean something different
     * in each block.
     *
     * @var list<string>
     */
    public const PALETTE = [
        'yw-primary',
        'yw-secondary',
        'yw-tertiary',
        'yw-surface',
        'yw-surface-raised',
        'yw-surface-sunken',
        'yw-ink-on-light',
        'yw-ink-on-dark',
        'yw-border',
        'yw-success',
        'yw-danger',
        'yw-warning',
        'yw-info',
    ];

    /**
     * Webfonts the rail offers: families fetched from Google and served from the instance.
     *
     * A short, opinionated list rather than Google's whole catalogue. Enumerating it would
     * mean a network call to draw the screen and a thousand names in a select; these are
     * families that cover the same ground FONT_STACKS does -- a workhorse sans, a reading
     * serif, a couple with character, a monospace -- and pair with each other.
     *
     * Anything already downloaded under `custom/fonts/` is offered too, whether it is here or
     * not (see webfonts()), so a webmaster who fetched something else keeps it.
     *
     * **These cost what FONT_STACKS does not.** Choosing one means the file is fetched from
     * Google when the preset is saved, stored under `custom/fonts/` and served from the
     * instance afterwards -- so readers are not tracked by Google, but the first paint waits
     * for a font and the save waits for a download. That is the trade the two groups exist to
     * make visible: the local ones download nothing and are on screen instantly.
     *
     * @var array<string, string> family name => the `font-family` value it becomes
     */
    public const WEBFONTS = [
        'Nunito' => "'Nunito', sans-serif",
        'Roboto' => "'Roboto', sans-serif",
        'Open Sans' => "'Open Sans', sans-serif",
        'Lato' => "'Lato', sans-serif",
        'Montserrat' => "'Montserrat', sans-serif",
        'Raleway' => "'Raleway', sans-serif",
        'Work Sans' => "'Work Sans', sans-serif",
        'Merriweather' => "'Merriweather', serif",
        'Lora' => "'Lora', serif",
        'Playfair Display' => "'Playfair Display', serif",
        'Source Serif 4' => "'Source Serif 4', serif",
        'Bitter' => "'Bitter', serif",
        'Fira Sans' => "'Fira Sans', sans-serif",
        'Fira Code' => "'Fira Code', monospace",
        'JetBrains Mono' => "'JetBrains Mono', monospace",
        'Space Mono' => "'Space Mono', monospace",
    ];

    /** The light-scheme colours a preset is recognised by in the list -- its swatch strip. */
    public const SWATCHES = [
        'yw-primary',
        'yw-secondary',
        'yw-tertiary',
        'yw-surface',
        'yw-surface-sunken',
        'yw-ink-on-light',
    ];

    /** Where core's own default token values are read from: the file that declares them. */
    private const CORE_TOKENS_FILE = 'styles/yw-core.css';

    /** @var array{light: array<string, string>, dark: array<string, string>}|null */
    private ?array $defaults = null;

    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly ConfigurationService $configurationService,
        private readonly ParameterBagInterface $params,
        private readonly AssetRegistry $assets,
    ) {
    }

    /**
     * Every preset this wiki can use, shipped ones first.
     *
     * @return list<array{id: string, name: string, custom: bool, default: bool, complete: bool, missing: list<string>, path: string, href: string, values: array{light: array<string, string>, dark: array<string, string>}}>
     */
    public function all(): array
    {
        $default = $this->default();
        $presets = [];

        foreach ($this->shippedFiles() as $file => $path) {
            $presets[] = $this->describe($file, $path, false, $default);
        }
        foreach ($this->instanceFiles() as $file => $path) {
            $presets[] = $this->describe(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $file, $path, true, $default);
        }

        return $presets;
    }

    /**
     * The wiki's default preset -- what every page wears -- or '' for core's own tokens.
     *
     * Distinct from what the Personnalisation screen is *previewing*, which is a matter for
     * that one page and never leaves the browser.
     */
    public function default(): string
    {
        $configured = $this->params->has('favorite_preset') ? $this->params->get('favorite_preset') : '';

        return is_string($configured) ? $configured : '';
    }

    /** @return array{id: string, name: string, custom: bool, default: bool, complete: bool, missing: list<string>, path: string, href: string, values: array{light: array<string, string>, dark: array<string, string>}}|null */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * The name a preset is listed under -- the stem of its file, which is what a card shows.
     *
     * Derived rather than looked up, so it can name a preset that has just been deleted, and
     * so a message about one costs no directory scan. Same rule as describe().
     */
    public function nameOf(string $id): string
    {
        return pathinfo($id, PATHINFO_FILENAME);
    }

    /**
     * The values a rail opens on: a named preset's, or core's own for a brand new one.
     *
     * Gaps are filled from core's defaults *for the editor only* -- what is on screen has to
     * be a complete preset, since saving writes every field. The file itself is never
     * gap-filled; that is what makes an incomplete one visible instead of invisible.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function valuesFor(string $id): array
    {
        $preset = $this->find($id);
        $values = $preset === null ? ['light' => [], 'dark' => []] : $preset['values'];

        return $this->withDefaults($values);
    }

    /**
     * The tokens a set of values leaves out, as `--yw-name (scheme)` strings.
     *
     * A colour has to be there in both schemes; everything else once, in the light block,
     * which is where the scheme-independent tokens live.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return list<string>
     */
    public function missingIn(array $values): array
    {
        $missing = [];
        foreach (self::TOKENS as $token => $definition) {
            if (trim($values['light'][$token] ?? '') === '') {
                $missing[] = '--' . $token;
            }
            if ($definition['kind'] === self::KIND_COLOR && trim($values['dark'][$token] ?? '') === '') {
                $missing[] = '--' . $token . ' (dark)';
            }
        }

        return $missing;
    }

    /**
     * Every webfont the rail offers: the curated list, plus whatever is already downloaded.
     *
     * A family under `custom/fonts/` has been fetched and is being served, so it must be
     * offerable however it got there -- a preset that used it, an earlier release's list, a
     * webmaster who put the files there by hand. Dropping one from the select would leave a
     * preset naming a font the screen could not re-select.
     *
     * @return array<string, string> family name => the `font-family` value it becomes
     */
    public function webfonts(): array
    {
        $fonts = self::WEBFONTS;

        foreach (glob(ThemeManager::CUSTOM_FONT_PATH . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            // the directory is the family, lowercased, as installAndGetCSSForFont wrote it
            $family = ucwords(str_replace(['-', '_'], ' ', basename($directory)));
            if (!isset($fonts[$family])) {
                $fonts[$family] = "'" . $family . "', sans-serif";
            }
        }

        ksort($fonts);

        return $fonts;
    }

    /** The Google catalogue, vendored: 1951 family names, one array, no metadata. */
    private const GOOGLE_FONTS_FILE = 'src/assets/google-fonts.json';

    /** @var list<string>|null */
    private ?array $catalogue = null;

    /**
     * Every family Google offers, by name.
     *
     * Vendored rather than asked for at runtime: it is a static list, and fetching it would
     * put a network round trip in front of drawing an admin screen -- on an instance that
     * cannot reach Google, the screen would hang before showing anything, which is exactly
     * the case where a webmaster most wants to see what is already installed.
     *
     * @return list<string>
     */
    public function googleFonts(): array
    {
        if ($this->catalogue === null) {
            $path = defined('YESWIKI_SOURCE_DIR')
                ? YESWIKI_SOURCE_DIR . '/' . self::GOOGLE_FONTS_FILE
                : self::GOOGLE_FONTS_FILE;
            $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
            $this->catalogue = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }

        return $this->catalogue;
    }

    /**
     * The catalogue's own spelling of a family, or '' if it does not have one.
     *
     * Matched case-insensitively and then answered in the catalogue's casing, because that
     * casing is what the download URL and the folder name are built from: `open sans` typed
     * into the box has to become `Open Sans` before anything asks Google for it.
     */
    public function googleFontNamed(string $family): string
    {
        $family = trim($family);
        foreach ($this->googleFonts() as $known) {
            if (strcasecmp($known, $family) === 0) {
                return $known;
            }
        }

        return '';
    }

    /**
     * Fetch a webfont so the rail can offer it, and return the family it installed.
     *
     * Two sources, because there are two situations. **Google** is where a font that is not
     * here yet comes from. **Another YesWiki** is for the one you already fetched onto your
     * other instance: its files are served from `custom/fonts/<family>/`, so pointing at that
     * wiki copies them across without asking Google a second time -- which is also the only
     * way to install one on a server that cannot reach Google at all.
     *
     * Either way the files land in `custom/fonts/` and are served from here afterwards. A
     * reader is never sent to Google.
     */
    public function installFont(string $family): string
    {
        $family = trim($family);
        if (self::isSystemStack($family)) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_FONT_IS_LOCAL'));
        }

        // Checked against the catalogue, not against a shape: `Opne Sans` is a perfectly
        // well-formed family name and Google answers a request for it with nothing at all,
        // so a regex would let a typo through to a network round trip and a blank failure.
        // The catalogue's own spelling is what comes back -- it is what the URL and the
        // folder name are built from.
        $family = $this->googleFontNamed($family);
        if ($family === '') {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_FONT_BAD_NAME'));
        }

        if (!$this->themeManager->installFont($family)) {
            throw new \RuntimeException(_t('ADMIN_PRESET_FONT_NOT_FOUND', ['family' => $family]));
        }

        return $family;
    }

    /**
     * Install several families at once, and say which ones landed.
     *
     * A comma-separated list, because picking a typeface is picking a set: a body face and a
     * heading face are chosen together, and downloading them one screen-round-trip at a time
     * is three page loads to answer one question.
     *
     * A family that fails does not stop the others -- the wiki ends up with what it could
     * get, and is told what it could not, which beats an all-or-nothing that leaves nothing.
     *
     * @return array{installed: list<string>, failed: list<string>}
     */
    public function installFonts(string $families): array
    {
        $installed = [];
        $failed = [];

        foreach (array_filter(array_map('trim', explode(',', $families))) as $family) {
            try {
                $installed[] = $this->installFont($family);
            } catch (\Throwable) {
                $failed[] = $family;
            }
        }

        if ($installed === [] && $failed === []) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_FONT_BAD_NAME'));
        }

        return ['installed' => $installed, 'failed' => $failed];
    }

    /**
     * Copy the webfonts another YesWiki uses, by asking it what they are.
     *
     * The remote wiki answers `/api/presets/fonts` with a descriptor per file -- family,
     * style, weight, `unicode-range`, absolute URL -- read out of its own presets. So this
     * gets the *whole* family: every weight and both slopes, which is the thing a font
     * mattering at all depends on and the thing a guess at file names could never produce.
     *
     * `$family` narrows it to one family; empty takes everything that wiki uses.
     *
     * @return list<string> the families installed
     */
    public function installFontsFromWiki(string $wikiUrl, string $family = '', string $preset = ''): array
    {
        $base = $this->fontSource($wikiUrl);
        $url = $base . '/?api/presets/fonts';
        if ($preset !== '') {
            $url .= '&preset=' . rawurlencode($preset);
        }

        $answer = json_decode((string)$this->themeManager->fetchUrl($url), true);
        $fonts = is_array($answer) ? ($answer['fonts'] ?? null) : null;
        if (!is_array($fonts) || $fonts === []) {
            throw new \RuntimeException(_t('ADMIN_PRESET_FONT_NONE_THERE', ['wiki' => $wikiUrl]));
        }

        $installed = [];
        foreach ($fonts as $font) {
            if (!is_array($font) || empty($font['family']) || empty($font['url'])) {
                continue;
            }
            if ($family !== '' && strcasecmp((string)$font['family'], $family) !== 0) {
                continue;
            }
            $rule = $this->themeManager->importRemoteFontFile($font);
            if ($rule !== null) {
                $installed[(string)$font['family']][] = $rule;
            }
        }

        // the rules go beside the files, like Google's do (ThemeManager::FONT_FACES_FILE):
        // a family is only usable once something declares it, and a copy that brought the
        // files without the declarations would be a font nothing could name
        foreach ($installed as $family => $rules) {
            $this->themeManager->writeFontFaces((string)$family, implode("\n", $rules));
        }

        if ($installed === []) {
            throw new \RuntimeException(_t('ADMIN_PRESET_FONT_NONE_THERE', ['wiki' => $wikiUrl]));
        }

        return array_keys($installed);
    }

    /**
     * The base a font is fetched from: '' for Google, or another wiki's address.
     *
     * The wiki's address is checked rather than used as given. It is a URL this server is
     * about to request, so anything but plain http(s) to a named host is refused -- `file://`
     * would read the disk, and a bare IP is the shape of somebody probing the network the
     * server sits in rather than naming a wiki.
     */
    private function fontSource(string $from): string
    {
        $from = trim($from);
        if ($from === '') {
            return '';
        }

        $parts = parse_url($from);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_FONT_BAD_SOURCE'));
        }

        return rtrim($from, '/');
    }

    /**
     * The webfonts a preset needs, as everything another wiki would need to install them.
     *
     * **The preset file is the manifest.** Its `@font-face` blocks already carry family,
     * style, weight, the subset's `unicode-range` and a `src` per file -- written there when
     * the font was fetched. So there is nothing to store, nothing to keep in step, and
     * nothing that can disagree with what the preset actually renders with: describing the
     * fonts *is* reading the file.
     *
     * URLs come back absolute against `$baseUrl`, because a preset stores them relative to
     * itself (`../../custom/fonts/…`) and the wiki asking is not this one.
     *
     * @return list<array{family: string, style: string, weight: string, subset: string, unicodeRange: string, url: string}>
     */
    public function fontsOf(string $id, string $baseUrl = ''): array
    {
        $preset = $this->find($id);
        if ($preset === null) {
            return [];
        }

        $css = (string)file_get_contents($preset['path']);
        $fonts = [];
        $subset = '';

        if (!preg_match_all('~/\*\s*([a-z0-9-]+)\s*\*/|@font-face\s*\{[^}]*\}~i', $css, $matches)) {
            return [];
        }

        foreach ($matches[0] as $index => $block) {
            if (!str_contains($block, '@font-face')) {
                $subset = $matches[1][$index];
                continue;
            }
            // only woff2 is offered: it is the only format core fetches now, and the only
            // one worth another wiki's bandwidth
            if (!preg_match('~url\(\s*[\'"]?([^\)\'"]*\.woff2)~i', $block, $url)) {
                continue;
            }
            preg_match("/font-family:\s*'?([^;']+)'?;/i", $block, $family);
            preg_match('/font-style:\s*([a-z]+)/i', $block, $style);
            preg_match('/font-weight:\s*([0-9]+)/i', $block, $weight);
            preg_match('/unicode-range:\s*([^;}]+)/i', $block, $range);

            $fonts[] = [
                'family' => trim($family[1] ?? ''),
                'style' => $style[1] ?? 'normal',
                'weight' => $weight[1] ?? '400',
                'subset' => $subset,
                'unicodeRange' => trim($range[1] ?? ''),
                'url' => $this->absoluteFontUrl(trim($url[1]), $baseUrl),
            ];
        }

        return $fonts;
    }

    /**
     * `@font-face` rules for every family under `custom/fonts/`, whatever names it.
     *
     * **This is what makes a webfont previewable.** A preset's rules are written into the
     * preset file when it is saved, so until then the browser has never heard of the family:
     * choosing it in the rail set `font-family: 'Nunito', sans-serif` on the document and
     * every word carried on rendering in Arial. Nothing was broken, and nothing looked like
     * it had happened -- which is the same symptom as the choice not registering at all.
     *
     * So the admin screen links this instead. It declares what is *installed*, which is a
     * superset of what any one preset names, and is therefore also right the moment a font
     * is downloaded and before anything has been saved.
     *
     * Families installed before their rules were kept (ThemeManager::FONT_FACES_FILE) are
     * described from their file names, which carry style, weight and subset by construction.
     * `unicode-range` is the one thing not recoverable that way; leaving it out costs a
     * subset file or two on this screen and nothing else, since the preset a reader gets is
     * still built from the real rules.
     */
    public function installedFontFaces(string $baseUrl = ''): string
    {
        $css = [];

        foreach (glob(ThemeManager::CUSTOM_FONT_PATH . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $stored = $directory . '/' . ThemeManager::FONT_FACES_FILE;
            $rules = is_file($stored)
                ? (string)file_get_contents($stored)
                : $this->facesFromFileNames($directory);
            if (trim($rules) === '') {
                continue;
            }

            // stored relative to a preset (`../../custom/fonts/…`), served from a route --
            // so nothing a browser resolves this against would find the file
            $css[] = (string)preg_replace_callback(
                '~url\(\s*[\'"]?([^)\'"]+)[\'"]?\s*\)~',
                fn (array $match): string => 'url(' . $this->absoluteFontUrl(trim($match[1]), $baseUrl) . ')',
                $rules
            );
        }

        return implode("\n", $css);
    }

    /**
     * Describe a family from the names of its files, for one installed before faces.css.
     *
     * `<family>-<style>-<weight>-<subset>.woff2` is what importFontFile writes, and the
     * directory is the family -- so the prefix is known and what follows it parses.
     */
    private function facesFromFileNames(string $directory): string
    {
        $folder = basename($directory);
        $family = ucwords(str_replace(['-', '_'], ' ', $folder));
        $rules = [];

        foreach (glob($directory . '/*.woff2') ?: [] as $file) {
            $name = basename($file, '.woff2');
            if (!preg_match('~^' . preg_quote($folder, '~') . '-(normal|italic)-([0-9]+)~', $name, $parts)) {
                continue;
            }
            $rules[] = ThemeManager::fontFaceRule(
                $family,
                $parts[1],
                $parts[2],
                '',
                '../../' . ThemeManager::CUSTOM_FONT_PATH . '/' . $folder . '/' . basename($file)
            );
        }

        return implode("\n", $rules);
    }

    /**
     * A preset's `../../custom/fonts/…` made absolute, so another wiki can fetch it.
     *
     * Left alone if it is already absolute: a preset whose fonts were never localised still
     * points at Google, and copying that URL across is the honest answer -- the wiki asking
     * will localise it itself.
     */
    private function absoluteFontUrl(string $url, string $baseUrl): string
    {
        if ($baseUrl === '' || preg_match('~^(https?:)?//~i', $url)) {
            return $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim(str_replace('../', '', $url), '/');
    }

    public function isConfigWritable(): bool
    {
        return is_writable(ConfigurationFileProvider::getConfigFileFromEnv());
    }

    public function arePresetsWritable(): bool
    {
        $path = ThemeManager::CUSTOM_CSS_PRESETS_PATH;

        return is_dir($path) ? is_writable($path) : is_writable(dirname($path));
    }

    /**
     * Make a preset the wiki's, or none of them.
     *
     * An id nobody offers is refused rather than written: `favorite_preset` names a file
     * that CoreAssets will link, so an unchecked value is a path this screen chose to
     * put in the head of every page.
     */
    public function select(string $id): void
    {
        if ($id !== '' && $this->find($id) === null) {
            throw new \InvalidArgumentException('unknown preset: ' . $id);
        }

        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        // through ArrayAccess rather than `$config->favorite_preset`: the property form goes
        // through __get/__set, which is the same store but is invisible to static analysis
        if ($id === '') {
            unset($config['favorite_preset']);
        } else {
            $config['favorite_preset'] = $id;
        }
        $config->write();
    }

    /**
     * Copy a preset into the instance so it can be edited, and return the copy's id.
     *
     * This is the *only* way a theme preset is changed. The file is copied verbatim rather
     * than rebuilt from its tokens: a preset can carry `@font-face` blocks (save() appends
     * them) and declarations this screen does not offer, and a copy that dropped them would
     * not be a copy.
     */
    public function duplicate(string $id): string
    {
        $source = $this->find($id);
        if ($source === null) {
            throw new \InvalidArgumentException('unknown preset: ' . $id);
        }

        $file = $this->freeFileName($source['name']);
        $this->ensureDirectory();
        if (!copy($source['path'], ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . $file)) {
            throw new \RuntimeException('could not copy ' . $id);
        }

        return ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $file;
    }

    /**
     * Rewrite an instance preset, and return its id -- which changes if it was renamed.
     *
     * Editing replaces; it never leaves a second copy behind. Renaming is therefore a rename
     * of the file, not a save-as: someone correcting a typo in a preset's name means the name
     * to be different, not for the wiki to end up wearing one of two near-identical presets.
     * The old file goes only once the new one is on disk.
     *
     * A shipped preset cannot be the target: themes/ is code. Pass '' as $id to create one.
     *
     * **A gap is refused, not filled.** Every token is written or nothing is: a preset that
     * declared forty of forty-nine would leave the other nine to whatever core happens to
     * say -- which is not what the person editing it was looking at.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     */
    public function save(string $id, string $name, array $values): string
    {
        $existing = $id === '' ? null : $this->find($id);
        if ($id !== '' && ($existing === null || !$existing['custom'])) {
            throw new \InvalidArgumentException('not an instance preset: ' . $id);
        }

        $missing = $this->missingIn($values);
        if ($missing !== []) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_INCOMPLETE', ['tokens' => implode(', ', $missing)]));
        }

        $cycle = $this->cycleIn($values);
        if ($cycle !== null) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_CYCLE', ['tokens' => implode(' -> ', $cycle)]));
        }

        $file = $this->fileNameFor($name);
        $saved = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $file;

        // ThemeManager writes the file and, for the font families, downloads and installs the
        // webfont locally -- which is the reason not to write the file here
        // all THREE font tokens: mono was left out, so a preset whose code blocks named a
        // webfont was written with no `@font-face` for it and rendered in the fallback --
        // the same silent nothing as an uninstalled font, on the one token nobody re-reads
        $result = $this->themeManager->writeCustomCSSPreset($file, $this->toCss($values), [
            $values['light']['yw-font-body'] ?? '',
            $values['light']['yw-font-heading'] ?? '',
            $values['light']['yw-font-mono'] ?? '',
        ]);
        if (!$result['status']) {
            throw new \RuntimeException($result['message']);
        }

        if ($existing !== null && $existing['id'] !== $saved) {
            // renamed: the wiki follows it if it was wearing it, and only then is the old
            // file removed -- in that order, so a failure never leaves the head pointing at
            // a file that has already gone
            if ($this->default() === $existing['id']) {
                $this->select($saved);
            }
            @unlink($existing['path']);
        }

        return $saved;
    }

    /**
     * The first loop of `var()` references a set of values contains, or null if there is none.
     *
     * **This has to be checked, because CSS will not tell anybody.** A custom property whose
     * value refers back to itself, however long the chain, is invalid at computed-value time:
     * the browser does not warn, does not fall back to the previous value, and does not leave
     * the property unset in a way a rule can notice -- every colour in the loop simply
     * computes to black. Measured, not assumed. A webmaster who pointed the brand at the
     * heading that was already pointing at the brand would get a black wiki and no clue.
     *
     * Returned as the path, so the message can name the loop rather than say one exists.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return list<string>|null
     */
    public function cycleIn(array $values): ?array
    {
        foreach (self::SCHEMES as $scheme) {
            // a colour is authored per scheme; everything else once, in the light block --
            // so a reference resolves against the scheme it was written in
            $valueOf = function (string $token) use ($values, $scheme): string {
                $isColour = (self::TOKENS[$token]['kind'] ?? '') === self::KIND_COLOR;

                return (string)($isColour ? ($values[$scheme][$token] ?? '') : ($values['light'][$token] ?? ''));
            };

            $state = [];
            foreach (array_keys(self::TOKENS) as $token) {
                $path = $this->walk($token, $valueOf, $state, []);
                if ($path !== null) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Depth-first from one token, returning the loop it is part of.
     *
     * `$state` is the usual three-colour marking: absent = not visited, `false` = on the
     * current path, `true` = finished and known clean. Without the third state a wide graph
     * is walked again for every token that reaches it.
     *
     * @param callable(string): string $valueOf
     * @param array<string, bool>      $state
     * @param list<string>             $path
     *
     * @return list<string>|null
     */
    private function walk(string $token, callable $valueOf, array &$state, array $path): ?array
    {
        if (($state[$token] ?? null) === true) {
            return null;
        }
        if (array_key_exists($token, $state)) {
            // marked but not finished, so it is on the path we are standing on: the loop runs
            // from where it first appears back round to here
            $from = array_search($token, $path, true);
            $loop = $from === false ? [] : array_slice($path, (int)$from);
            $loop[] = $token;

            return $loop;
        }

        $state[$token] = false;
        foreach ($this->referencesIn($valueOf($token)) as $referenced) {
            if (!array_key_exists($referenced, self::TOKENS)) {
                continue;
            }
            $found = $this->walk($referenced, $valueOf, $state, array_merge($path, [$token]));
            if ($found !== null) {
                return $found;
            }
        }
        $state[$token] = true;

        return null;
    }

    /**
     * The tokens a value refers to: every `var(--yw-…)` in it, fallbacks included.
     *
     * Any `var()` counts, not only a value that is nothing but one: a hand-written
     * `color-mix(in oklab, var(--yw-primary) 50%, white)` is as much a reference as a bare
     * one, and loops through it are just as invisible.
     *
     * @return list<string>
     */
    public function referencesIn(string $value): array
    {
        preg_match_all('/var\(\s*--([a-z0-9-]+)/i', $value, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Delete an instance preset. A shipped one is refused -- it belongs to the code.
     *
     * Deselects it first if it was the one in use, so the wiki never links a file that is
     * about to stop existing.
     */
    public function delete(string $id): void
    {
        $preset = $this->find($id);
        if ($preset === null || !$preset['custom']) {
            throw new \InvalidArgumentException('not an instance preset: ' . $id);
        }

        if ($this->default() === $id) {
            $this->select('');
        }

        $file = substr($id, strlen(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX));
        $result = $this->themeManager->deleteCustomCSSPreset($file);
        if (empty($result['status'])) {
            throw new \RuntimeException((string)($result['message'] ?? 'preset not deleted'));
        }
    }

    /**
     * The token values a preset stylesheet declares, per Colour scheme.
     *
     * The dark set is read from either of the two blocks that carry it -- the
     * `prefers-color-scheme` one and the `[data-theme='dark']` one -- because a hand-written
     * preset may lead with either, and a file that has only one of them is a preset whose
     * toggle works in one direction, not a file to be read as empty.
     *
     * Deliberately forgiving about what it finds: a value this screen cannot represent is
     * still a value, and a preset is being *listed* here, so one odd declaration must not
     * make the file look blank.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function valuesOf(string $css): array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        $values = ['light' => [], 'dark' => []];

        foreach ($this->blocksIn($css) as [$selector, $body, $inDarkMedia]) {
            if (!str_contains($selector, ':root') && !str_contains($selector, 'html')) {
                continue;
            }
            $scheme = ($inDarkMedia || str_contains($selector, "data-theme='dark'") || str_contains($selector, 'data-theme="dark"'))
                ? 'dark'
                : 'light';
            foreach ($this->declarationsIn($body) as $token => $value) {
                if (array_key_exists($token, self::TOKENS)) {
                    $values[$scheme][$token] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * A hex literal `<input type="color">` will accept.
     *
     * Every shipped preset uses six-digit hex, but a hand-edited file can hold anything a
     * colour can be, and a colour input silently shows black for a value it cannot parse.
     * So the picker gets something valid and the real value is kept beside it.
     */
    public function asHex(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            return $value;
        }
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $short)) {
            return '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
        }

        return '#000000';
    }

    /**
     * Is this font family one of the stacks above -- fonts the reader already has?
     *
     * Asked by ThemeManager on save, because saving a preset otherwise means asking Google
     * for a webfont named after the first family in the value. For a stack that is `Seravek`
     * or `Didot`, that request is answered by nothing and costs a curl timeout per user-agent
     * string, on a screen where the person is waiting for a page. It is also a request to
     * Google made by the instance, in the one case where the whole point of the choice was
     * that nothing has to be fetched from anywhere.
     *
     * Static, so ThemeManager can ask without being handed this service: PresetService is
     * built on top of ThemeManager, and injecting it back would close the circle.
     */
    public static function isSystemStack(string $family): bool
    {
        $normalised = (string)preg_replace('/\s+/', ' ', trim($family));

        return in_array($normalised, self::FONT_STACKS, true);
    }

    /**
     * The ink each fill gets: whichever of the preset's two inks is more legible on it.
     *
     * Per scheme, because the fills flip: a primary that is a deep teal in the light scheme
     * and a pale cyan in the dark one wants the light ink in one and the dark ink in the
     * other, from the same authored pair.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function resolve(array $values): array
    {
        foreach (self::SCHEMES as $scheme) {
            // the inks are scheme-independent, so both come from the light block
            $onLight = $values['light']['yw-ink-on-light'] ?? '';
            $onDark = $values['light']['yw-ink-on-dark'] ?? '';

            foreach (self::INK_FOR as $fill => $property) {
                $colour = $values[$scheme][$fill] ?? '';
                $values[$scheme][$property] = $this->inkOn($colour, $onLight, $onDark);
            }
        }

        return $values;
    }

    /**
     * The ink for a background a PAGE AUTHOR chose, as a CSS value, or '' if it cannot tell.
     *
     * A `{{section bgcolor="…"}}` is the one ground core does not pick: somebody typed it.
     * Two shapes are answerable, and they cover what people actually write --
     *
     *   `#f9c401`            a literal, which can be measured against the wiki's two inks
     *   `var(--yw-primary)`  a fill, whose ink core has already resolved (`--yw-on-primary`)
     *
     * -- and anything else (a `color-mix()`, a keyword, a gradient) answers '', because
     * guessing wrong here means unreadable text on somebody's cover image. Those keep the
     * `white`/`black` classes, which is an author saying it outright.
     */
    public function inkForBackground(string $background, string $scheme = 'light'): string
    {
        $background = trim($background);
        if ($background === '') {
            return '';
        }

        // a fill core already resolved an ink for: use that answer rather than a second one
        if (preg_match('~^var\(\s*--([a-z0-9-]+)\s*\)$~i', $background, $match)) {
            $ink = self::INK_FOR[$match[1]] ?? '';

            return $ink === '' ? '' : 'var(--' . $ink . ')';
        }

        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $background)) {
            return '';
        }

        $values = $this->valuesFor($this->default());
        $onLight = $values['light']['yw-ink-on-light'] ?? '';
        $onDark = $values['light']['yw-ink-on-dark'] ?? '';

        // the token, not the literal: a preset changing its inks must move the section too
        return $this->inkOn($background, $onLight, $onDark) === $onLight
            ? 'var(--yw-ink-on-light)'
            : 'var(--yw-ink-on-dark)';
    }

    /**
     * Which of two inks reads better on a colour.
     *
     * Ties and unreadable values go to the dark-ground ink: a fill this cannot measure is one
     * somebody wrote as a `var()` or a `color-mix()`, and those are overwhelmingly the strong
     * brand colours rather than the near-whites.
     */
    public function inkOn(string $fill, string $onLight, string $onDark): string
    {
        $against = $this->contrastRatio($fill, $onLight);
        $with = $this->contrastRatio($fill, $onDark);
        if ($against === null || $with === null) {
            return $onDark;
        }

        return $against > $with ? $onLight : $onDark;
    }

    /**
     * The WCAG 2.1 contrast ratio between two colours, or null if either is not a plain hex.
     *
     * Null rather than a guess: a value written as `var(--yw-primary)` or a `color-mix()` is
     * one only a browser can resolve, and a number invented for it here would be a score
     * nobody could act on.
     */
    public function contrastRatio(string $first, string $second): ?float
    {
        $one = $this->luminance($first);
        $two = $this->luminance($second);
        if ($one === null || $two === null) {
            return null;
        }

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    /**
     * WCAG relative luminance of a hex colour, or null if the value is not one.
     *
     * The shape is checked on the RAW value, before asHex() normalises it: asHex answers
     * `#000000` for everything it cannot read, so normalising first would score a
     * `var(--yw-primary)` as pure black and hand it an ink chosen for a colour it is not.
     */
    private function luminance(string $value): ?float
    {
        $value = trim($value);
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
            return null;
        }
        $hex = ltrim($this->asHex($value), '#');
        $channel = static function (float $value): float {
            $value /= 255;

            return $value <= 0.03928 ? $value / 12.92 : ((($value + 0.055) / 1.055) ** 2.4);
        };

        return 0.2126 * $channel((float)hexdec(substr($hex, 0, 2)))
            + 0.7152 * $channel((float)hexdec(substr($hex, 2, 2)))
            + 0.0722 * $channel((float)hexdec(substr($hex, 4, 2)));
    }

    /**
     * The CSS a set of token values is written as: light, then the dark set twice.
     *
     * The two dark blocks carry the same declarations rather than one referring to the
     * other, because the toggle has to be able to win in both directions -- `forced light on
     * a dark desktop` is the media query being switched off by an attribute, and it cannot
     * be expressed as a variation of a block that has already applied.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     */
    public function toCss(array $values): string
    {
        // the ink each fill gets is worked out here and written into the file, because CSS
        // cannot choose between two authored colours on the luminance of a third
        $values = $this->resolve($values);

        $light = '';
        foreach (self::TOKENS as $token => $_definition) {
            $light .= '  --' . $token . ': ' . trim($values['light'][$token] ?? '') . ";\n";
        }
        foreach (self::INK_FOR as $_fill => $property) {
            $light .= '  --' . $property . ': ' . trim($values['light'][$property] ?? '') . ";\n";
        }

        $dark = '';
        foreach (self::TOKENS as $token => $definition) {
            if ($definition['kind'] !== self::KIND_COLOR) {
                continue;
            }
            $dark .= '  --' . $token . ': ' . trim($values['dark'][$token] ?? '') . ";\n";
        }
        foreach (self::INK_FOR as $_fill => $property) {
            $dark .= '  --' . $property . ': ' . trim($values['dark'][$property] ?? '') . ";\n";
        }
        $darkIndented = (string)preg_replace('/^  /m', '    ', $dark);

        return <<<CSS
            /* A complete Preset: every AUTHORED Design token, colour tokens once per Colour
               scheme and the rest once (ADR-0020, simplified by ADR-0021). Written by the
               Personnalisation screen -- editing it by hand is fine, but leave nothing out:
               core refuses an incomplete Preset by name rather than filling the gap from
               somebody else's values.

               What is NOT here, core derives from what is: every hover colour, the muted
               text, the two border shades, the focus ring, the panel and ink behind each
               status colour, the shadow colours and the three corner radii. Declaring one
               here would work -- it would simply win over core's -- but it would then be a
               value nobody keeps in step, and the usual result is a dark scheme quietly
               matched to a light surface. The measures are multiples of the base type size:
               `rem` is that size, and the three scales are bare multipliers. */
            :root {
            $light}

            @media (prefers-color-scheme: dark) {
              :root:not([data-theme='light']) {
            $darkIndented  }
            }

            :root[data-theme='dark'] {
            $dark}

            CSS;
    }

    /**
     * The file a typed name would be written to.
     *
     * basename() alone is not enough: the result is concatenated into a path, so anything
     * that is not a plain name is dropped rather than escaped. Public because that is a
     * property worth being able to assert on its own, without writing a file to find out.
     */
    public function fileNameFor(string $name): string
    {
        // accents folded rather than dropped: on a French wiki, stripping them turns "Été"
        // into "t", and the file a preset lives in is named after what somebody typed
        $name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', removeAccents(trim($name)));
        $name = trim((string)$name, '-');
        if ($name === '') {
            throw new \InvalidArgumentException('a preset needs a name');
        }

        return strtolower($name) . '.css';
    }

    /**
     * Core's own token values -- the default Preset -- read from the file that declares them.
     *
     * Parsed rather than restated in PHP: two copies of forty-nine values drift, and the one
     * in the stylesheet is the one every page actually wears.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function coreDefaults(): array
    {
        if ($this->defaults === null) {
            $path = defined('YESWIKI_SOURCE_DIR')
                ? YESWIKI_SOURCE_DIR . '/' . self::CORE_TOKENS_FILE
                : self::CORE_TOKENS_FILE;
            $css = is_file($path) ? (string)file_get_contents($path) : '';
            $this->defaults = $this->valuesOf($css);
        }

        return $this->defaults;
    }

    /**
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function withDefaults(array $values): array
    {
        $defaults = $this->coreDefaults();

        return [
            'light' => array_filter($values['light']) + $defaults['light'],
            'dark' => array_filter($values['dark']) + $defaults['dark'],
        ];
    }

    /** @return array{id: string, name: string, custom: bool, default: bool, complete: bool, missing: list<string>, path: string, href: string, values: array{light: array<string, string>, dark: array<string, string>}} */
    private function describe(string $id, string $path, bool $custom, string $default): array
    {
        $values = $this->valuesOf((string)file_get_contents($path));
        $missing = $this->missingIn($values);

        return [
            'id' => $id,
            'name' => pathinfo($id, PATHINFO_FILENAME),
            'custom' => $custom,
            'default' => $id === $default,
            'complete' => $missing === [],
            'missing' => $missing,
            'path' => $path,
            // the address the screen previews from -- resolved the way every other stylesheet
            // is, so a farm instance gets its cache/assets/{version}/ copy and not a path
            // into the shared code tree
            'href' => (string)$this->assets->urlFor($path),
            // the swatch strip and the rail both read this, and both want something to show
            // for a preset that is missing values -- what they show is core's, which is what
            // the page would actually wear there
            'values' => $this->withDefaults($values),
        ];
    }

    /**
     * The rule blocks in a stylesheet, as [selector, body, is inside a dark media query].
     *
     * A hand-rolled scan rather than a regex: `@media` nests, and the dark set lives inside
     * one, so a pattern that stops at the first `}` reads the media query's own brace as the
     * end of the block it wraps.
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function blocksIn(string $css): array
    {
        $blocks = [];
        $length = strlen($css);
        $selector = '';
        $darkMediaDepth = null;
        $depth = 0;
        $i = 0;
        while ($i < $length) {
            $character = $css[$i];
            if ($character === '{') {
                $head = trim($selector);
                $selector = '';
                if (str_starts_with($head, '@')) {
                    $depth++;
                    if ($darkMediaDepth === null && str_contains(str_replace(' ', '', $head), 'prefers-color-scheme:dark')) {
                        $darkMediaDepth = $depth;
                    }
                    $i++;
                    continue;
                }
                $end = strpos($css, '}', $i);
                $end = $end === false ? $length : $end;
                $blocks[] = [$head, substr($css, $i + 1, $end - $i - 1), $darkMediaDepth !== null];
                $i = $end + 1;
                continue;
            }
            if ($character === '}') {
                if ($darkMediaDepth !== null && $depth === $darkMediaDepth) {
                    $darkMediaDepth = null;
                }
                $depth = max(0, $depth - 1);
                $selector = '';
                $i++;
                continue;
            }
            $selector .= $character;
            $i++;
        }

        return $blocks;
    }

    /**
     * @return array<string, string> token name (without the leading --) => value
     *
     * Runs of whitespace are collapsed to one space, which CSS does anyway and this has to as
     * well: the formatter wraps a long value over several lines, so a font stack came back
     * with newlines and indentation inside it. Nothing rendered wrong -- the browser reads it
     * the same -- but every exact comparison against it failed, so the rail could not
     * recognise its own `Monospace Code` stack and showed the raw text as an option of its
     * own, and isSystemStack() stopped recognising it and asked Google for a webfont called
     * `ui-monospace`.
     */
    private function declarationsIn(string $body): array
    {
        $declarations = [];
        if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $declarations[$match[1]] = (string)preg_replace('/\s+/', ' ', trim($match[2]));
            }
        }

        return $declarations;
    }

    /**
     * The theme's own presets, `custom/themes/` shadowing the source tree file by file.
     *
     * @return array<string, string> file name => path
     */
    private function shippedFiles(): array
    {
        $theme = $this->theme();
        $files = [];
        foreach ([YESWIKI_SOURCE_DIR . '/themes/' . $theme . '/presets', 'custom/themes/' . $theme . '/presets'] as $directory) {
            foreach (glob($directory . '/*.css') ?: [] as $path) {
                $files[basename($path)] = $path;
            }
        }
        ksort($files);

        return $files;
    }

    /** @return array<string, string> file name => path */
    private function instanceFiles(): array
    {
        $files = [];
        foreach (glob(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/*.css') ?: [] as $path) {
            $files[basename($path)] = $path;
        }
        ksort($files);

        return $files;
    }

    private function theme(): string
    {
        $configured = $this->params->has('favorite_theme') ? $this->params->get('favorite_theme') : '';

        return (is_string($configured) && $configured !== '') ? basename($configured) : THEME_PAR_DEFAUT;
    }

    /**
     * `<name>.css`, or `<name>-2.css`, `<name>-3.css`... if that is taken.
     *
     * Copying `red` twice has to give two presets. Overwriting the first copy would be a
     * silent loss, and refusing the second would make the button look broken.
     */
    private function freeFileName(string $name): string
    {
        $file = $this->fileNameFor($name);
        $stem = pathinfo($file, PATHINFO_FILENAME);

        $suffix = 1;
        while (file_exists(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . $file)) {
            $file = $stem . '-' . (++$suffix) . '.css';
        }

        return $file;
    }

    private function ensureDirectory(): void
    {
        $path = ThemeManager::CUSTOM_CSS_PRESETS_PATH;
        if (!is_dir($path) && !mkdir($path) && !is_dir($path)) {
            throw new \RuntimeException($path . ' does not exist and cannot be created');
        }
    }
}

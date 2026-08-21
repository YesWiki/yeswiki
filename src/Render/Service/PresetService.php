<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\StringUtilService;

/**
 * The wiki's Presets: what they are, which one is on, whether one is complete, and how to write one.
 */
class PresetService
{
    /** A colour token: declared once per Colour scheme. */
    public const KIND_COLOR = 'color';
    /** A colour that does NOT flip: one value, both schemes. */
    public const KIND_COLOR_FIXED = 'color-fixed';
    /** A measure: a slider, one value, both schemes. */
    public const KIND_SIZE = 'size';
    /** A font stack: one value, both schemes. */
    public const KIND_FONT = 'font';
    /** One of a fixed set of CSS keywords: a select, one value, both schemes. */
    public const KIND_CHOICE = 'choice';

    /**
     * Every Design token a Preset AUTHORS, in the order the editing rail offers them.
     *
     * @var array<string, array{kind: string, group: string, label: string, min?: int|float, max?: int|float, step?: int|float, unit?: string, contrast?: string, row?: string, options?: array<string, string>}>
     */
    public const TOKENS = [
        'yw-primary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_PRIMARY', 'contrast' => 'auto-ink', 'row' => 'brand-a'],
        'yw-secondary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_SECONDARY', 'contrast' => 'auto-ink', 'row' => 'brand-b'],
        'yw-tertiary' => ['kind' => self::KIND_COLOR, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_TERTIARY', 'contrast' => 'auto-ink', 'row' => 'brand-b'],

        'yw-ink-on-light' => ['kind' => self::KIND_COLOR_FIXED, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_INK_ON_LIGHT', 'contrast' => 'light.yw-surface', 'row' => 'ink'],
        'yw-ink-on-dark' => ['kind' => self::KIND_COLOR_FIXED, 'group' => 'brand', 'label' => 'ADMIN_PRESET_TOKEN_INK_ON_DARK', 'contrast' => 'dark.yw-surface', 'row' => 'ink'],

        'yw-surface' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE', 'row' => 'surf-a'],
        'yw-surface-raised' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE_RAISED', 'row' => 'surf-b'],
        'yw-surface-sunken' => ['kind' => self::KIND_COLOR, 'group' => 'surfaces', 'label' => 'ADMIN_PRESET_TOKEN_SURFACE_SUNKEN', 'row' => 'surf-b'],

        'yw-border' => ['kind' => self::KIND_COLOR, 'group' => 'lines', 'label' => 'ADMIN_PRESET_TOKEN_BORDER', 'row' => 'lines'],

        'yw-border-width' => ['kind' => self::KIND_SIZE, 'group' => 'lines', 'label' => 'ADMIN_PRESET_TOKEN_BORDER_WIDTH', 'min' => 0, 'max' => 4, 'step' => 1, 'unit' => 'px', 'row' => 'lines'],

        'yw-success' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_SUCCESS', 'contrast' => 'auto-ink', 'row' => 'status-a'],
        'yw-danger' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_DANGER', 'contrast' => 'auto-ink', 'row' => 'status-a'],
        'yw-warning' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_WARNING', 'contrast' => 'auto-ink', 'row' => 'status-b'],
        'yw-info' => ['kind' => self::KIND_COLOR, 'group' => 'status', 'label' => 'ADMIN_PRESET_TOKEN_INFO', 'contrast' => 'auto-ink', 'row' => 'status-b'],

        'yw-navbar-bg' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_BG', 'row' => 'navbar-a'],
        'yw-navbar-text' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_INK', 'contrast' => 'yw-navbar-bg', 'row' => 'navbar-a'],

        'yw-navbar-shadow' => ['kind' => self::KIND_COLOR, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_SHADOW', 'row' => 'navbar-b'],
        'yw-navbar-shadow-spread' => ['kind' => self::KIND_SIZE, 'group' => 'navbar', 'label' => 'ADMIN_PRESET_TOKEN_SHADOW_SPREAD', 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px', 'row' => 'navbar-b'],

        'yw-footer-bg' => ['kind' => self::KIND_COLOR, 'group' => 'footer', 'label' => 'ADMIN_PRESET_TOKEN_BG', 'row' => 'footer-a'],
        'yw-footer-text' => ['kind' => self::KIND_COLOR, 'group' => 'footer', 'label' => 'ADMIN_PRESET_TOKEN_INK', 'contrast' => 'yw-footer-bg', 'row' => 'footer-a'],

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

        'yw-font-size-base' => ['kind' => self::KIND_SIZE, 'group' => 'type', 'label' => 'ADMIN_PRESET_TOKEN_FONT_SIZE', 'min' => 12, 'max' => 24, 'step' => 1, 'unit' => 'px'],

        'yw-space-sm-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_SM_Y', 'min' => 0, 'max' => 1, 'step' => 0.05, 'unit' => 'rem', 'row' => 'sm'],
        'yw-space-sm-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_SM_X', 'min' => 0, 'max' => 1, 'step' => 0.05, 'unit' => 'rem', 'row' => 'sm'],
        'yw-space-md-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_MD_Y', 'min' => 0, 'max' => 2, 'step' => 0.05, 'unit' => 'rem', 'row' => 'md'],
        'yw-space-md-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_MD_X', 'min' => 0, 'max' => 2, 'step' => 0.05, 'unit' => 'rem', 'row' => 'md'],
        'yw-space-lg-y' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_LG_Y', 'min' => 0, 'max' => 6, 'step' => 0.05, 'unit' => 'rem', 'row' => 'lg'],
        'yw-space-lg-x' => ['kind' => self::KIND_SIZE, 'group' => 'spacing', 'label' => 'ADMIN_PRESET_TOKEN_SPACE_LG_X', 'min' => 0, 'max' => 6, 'step' => 0.05, 'unit' => 'rem', 'row' => 'lg'],

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

    /** `contrast => 'auto-ink'`: score this colour against whichever ink it will actually get. */
    public const CONTRAST_AUTO_INK = 'auto-ink';

    /**
     * The fills core puts text on, and the property holding the ink it picked for each.
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
     * How a heading's letters are cased.
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
     * @var array<string, string>
     */
    public const TEXT_ALIGNMENTS = [
        'start' => 'ADMIN_PRESET_ALIGN_LEFT',
        'center' => 'ADMIN_PRESET_ALIGN_CENTER',
        'end' => 'ADMIN_PRESET_ALIGN_RIGHT',
    ];

    /** The two Colour schemes a colour token is authored in. */
    public const SCHEMES = ['light', 'dark'];

    /** The type a preset can be set in: the stacks from modernfontstacks.com, verbatim. */
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

    /**
     * @var array{light: array<string, string>, dark: array<string, string>}|null
     */
    private ?array $defaults = null;

    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly ConfigurationService $configurationService,
        private readonly ParameterBagInterface $params,
        private readonly AssetRegistry $assets,
        private readonly Storage $storage,
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

    /** The wiki's default preset -- what every page wears -- or '' for core's own tokens. */
    public function default(): string
    {
        $configured = $this->params->has('favorite_preset') ? $this->params->get('favorite_preset') : '';

        return is_string($configured) ? $configured : '';
    }

    /**
     * @return array{id: string, name: string, custom: bool, default: bool, complete: bool, missing: list<string>, path: string, href: string, values: array{light: array<string, string>, dark: array<string, string>}}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /** The name a preset is listed under -- the stem of its file, which is what a card shows. */
    public function nameOf(string $id): string
    {
        return pathinfo($id, PATHINFO_FILENAME);
    }

    /**
     * The values a rail opens on: a named preset's, or core's own for a brand new one.
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
     * @return array<string, string> family name => the `font-family` value it becomes
     */
    public function webfonts(): array
    {
        $fonts = self::WEBFONTS;

        foreach ($this->storage->directories(ThemeManager::CUSTOM_FONT_PATH) as $directory) {
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

    /**
     * @var list<string>|null
     */
    private ?array $catalogue = null;

    /**
     * Every family Google offers, by name.
     *
     * @return list<string>
     */
    public function googleFonts(): array
    {
        if ($this->catalogue === null) {
            $path = defined('YESWIKI_PROGRAM_DIR')
                ? YESWIKI_PROGRAM_DIR . '/' . self::GOOGLE_FONTS_FILE
                : self::GOOGLE_FONTS_FILE;
            $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
            $this->catalogue = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }

        return $this->catalogue;
    }

    /** The catalogue's own spelling of a family, or '' if it does not have one. */
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

    /** Fetch a webfont so the rail can offer it, and return the family it installed. */
    public function installFont(string $family): string
    {
        $family = trim($family);
        if (self::isSystemStack($family)) {
            throw new \InvalidArgumentException(_t('ADMIN_PRESET_FONT_IS_LOCAL'));
        }

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

        foreach ($installed as $family => $rules) {
            $this->themeManager->writeFontFaces((string)$family, implode("\n", $rules));
        }

        if ($installed === []) {
            throw new \RuntimeException(_t('ADMIN_PRESET_FONT_NONE_THERE', ['wiki' => $wikiUrl]));
        }

        return array_keys($installed);
    }

    /** The base a font is fetched from: '' for Google, or another wiki's address. */
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
     * @return list<array{family: string, style: string, weight: string, subset: string, unicodeRange: string, url: string}>
     */
    public function fontsOf(string $id, string $baseUrl = ''): array
    {
        $preset = $this->find($id);
        if ($preset === null) {
            return [];
        }

        $css = $this->cssAt($preset['path']);
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

    /** `@font-face` rules for every family under `custom/fonts/`, whatever names it. */
    public function installedFontFaces(string $baseUrl = ''): string
    {
        $css = [];

        foreach ($this->storage->directories(ThemeManager::CUSTOM_FONT_PATH) as $directory) {
            $stored = $directory . '/' . ThemeManager::FONT_FACES_FILE;
            $rules = $this->storage->fileExists($stored)
                ? $this->storage->read($stored)
                : $this->facesFromFileNames($directory);
            if (trim($rules) === '') {
                continue;
            }

            $css[] = (string)preg_replace_callback(
                '~url\(\s*[\'"]?([^)\'"]+)[\'"]?\s*\)~',
                fn (array $match): string => 'url(' . $this->absoluteFontUrl(trim($match[1]), $baseUrl) . ')',
                $rules
            );
        }

        return implode("\n", $css);
    }

    /** Describe a family from the names of its files, for one installed before faces.css. */
    private function facesFromFileNames(string $directory): string
    {
        $folder = basename($directory);
        $family = ucwords(str_replace(['-', '_'], ' ', $folder));
        $rules = [];

        foreach ($this->storage->glob($directory . '/*.woff2') as $file) {
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
     * A preset's `../../custom/fonts/…` made absolute, so another wiki can fetch it -- and so a
     * browser can, when the files are in a bucket and the stylesheet naming them is not.
     */
    private function absoluteFontUrl(string $url, string $baseUrl): string
    {
        if (preg_match('~^(https?:)?//~i', $url)) {
            return $url;
        }
        $path = ltrim(str_replace('../', '', $url), '/');

        if (str_starts_with($path, 'custom/') && $this->storage->isRemote($path)) {
            return $this->storage->url($path);
        }

        return $baseUrl === '' ? $url : rtrim($baseUrl, '/') . '/' . $path;
    }

    public function isConfigWritable(): bool
    {
        return is_writable(ConfigurationFileProvider::getConfigFileFromEnv());
    }

    public function arePresetsWritable(): bool
    {
        return $this->storage->isWritable(ThemeManager::CUSTOM_CSS_PRESETS_PATH);
    }

    /** Make a preset the wiki's, or none of them. */
    public function select(string $id): void
    {
        if ($id !== '' && $this->find($id) === null) {
            throw new \InvalidArgumentException('unknown preset: ' . $id);
        }

        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        if ($id === '') {
            unset($config['favorite_preset']);
        } else {
            $config['favorite_preset'] = $id;
        }
        $config->write();
    }

    /** Copy a preset into the instance so it can be edited, and return the copy's id. */
    public function duplicate(string $id): string
    {
        $source = $this->find($id);
        if ($source === null) {
            throw new \InvalidArgumentException('unknown preset: ' . $id);
        }

        $file = $this->freeFileName($source['name']);

        try {
            $this->storage->write(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . $file, $this->cssAt($source['path']));
        } catch (StorageException $exception) {
            throw new \RuntimeException('could not copy ' . $id, 0, $exception);
        }

        return ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $file;
    }

    /**
     * Rewrite an instance preset, and return its id -- which changes if it was renamed.
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

        $result = $this->themeManager->writeCustomCSSPreset($file, $this->toCss($values), [
            $values['light']['yw-font-body'] ?? '',
            $values['light']['yw-font-heading'] ?? '',
            $values['light']['yw-font-mono'] ?? '',
        ]);
        if (!$result['status']) {
            throw new \RuntimeException($result['message']);
        }

        if ($existing !== null && $existing['id'] !== $saved) {
            if ($this->default() === $existing['id']) {
                $this->select($saved);
            }
            $this->storage->delete($existing['path']);
        }

        return $saved;
    }

    /**
     * The first loop of `var()` references a set of values contains, or null if there is none.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return list<string>|null
     */
    public function cycleIn(array $values): ?array
    {
        foreach (self::SCHEMES as $scheme) {
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
     * @return list<string>
     */
    public function referencesIn(string $value): array
    {
        preg_match_all('/var\(\s*--([a-z0-9-]+)/i', $value, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** Delete an instance preset. */
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
            throw new \RuntimeException($result['message']);
        }
    }

    /**
     * The token values a preset stylesheet declares, per Colour scheme.
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

    /** A hex literal `<input type="color">` will accept. */
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

    /** Is this font family one of the stacks above -- fonts the reader already has? */
    public static function isSystemStack(string $family): bool
    {
        $normalised = (string)preg_replace('/\s+/', ' ', trim($family));

        return in_array($normalised, self::FONT_STACKS, true);
    }

    /**
     * The ink each fill gets: whichever of the preset's two inks is more legible on it.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function resolve(array $values): array
    {
        foreach (self::SCHEMES as $scheme) {
            $onLight = $values['light']['yw-ink-on-light'] ?? '';
            $onDark = $values['light']['yw-ink-on-dark'] ?? '';

            foreach (self::INK_FOR as $fill => $property) {
                $colour = $values[$scheme][$fill] ?? '';
                $values[$scheme][$property] = $this->inkOn($colour, $onLight, $onDark);
            }
        }

        return $values;
    }

    /** The ink for a background a PAGE AUTHOR chose, as a CSS value, or '' if it cannot tell. */
    public function inkForBackground(string $background, string $scheme = 'light'): string
    {
        $background = trim($background);
        if ($background === '') {
            return '';
        }

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

        return $this->inkOn($background, $onLight, $onDark) === $onLight
            ? 'var(--yw-ink-on-light)'
            : 'var(--yw-ink-on-dark)';
    }

    /** Which of two inks reads better on a colour. */
    public function inkOn(string $fill, string $onLight, string $onDark): string
    {
        $against = $this->contrastRatio($fill, $onLight);
        $with = $this->contrastRatio($fill, $onDark);
        if ($against === null || $with === null) {
            return $onDark;
        }

        return $against > $with ? $onLight : $onDark;
    }

    /** The WCAG 2.1 contrast ratio between two colours, or null if either is not a plain hex. */
    public function contrastRatio(string $first, string $second): ?float
    {
        $one = $this->luminance($first);
        $two = $this->luminance($second);
        if ($one === null || $two === null) {
            return null;
        }

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    /** WCAG relative luminance of a hex colour, or null if the value is not one. */
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
     * @param array{light: array<string, string>, dark: array<string, string>} $values
     */
    public function toCss(array $values): string
    {
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

    /** The file a typed name would be written to. */
    public function fileNameFor(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', StringUtilService::withoutDiacritics(trim($name)));
        $name = trim((string)$name, '-');
        if ($name === '') {
            throw new \InvalidArgumentException('a preset needs a name');
        }

        return strtolower($name) . '.css';
    }

    /**
     * Core's own token values -- the default Preset -- read from the file that declares them.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function coreDefaults(): array
    {
        if ($this->defaults === null) {
            $path = defined('YESWIKI_PROGRAM_DIR')
                ? YESWIKI_PROGRAM_DIR . '/' . self::CORE_TOKENS_FILE
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

    /**
     * @return array{id: string, name: string, custom: bool, default: bool, complete: bool, missing: list<string>, path: string, href: string, values: array{light: array<string, string>, dark: array<string, string>}}
     */
    private function describe(string $id, string $path, bool $custom, string $default): array
    {
        $values = $this->valuesOf($this->cssAt($path));
        $missing = $this->missingIn($values);

        return [
            'id' => $id,
            'name' => pathinfo($id, PATHINFO_FILENAME),
            'custom' => $custom,
            'default' => $id === $default,
            'complete' => $missing === [],
            'missing' => $missing,
            'path' => $path,

            'href' => (string)$this->assets->urlFor($path),

            'values' => $this->withDefaults($values),
        ];
    }

    /**
     * The rule blocks in a stylesheet, as [selector, body, is inside a dark media query].
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
     *                               Runs of whitespace are collapsed to one space, which CSS does anyway and this has to as
     *                               well: the formatter wraps a long value over several lines, so a font stack came back
     *                               with newlines and indentation inside it. Nothing rendered wrong -- the browser reads it
     *                               the same -- but every exact comparison against it failed, so the rail could not
     *                               recognise its own `Monospace Code` stack and showed the raw text as an option of its
     *                               own, and isSystemStack() stopped recognising it and asked Google for a webfont called
     *                               `ui-monospace`.
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
     * The theme's own presets, `custom/themes/` shadowing the Program tree file by file.
     *
     * @return array<string, string> file name => path
     */
    private function shippedFiles(): array
    {
        $theme = $this->theme();
        $files = [];
        foreach (glob(YESWIKI_PROGRAM_DIR . '/themes/' . $theme . '/presets/*.css') ?: [] as $path) {
            $files[basename($path)] = $path;
        }
        foreach ($this->storage->glob('custom/themes/' . $theme . '/presets/*.css') as $path) {
            $files[basename($path)] = $path;
        }
        ksort($files);

        return $files;
    }

    /**
     * @return array<string, string> file name => path
     */
    private function instanceFiles(): array
    {
        $files = [];
        foreach ($this->storage->glob(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/*.css') as $path) {
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

    /** `<name>.css`, or `<name>-2.css`, `<name>-3.css`... */
    private function freeFileName(string $name): string
    {
        $file = $this->fileNameFor($name);
        $stem = pathinfo($file, PATHINFO_FILENAME);

        $suffix = 1;
        while ($this->storage->exists(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . $file)) {
            $file = $stem . '-' . (++$suffix) . '.css';
        }

        return $file;
    }

    /** A stylesheet from wherever it is: the Program tree ships presets and tokens, the instance stores its own. */
    private function cssAt(string $path): string
    {
        return $this->isSourcePath($path)
            ? (string)@file_get_contents($path)
            : $this->storage->read($path);
    }

    private function isSourcePath(string $path): bool
    {
        return defined('YESWIKI_PROGRAM_DIR') && str_starts_with($path, YESWIKI_PROGRAM_DIR . '/');
    }
}

<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * The wiki's colour presets: what they are, which one is on, and how to write one.
 *
 * A preset is a `:root { --primary-color: …; … }` stylesheet, linked last by CoreAssets so
 * it overrides the theme's own colours. Two kinds live side by side:
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
 * The scanning here is filesystem-first rather than going through ThemeManager::getTemplates(),
 * which is only populated once a page render has reached loadTemplates() -- an admin screen
 * renders its body *before* that, and would have found no presets at all.
 */
class PresetService
{
    /**
     * The variables a preset carries: what kind of input each one is, and what to call it.
     *
     * The list is closed on purpose: it is what ThemeManager::addCustomCSSPreset() writes,
     * and what the themes' own stylesheets consume. A preset file holding anything else
     * keeps it on disk but does not offer it for editing -- see valuesOf().
     *
     * The label keys live here rather than being built from the variable name in the
     * template: `_t('ADMIN_PRESET_VAR_' ~ name|upper)` would work and would be invisible to
     * every tool that looks for translation keys by grepping for them.
     */
    public const VARIABLES = [
        'primary-color' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_PRIMARY_COLOR'],
        'secondary-color-1' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_SECONDARY_COLOR_1'],
        'secondary-color-2' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_SECONDARY_COLOR_2'],
        'neutral-color' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_NEUTRAL_COLOR'],
        'neutral-soft-color' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_NEUTRAL_SOFT_COLOR'],
        'neutral-light-color' => ['kind' => 'color', 'label' => 'ADMIN_PRESET_VAR_NEUTRAL_LIGHT_COLOR'],
        'main-text-fontsize' => ['kind' => 'size', 'label' => 'ADMIN_PRESET_VAR_MAIN_TEXT_FONTSIZE'],
        'main-text-fontfamily' => ['kind' => 'font', 'label' => 'ADMIN_PRESET_VAR_MAIN_TEXT_FONTFAMILY'],
        'main-title-fontfamily' => ['kind' => 'font', 'label' => 'ADMIN_PRESET_VAR_MAIN_TITLE_FONTFAMILY'],
    ];

    /** The colour variables, in order -- the swatch strip a preset is recognised by. */
    public const SWATCHES = [
        'primary-color',
        'secondary-color-1',
        'secondary-color-2',
        'neutral-color',
        'neutral-soft-color',
        'neutral-light-color',
    ];

    /** What a preset falls back to per variable, so an empty form is still a valid preset. */
    private const DEFAULTS = [
        'primary-color' => '#0c5d6a',
        'secondary-color-1' => '#d8604c',
        'secondary-color-2' => '#d78958',
        'neutral-color' => '#4e5056',
        'neutral-soft-color' => '#57575c',
        'neutral-light-color' => '#f2f2f2',
        'main-text-fontsize' => '16px',
        'main-text-fontfamily' => 'sans-serif',
        'main-title-fontfamily' => 'sans-serif',
    ];

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
     * @return list<array{id: string, name: string, custom: bool, default: bool, path: string, href: string, values: array<string, string>}>
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
     * The wiki's default preset -- what every page wears -- or '' for the theme's own colours.
     *
     * Distinct from what the Personnalisation screen is *previewing*, which is a matter for
     * that one page and never leaves the browser.
     */
    public function default(): string
    {
        $configured = $this->params->has('favorite_preset') ? $this->params->get('favorite_preset') : '';

        return is_string($configured) ? $configured : '';
    }

    /** @return array{id: string, name: string, custom: bool, default: bool, path: string, href: string, values: array<string, string>}|null */
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
     * The values a rail opens on: a named preset's, or the defaults for a brand new one.
     *
     * @return array<string, string>
     */
    public function valuesFor(string $id): array
    {
        return $this->find($id)['values'] ?? self::DEFAULTS;
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
     * than rebuilt from its nine variables: a preset can carry `@font-face` blocks (save()
     * appends them) and variables this screen does not offer, and a copy that dropped them
     * would not be a copy.
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
     * Every variable is written, missing ones falling back to the default: a preset is read
     * as a whole, and one that declared five of nine would leave the other four to whatever
     * the theme happens to say -- which is not what the person editing it saw.
     *
     * @param array<string, string> $values
     */
    public function save(string $id, string $name, array $values): string
    {
        $existing = $id === '' ? null : $this->find($id);
        if ($id !== '' && ($existing === null || !$existing['custom'])) {
            throw new \InvalidArgumentException('not an instance preset: ' . $id);
        }

        $file = $this->fileNameFor($name);
        $saved = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $file;

        $post = [];
        foreach (array_keys(self::VARIABLES) as $variable) {
            $value = trim((string)($values[$variable] ?? ''));
            $post[$variable] = $value === '' ? self::DEFAULTS[$variable] : $value;
        }

        // ThemeManager writes the file and, for the two font families, downloads and
        // installs the webfont locally -- which is the reason not to write the file here
        $result = $this->themeManager->addCustomCSSPreset($file, $post);
        if (empty($result['status'])) {
            throw new \RuntimeException((string)($result['message'] ?? 'preset not saved'));
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
     * The variables a preset stylesheet declares.
     *
     * Deliberately more forgiving than ThemeManager's own parser, which returns nothing at
     * all when one colour is not a hex literal: here a preset is being *listed*, and a file
     * with one unusual value should show its other eight rather than appear empty.
     *
     * @return array<string, string>
     */
    public function valuesOf(string $css): array
    {
        if (!preg_match('/:root\s*\{([^}]*)\}/', $css, $root)) {
            return [];
        }

        $values = [];
        if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $root[1], $declarations, PREG_SET_ORDER)) {
            foreach ($declarations as $declaration) {
                if (array_key_exists($declaration[1], self::VARIABLES)) {
                    $values[$declaration[1]] = trim($declaration[2]);
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

    /** @return array{id: string, name: string, custom: bool, default: bool, path: string, href: string, values: array<string, string>} */
    private function describe(string $id, string $path, bool $custom, string $default): array
    {
        $values = $this->valuesOf((string)file_get_contents($path));

        return [
            'id' => $id,
            'name' => pathinfo($id, PATHINFO_FILENAME),
            'custom' => $custom,
            'default' => $id === $default,
            'path' => $path,
            // the address the screen previews from -- resolved the way every other stylesheet
            // is, so a farm instance gets its cache/assets/{version}/ copy and not a path
            // into the shared code tree
            'href' => (string)$this->assets->urlFor($path),
            'values' => $values + self::DEFAULTS,
        ];
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

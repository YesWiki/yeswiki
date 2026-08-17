<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Render\Service\PresetService;

/**
 * ADR-0021: a Preset stops declaring what core can derive, and stops holding typed lengths.
 *
 * ADR-0020 gave a Preset forty-nine tokens to declare. Eighteen of them were never a
 * decision: a hover colour is the brand nudged toward the ink, a muted text is the text
 * faded into the surface, the panel behind a success message is that green washed into the
 * page. Core computes those now, and a Preset declaring them is carrying a value that has
 * stopped being asked for. Eleven more were the spacing ramp, which is three steps now.
 *
 * So this is not a rename: the file is REBUILT from what it says. Every value that survived
 * is carried across, every value core now derives is dropped, and the tokens ADR-0021 added
 * -- the top bar's and the footer's colours, the heading colours and scale, the border width,
 * the shadow strength -- are seeded from what the file already implied rather than left
 * blank, because a blank one is an incomplete Preset and this migration is not entitled to
 * make somebody's working preset fail validation.
 *
 * Seeded, specifically:
 *   - the bar and the footer from the surfaces they used to be painted with by the theme, so
 *     a wiki looks the same the morning after the upgrade as the evening before;
 *   - the heading colours from the primary and the third colour, which is what the theme's
 *     h1-h5 ramp resolved to;
 *   - the three spacing steps from old steps 2, 5 and 8 -- the ones core's own scale used as
 *     its "inside a control / inside a component / between components" anchors;
 *   - the corner scale from the old `--yw-radius-md` against core's `0.5rem`, so a preset
 *     that had rounded everything stays rounded by the same factor.
 *
 * **A wiki that had chosen `colored-navbar.css` gets its coloured bar back here**, as
 * `--yw-navbar-bg: <its primary>`. That stylesheet was a theme style, and it is gone; the
 * bar's colour is a Preset's now. Without this the upgrade would silently repaint every such
 * wiki's bar white, which is the single most visible thing on the site.
 *
 * Both places an instance can own a preset are covered: `custom/css-presets/` (its own) and
 * `custom/themes/<theme>/presets/` (its overrides of the theme's). `themes/` itself is code
 * -- on a farm it is shared and replaced on upgrade -- and is not touched.
 *
 * Anything in the file that is not a `:root` or scheme block is kept verbatim and appended:
 * a preset can carry `@font-face` rules (save() appends them) and rules of its own, and a
 * migration that dropped them would be deleting a webmaster's work rather than rewriting it.
 *
 * Idempotent per file: one that already declares `--yw-space-sm` is left alone, so running
 * the upgrade twice cannot fold a migrated preset a second time.
 */
class PresetsLoseTheirDerivedTokens extends YesWikiMigration
{
    /**
     * The eleven-step ramp's three anchors: what "inside a control", "inside a component"
     * and "between components" were on it. The other eight steps had no separate meaning,
     * which is why there are three now.
     */
    private const SPACE_ANCHOR = [
        'yw-space-sm-y' => 'yw-space-2',
        'yw-space-md-y' => 'yw-space-5',
        'yw-space-lg-y' => 'yw-space-8',
    ];

    /**
     * How much wider than tall each step's blank is, from core's own values.
     *
     * A step is two numbers now, and the old ramp had one. The vertical keeps what the ramp
     * said -- that is the axis a stacked page is read down, and the one somebody who tuned
     * their spacing was tuning. The horizontal is derived at core's ratio, so a preset that
     * was tighter than core stays proportionally tighter on both axes rather than being
     * snapped back to core's own horizontal.
     */
    private const SPACE_X_RATIO = [
        'yw-space-sm-x' => ['yw-space-sm-y', 0.35 / 0.25],
        'yw-space-md-x' => ['yw-space-md-y', 1.0 / 0.75],
        'yw-space-lg-x' => ['yw-space-lg-y', 1.5 / 2.0],
    ];

    /** The ramp core writes, and the sizes a migrated preset starts from. */
    private const HEADING_SIZE = [
        'yw-heading-1-size' => '2rem',
        'yw-heading-2-size' => '1.5rem',
        'yw-heading-3-size' => '1.25rem',
        'yw-heading-4-size' => '1.1rem',
        'yw-heading-5-size' => '1rem',
        'yw-heading-6-size' => '0.9rem',
    ];

    /** Core's own `--yw-radius-md`: the length `--yw-radius-scale: 1` now means. */
    private const RADIUS_UNIT_REM = 0.5;

    public function run()
    {
        $log = $this->getService(AdministrativeLogService::class);
        $presets = $this->getService(PresetService::class);

        $migrated = [];
        foreach ($this->files() as $path) {
            $css = @file_get_contents($path);
            if ($css === false) {
                // a preset that cannot be read cannot be migrated, and a migration that
                // returns marks itself done and never runs again -- so this throws, and the
                // upgrade stops where somebody can still see which file it was
                throw new RuntimeException("preset $path could not be read");
            }
            if (!$this->needsMigration($css)) {
                continue;
            }

            $rewritten = $this->rewrite($css, $presets);
            if (@file_put_contents($path, $rewritten) === false) {
                throw new RuntimeException("preset $path could not be written: check the permissions on " . dirname($path));
            }

            $missing = count($presets->missingIn($presets->valuesOf($rewritten)));
            $migrated[] = basename($path) . ($missing === 0 ? '' : " ($missing tokens left to fill in)");
        }

        // The theme style that painted the bar is gone: its whole content is four tokens now.
        // Left in the configuration file it would name a stylesheet that does not exist, and
        // ThemeManager would quietly fall back -- which is the same white bar, only without
        // anything in the log saying why.
        $navbarWasColoured = $this->forgetColouredNavbarStyle();

        if ($migrated !== [] || $navbarWasColoured) {
            $log->log(
                'migration',
                'presets simplified (ADR-0021): what core can derive -- hover colours, muted'
                . ' text, border shades, the panel and ink of each status colour, the focus'
                . ' ring, the shadow colours, the corner radii -- is no longer declared, and'
                . ' the eleven spacing steps are three. Rewritten: '
                . ($migrated === [] ? 'none' : implode(', ', $migrated)) . '.'
                . ($navbarWasColoured
                    ? ' The colored-navbar style is gone; the top bar\'s colours are tokens now'
                    . ' and this wiki\'s presets were given its coloured bar.'
                    : '')
            );
        }
    }

    /** @return list<string> */
    private function files(): array
    {
        $paths = [];
        foreach (['custom/css-presets/*.css', 'custom/themes/*/presets/*.css'] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * A file to rewrite is one written in the ADR-0020 vocabulary and not yet in this one.
     *
     * Both halves matter: a preset already carrying `--yw-space-sm` must not be folded a
     * second time, and a file under `custom/css-presets/` that is not a preset at all --
     * somebody's stylesheet parked there -- has nothing here to rewrite and is left alone.
     * A file still speaking the *pre*-ADR-0020 nine variables is likewise left alone: the
     * earlier migration renames it first, and this one then sees it on the next pass.
     */
    private function needsMigration(string $css): bool
    {
        if (str_contains($css, '--yw-space-sm')) {
            return false;
        }

        return (bool)preg_match('/--yw-(space-[0-9]|radius-md|primary)\s*:/i', $css);
    }

    /**
     * Rebuild the file: the values it still has, plus the ones ADR-0021 now asks for.
     *
     * Rebuilt rather than edited in place, unlike the ADR-0020 migration, because this is not
     * a rename -- eighteen declarations go, eleven collapse to three, and nine tokens that
     * were never in the file have to appear. Editing that in place would leave a file whose
     * order and comments describe a token set it no longer has.
     */
    private function rewrite(string $css, PresetService $presets): string
    {
        $old = $this->rawValuesOf($css);
        $defaults = $presets->coreDefaults();

        $values = ['light' => [], 'dark' => []];
        foreach (PresetService::SCHEMES as $scheme) {
            // a colour missing from the dark set falls back to the light one before core's:
            // a preset that only ever had a light half is a preset whose colours are those,
            // and core's dark blue in the middle of somebody's greens is worse than a dark
            // mode that is merely the light one
            $have = fn (string $token): string => $old[$scheme][$token]
                ?? ($scheme === 'dark' ? ($old['light'][$token] ?? '') : '')
                ?: ($defaults[$scheme][$token] ?? $defaults['light'][$token] ?? '');

            foreach (array_keys(PresetService::TOKENS) as $token) {
                $values[$scheme][$token] = $have($token);
            }

            // the tokens ADR-0021 added: seeded from what the file already implied, so the
            // wiki looks the same after the upgrade as before it
            $values[$scheme]['yw-navbar-bg'] = $this->colouredNavbar()
                ? $have('yw-primary')
                : $have('yw-surface-raised');
            $values[$scheme]['yw-navbar-text'] = $this->colouredNavbar()
                ? $have('yw-on-primary')
                : $have('yw-text');
            $values[$scheme]['yw-footer-bg'] = $have('yw-surface');
            $values[$scheme]['yw-footer-text'] = $have('yw-text');
            // one colour per heading level: h1-h3 take the brand, h4-h6 the third colour,
            // which is the ramp the theme resolved to before any of these were tokens
            for ($level = 1; $level <= 6; $level++) {
                $values[$scheme]['yw-heading-' . $level] = $level <= 3
                    ? $have('yw-primary')
                    : $have('yw-tertiary');
            }
        }

        // The two inks. `--yw-text-on-dark` was already exactly "the ink for a dark ground",
        // so it carries straight over; the light-ground one is seeded from the preset's own
        // text colour, which IS the ink it had chosen for reading on a light page. Between
        // them they replace that token and `--yw-on-primary`, and core picks per fill.
        $values['light']['yw-ink-on-dark'] = $old['light']['yw-text-on-dark']
            ?? $defaults['light']['yw-ink-on-dark']
            ?? '#ffffff';
        $values['light']['yw-ink-on-light'] = $old['light']['yw-text']
            ?? $defaults['light']['yw-ink-on-light']
            ?? '#14171a';

        // the scheme-independent half, all of it read from the light block. The heading
        // sizes are core's ramp: there was nothing in the old file to carry over, because
        // until now a heading's size was the browser's business and not a Preset's.
        foreach (self::HEADING_SIZE as $token => $size) {
            $values['light'][$token] = $size;
        }
        // casing and alignment are new too: nothing in the old file expressed either, so
        // every level starts on "as typed", aligned with the text around it
        for ($level = 1; $level <= 6; $level++) {
            $values['light']['yw-heading-' . $level . '-transform'] = 'none';
            $values['light']['yw-heading-' . $level . '-align'] = 'start';
        }
        $values['light']['yw-border-width'] = '1px';
        $values['light']['yw-shadow-strength'] = '1';
        foreach (self::SPACE_ANCHOR as $token => $anchor) {
            $values['light'][$token] = $old['light'][$anchor]
                ?? $defaults['light'][$token]
                ?? '0.75rem';
        }
        foreach (self::SPACE_X_RATIO as $token => [$from, $ratio]) {
            $values['light'][$token] = $this->scaled($values['light'][$from], $ratio)
                ?? $defaults['light'][$token]
                ?? '1rem';
        }
        $values['light']['yw-radius-scale'] = $this->radiusScale($old['light']['yw-radius-md'] ?? null);

        return $presets->toCss($values) . $this->extras($css);
    }

    /**
     * Every `--yw-*` declaration a stylesheet makes, per scheme -- old names included.
     *
     * PresetService::valuesOf() answers only for tokens that still exist, which is exactly
     * the wrong question here: what this migration needs is the eleven spacing steps and the
     * radius that are no longer tokens. Deliberately forgiving, same as that one -- a value
     * this cannot represent is still a value, and a preset is being read rather than judged.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function rawValuesOf(string $css): array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        $values = ['light' => [], 'dark' => []];

        // the dark half is whatever sits inside a `prefers-color-scheme: dark` query or a
        // `[data-theme='dark']` selector; a hand-written preset may lead with either, and one
        // carrying only one of them is a preset whose toggle works in one direction
        $dark = '';
        if (preg_match_all('/@media[^{]*prefers-color-scheme\s*:\s*dark[^{]*\{(.*?)\n\}/s', $css, $matches)) {
            $dark .= implode("\n", $matches[1]);
        }
        if (preg_match_all('/\[data-theme=[\'"]dark[\'"]\]\s*\{(.*?)\n\}/s', $css, $matches)) {
            $dark .= implode("\n", $matches[1]);
        }
        $light = str_replace($dark, '', $css);

        foreach (['light' => $light, 'dark' => $dark] as $scheme => $source) {
            if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $source, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // first wins: a preset's own `:root` comes before anything it appends
                    $values[$scheme][$match[1]] ??= trim($match[2]);
                }
            }
        }

        return $values;
    }

    /**
     * `--yw-radius-md: 1rem` on a scale whose 1 is `0.5rem` means 2 -- "twice as round".
     *
     * Anything this cannot read as a length (a `var()`, a `clamp()`) becomes 1 rather than 0:
     * a preset whose radius could not be measured is not a preset that asked for square
     * corners, and 0 would be a visible change nobody requested.
     */
    private function radiusScale(?string $radius): string
    {
        if ($radius === null || !preg_match('/^([0-9.]+)(rem|px|em)$/', trim($radius), $match)) {
            return '1';
        }

        $length = (float)$match[1];
        if ($match[2] === 'px') {
            $length /= 16;
        }
        $scale = round($length / self::RADIUS_UNIT_REM, 2);

        return $scale <= 0 ? '0' : rtrim(rtrim(number_format($scale, 2, '.', ''), '0'), '.');
    }

    /**
     * A `rem` length times a ratio, snapped to the slider's step, or null if it is not one.
     *
     * Null rather than a guess: a preset whose spacing was written as `clamp()` or a `var()`
     * has a horizontal axis nobody can compute from it, and core's own value is a better
     * answer than a number derived from something this could not read.
     */
    private function scaled(string $length, float $ratio): ?string
    {
        if (!preg_match('/^([0-9.]+)rem$/', trim($length), $match)) {
            return null;
        }

        $scaled = round(((float)$match[1] * $ratio) / 0.05) * 0.05;

        return rtrim(rtrim(number_format($scaled, 4, '.', ''), '0'), '.') . 'rem';
    }

    /** Everything that is not a `:root` or scheme block: `@font-face`, rules of their own. */
    private function extras(string $css): string
    {
        $extras = '';
        if (preg_match_all('/@font-face\s*\{[^}]*\}/s', $css, $matches)) {
            $extras = "\n" . implode("\n\n", $matches[0]) . "\n";
        }

        return $extras;
    }

    private ?bool $colouredNavbar = null;

    /** Was this wiki wearing the theme style whose whole job was colouring the top bar? */
    private function colouredNavbar(): bool
    {
        if ($this->colouredNavbar === null) {
            $style = $this->params->has('favorite_style') ? $this->params->get('favorite_style') : '';
            $this->colouredNavbar = is_string($style) && str_contains($style, 'colored-navbar');
        }

        return $this->colouredNavbar;
    }

    /**
     * Stop naming a stylesheet that no longer exists.
     *
     * Left alone, ThemeManager falls back to the theme's default style, which renders
     * correctly -- so nothing breaks, and the configuration file keeps a line pointing at a
     * deleted file for the next person to wonder about.
     */
    private function forgetColouredNavbarStyle(): bool
    {
        if (!$this->colouredNavbar()) {
            return false;
        }

        $config = $this->getService(ConfigurationService::class)
            ->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config['favorite_style'] = CSS_PAR_DEFAUT;
        $config->write();

        return true;
    }
}

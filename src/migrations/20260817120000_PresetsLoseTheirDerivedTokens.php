<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Render\Service\PresetService;

/** ADR-0021: a Preset stops declaring what core can derive, and stops holding typed lengths. */
class PresetsLoseTheirDerivedTokens extends YesWikiMigration
{
    /**
     * The eleven-step ramp's three anchors: what "inside a control", "inside a component" and "between components" were on it.
     */
    private const SPACE_ANCHOR = [
        'yw-space-sm-y' => 'yw-space-2',
        'yw-space-md-y' => 'yw-space-5',
        'yw-space-lg-y' => 'yw-space-8',
    ];

    /** How much wider than tall each step's blank is, from core's own values. */
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
            $css = $this->getService(Storage::class)->read($path);
            if ($css === '') {
                throw new RuntimeException("preset $path could not be read");
            }
            if (!$this->needsMigration($css)) {
                continue;
            }

            $rewritten = $this->rewrite($css, $presets);
            try {
                $this->getService(Storage::class)->write($path, $rewritten);
            } catch (Throwable) {
                throw new RuntimeException("preset $path could not be written: check the permissions on " . dirname($path));
            }

            $missing = count($presets->missingIn($presets->valuesOf($rewritten)));
            $migrated[] = basename($path) . ($missing === 0 ? '' : " ($missing tokens left to fill in)");
        }

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

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $paths = [];
        foreach (['custom/css-presets/*.css', 'custom/themes/*/presets/*.css'] as $pattern) {
            foreach ($this->getService(Storage::class)->glob($pattern) as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /** A file to rewrite is one written in the ADR-0020 vocabulary and not yet in this one. */
    private function needsMigration(string $css): bool
    {
        if (str_contains($css, '--yw-space-sm')) {
            return false;
        }

        return (bool)preg_match('/--yw-(space-[0-9]|radius-md|primary)\s*:/i', $css);
    }

    /** Rebuild the file: the values it still has, plus the ones ADR-0021 now asks for. */
    private function rewrite(string $css, PresetService $presets): string
    {
        $old = $this->rawValuesOf($css);
        $defaults = $presets->coreDefaults();

        $values = ['light' => [], 'dark' => []];
        foreach (PresetService::SCHEMES as $scheme) {
            $have = fn (string $token): string => $old[$scheme][$token]
                ?? ($scheme === 'dark' ? ($old['light'][$token] ?? '') : '')
                ?: ($defaults[$scheme][$token] ?? $defaults['light'][$token] ?? '');

            foreach (array_keys(PresetService::TOKENS) as $token) {
                $values[$scheme][$token] = $have($token);
            }

            $values[$scheme]['yw-navbar-bg'] = $this->colouredNavbar()
                ? $have('yw-primary')
                : $have('yw-surface-raised');
            $values[$scheme]['yw-navbar-text'] = $this->colouredNavbar()
                ? $have('yw-on-primary')
                : $have('yw-text');
            $values[$scheme]['yw-footer-bg'] = $have('yw-surface');
            $values[$scheme]['yw-footer-text'] = $have('yw-text');

            for ($level = 1; $level <= 6; $level++) {
                $values[$scheme]['yw-heading-' . $level] = $level <= 3
                    ? $have('yw-primary')
                    : $have('yw-tertiary');
            }
        }

        $values['light']['yw-ink-on-dark'] = $old['light']['yw-text-on-dark']
            ?? $defaults['light']['yw-ink-on-dark']
            ?? '#ffffff';
        $values['light']['yw-ink-on-light'] = $old['light']['yw-text']
            ?? $defaults['light']['yw-ink-on-light']
            ?? '#14171a';

        foreach (self::HEADING_SIZE as $token => $size) {
            $values['light'][$token] = $size;
        }

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
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function rawValuesOf(string $css): array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        $values = ['light' => [], 'dark' => []];

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
                    $values[$scheme][$match[1]] ??= trim($match[2]);
                }
            }
        }

        return $values;
    }

    /** `--yw-radius-md: 1rem` on a scale whose 1 is `0.5rem` means 2 -- "twice as round". */
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

    /** A `rem` length times a ratio, snapped to the slider's step, or null if it is not one. */
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

    /** Stop naming a stylesheet that no longer exists. */
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

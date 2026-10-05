<?php

namespace YesWiki\Render\Service;

/** Turns a preset written before ADR-0021, in Doryphore's nine variables or ADR-0020's tokens, into a complete Preset. */
class PresetUpgrader
{
    /** Doryphore's nine variables, and the token each one's value means now (ADR-0020). */
    public const OLD_VARIABLES = [
        'primary-color' => 'yw-primary',
        'secondary-color-1' => 'yw-secondary',
        'secondary-color-2' => 'yw-tertiary',
        'neutral-color' => 'yw-text',
        'neutral-soft-color' => 'yw-text-muted',
        'neutral-light-color' => 'yw-surface-sunken',
        'main-text-fontsize' => 'yw-font-size-base',
        'main-text-fontfamily' => 'yw-font-body',
        'main-title-fontfamily' => 'yw-font-heading',
    ];

    /** The eleven-step ramp's three anchors: inside a control, inside a component, between components. */
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

    /** The heading ramp core writes, which a converted preset starts from. */
    private const HEADING_SIZE = [
        'yw-heading-1-size' => '2rem',
        'yw-heading-2-size' => '1.5rem',
        'yw-heading-3-size' => '1.25rem',
        'yw-heading-4-size' => '1.1rem',
        'yw-heading-5-size' => '1rem',
        'yw-heading-6-size' => '0.9rem',
    ];

    /** Doryphore's margot coloured its titles this way: h1 and h2 primary, h3 secondary, the rest the second secondary. */
    private const HEADING_COLOUR = [
        1 => 'yw-primary',
        2 => 'yw-primary',
        3 => 'yw-secondary',
        4 => 'yw-tertiary',
        5 => 'yw-tertiary',
        6 => 'yw-tertiary',
    ];

    /** The colours a dark scheme may borrow from the light one when a preset never had a dark one. */
    private const SCHEME_FREE_COLOURS = [
        'yw-primary', 'yw-secondary', 'yw-tertiary', 'yw-success', 'yw-danger', 'yw-warning', 'yw-info',
    ];

    /** Core's own `--yw-radius-md`: the length `--yw-radius-scale: 1` means. */
    private const RADIUS_UNIT_REM = 0.5;

    /** Font family names that are CSS keywords, never a webfont. */
    private const GENERIC_FAMILIES = [
        'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif',
        'ui-monospace', 'ui-rounded', 'inherit', 'initial', 'unset', 'emoji', 'math',
    ];

    public function __construct(
        private readonly PresetService $presets,
        private readonly ThemeManager $themeManager,
    ) {
    }

    /** Whether a stylesheet is a preset this class has something to do to. */
    public function needsUpgrade(string $css): bool
    {
        return $this->speaksDoryphore($css) || $this->speaksAdr0020($css);
    }

    /** Doryphore's vocabulary, and none of the new one. */
    public function speaksDoryphore(string $css): bool
    {
        if (preg_match('/--yw-[a-z0-9-]+\s*:/i', $css)) {
            return false;
        }
        foreach (array_keys(self::OLD_VARIABLES) as $variable) {
            if (preg_match('/--' . preg_quote($variable, '/') . '\s*:/i', $css)) {
                return true;
            }
        }

        return false;
    }

    /** ADR-0020's tokens, from before a preset stopped declaring what core derives. */
    public function speaksAdr0020(string $css): bool
    {
        if (str_contains($css, '--yw-space-sm')) {
            return false;
        }

        return (bool)preg_match('/--yw-(space-[0-9]|radius-md|primary)\s*:/i', $css);
    }

    /** Whether a Doryphore theme style painted the top bar in the primary colour. */
    public static function hadColouredNavbar(mixed $style): bool
    {
        if (!is_string($style) || $style === '') {
            return false;
        }

        return str_contains($style, 'colored-navbar') || basename($style) === 'margot.css';
    }

    /**
     * Rewrite one preset stylesheet, which lives at $path, into a complete Preset.
     *
     * @return array{css: string, missing: list<string>, localised: list<string>, imports: list<string>}
     */
    public function upgrade(string $css, string $path, bool $colouredNavbar): array
    {
        if ($this->speaksDoryphore($css)) {
            $css = $this->renamed($css);
        }

        $values = $this->values($this->rawValuesOf($css), $colouredNavbar);
        [$imports, $faces, $localised] = $this->fonts($css, $path, $values['light']);

        $rewritten = ($imports === [] ? '' : implode("\n", $imports) . "\n\n")
            . $this->presets->toCss($values)
            . ($faces === [] ? '' : "\n" . implode("\n\n", $faces) . "\n");

        return [
            'css' => $rewritten,
            'missing' => $this->presets->missingIn($this->presets->valuesOf($rewritten)),
            'localised' => $localised,
            'imports' => $imports,
        ];
    }

    /** The nine declarations under their token names. */
    private function renamed(string $css): string
    {
        foreach (self::OLD_VARIABLES as $variable => $token) {
            $css = (string)preg_replace('/--' . preg_quote($variable, '/') . '(?![a-z0-9-])/i', '--' . $token, $css);
        }

        return $css;
    }

    /**
     * Every authored token, from what the file said and what core would say.
     *
     * @param array{light: array<string, string>, dark: array<string, string>} $old
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function values(array $old, bool $colouredNavbar): array
    {
        $defaults = $this->presets->coreDefaults();
        $have = function (string $scheme, string $token) use ($old, $defaults): string {
            $own = $old[$scheme][$token] ?? '';
            if ($own === '' && $scheme === 'dark' && in_array($token, self::SCHEME_FREE_COLOURS, true)) {
                $own = $old['light'][$token] ?? '';
            }

            return $own !== '' ? $own : ($defaults[$scheme][$token] ?? $defaults['light'][$token] ?? '');
        };

        $values = ['light' => [], 'dark' => []];
        $inkOnDark = $old['light']['yw-ink-on-dark'] ?? $old['light']['yw-text-on-dark'] ?? $defaults['light']['yw-ink-on-dark'] ?? '#ffffff';
        $inkOnLight = $old['light']['yw-ink-on-light'] ?? $old['light']['yw-text'] ?? $defaults['light']['yw-ink-on-light'] ?? '#14171a';
        $values['light']['yw-ink-on-dark'] = $inkOnDark;
        $values['light']['yw-ink-on-light'] = $inkOnLight;

        foreach (PresetService::SCHEMES as $scheme) {
            foreach (array_keys(PresetService::TOKENS) as $token) {
                $values[$scheme][$token] ??= $have($scheme, $token);
            }

            $primary = $have($scheme, 'yw-primary');
            if ($colouredNavbar) {
                $values[$scheme]['yw-navbar-bg'] = $primary;
                $values[$scheme]['yw-navbar-text'] = $old[$scheme]['yw-on-primary'] ?? $old['light']['yw-on-primary']
                    ?? $this->presets->inkOn($primary, $inkOnLight, $inkOnDark);
            } else {
                $values[$scheme]['yw-navbar-bg'] = $have($scheme, 'yw-surface-raised');
                $values[$scheme]['yw-navbar-text'] = $inkOnLight;
            }
            $values[$scheme]['yw-footer-bg'] = $have($scheme, 'yw-surface');
            $values[$scheme]['yw-footer-text'] = $inkOnLight;

            foreach (self::HEADING_COLOUR as $level => $colour) {
                $values[$scheme]['yw-heading-' . $level] = 'var(--' . $colour . ')';
            }
        }

        if (!$colouredNavbar && $values['light']['yw-navbar-bg'] === $have('light', 'yw-surface')) {
            $values['dark']['yw-navbar-bg'] = $have('dark', 'yw-surface');
        }
        $values['dark']['yw-navbar-text'] = $colouredNavbar ? $values['dark']['yw-navbar-text'] : $inkOnDark;
        $values['dark']['yw-footer-text'] = $inkOnDark;
        foreach (self::HEADING_COLOUR as $level => $colour) {
            $ratio = $this->presets->contrastRatio($have('dark', $colour), $have('dark', 'yw-surface'));
            if ($ratio !== null && $ratio < 3) {
                $values['dark']['yw-heading-' . $level] = $defaults['dark']['yw-heading-' . $level] ?? $inkOnDark;
            }
        }

        foreach (self::HEADING_SIZE as $token => $size) {
            $values['light'][$token] = $old['light'][$token] ?? $size;
        }
        for ($level = 1; $level <= 6; $level++) {
            $values['light']['yw-heading-' . $level . '-transform'] = $old['light']['yw-heading-' . $level . '-transform'] ?? 'none';
            $values['light']['yw-heading-' . $level . '-align'] = $old['light']['yw-heading-' . $level . '-align'] ?? 'start';
        }
        $values['light']['yw-border-width'] = $old['light']['yw-border-width'] ?? '1px';
        $values['light']['yw-shadow-strength'] = $old['light']['yw-shadow-strength'] ?? '1';
        foreach (self::SPACE_ANCHOR as $token => $anchor) {
            $values['light'][$token] = $old['light'][$token] ?? $old['light'][$anchor] ?? $defaults['light'][$token] ?? '0.75rem';
        }
        foreach (self::SPACE_X_RATIO as $token => [$from, $ratio]) {
            $values['light'][$token] = $old['light'][$token]
                ?? $this->scaled($values['light'][$from], $ratio)
                ?? $defaults['light'][$token]
                ?? '1rem';
        }
        $values['light']['yw-radius-scale'] = $old['light']['yw-radius-scale'] ?? $this->radiusScale($old['light']['yw-radius-md'] ?? null);
        $values['light']['yw-font-size-base'] = $this->pixels($values['light']['yw-font-size-base']);

        return $values;
    }

    /**
     * The imports to keep, the `@font-face` rules to carry, and the families fetched to make them.
     *
     * @param array<string, string> $light
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    private function fonts(string $css, string $path, array $light): array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        $faces = preg_match_all('/@font-face\s*\{[^}]*\}/s', $css, $matches) ? $matches[0] : [];
        $imports = [];
        $localised = [];

        if (preg_match_all('/@import\s+(?:url\(\s*([\'"]?)(.+?)\1\s*\)|([\'"])(.+?)\3)[^;]*;/i', $css, $found, PREG_SET_ORDER)) {
            foreach ($found as $match) {
                $rule = $match[0];
                $url = ($match[2] ?? '') !== '' ? $match[2] : ($match[4] ?? '');
                $families = $this->googleFamiliesIn($url);
                $rules = $families === [] ? null : $this->facesFor($families, $path);
                if ($rules === null) {
                    $imports[] = trim($rule);
                    continue;
                }
                array_push($faces, ...$rules);
                array_push($localised, ...$families);
            }
        }

        foreach (['yw-font-body', 'yw-font-heading', 'yw-font-mono'] as $token) {
            $family = $this->webfontIn($light[$token] ?? '');
            if ($family === '' || $this->declares($faces, $family) || $this->imports($imports, $family)) {
                continue;
            }
            $rules = $this->facesFor([$family], $path, $this->presets->googleFontNamed($family) !== '');
            if ($rules !== null) {
                array_push($faces, ...$rules);
                $localised[] = $family;
            }
        }

        return [$imports, array_values(array_unique(array_map('trim', $faces))), array_values(array_unique($localised))];
    }

    /**
     * The families a Google Fonts stylesheet address asks for, or none if it is not one.
     *
     * @return list<string>
     */
    private function googleFamiliesIn(string $url): array
    {
        $parts = parse_url(html_entity_decode($url));
        if (!is_array($parts) || strtolower((string)($parts['host'] ?? '')) !== 'fonts.googleapis.com') {
            return [];
        }

        $families = [];
        foreach (explode('&', (string)($parts['query'] ?? '')) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ($key !== 'family') {
                continue;
            }
            foreach (explode('|', urldecode($value)) as $family) {
                $family = trim(explode(':', $family)[0]);
                if ($family !== '') {
                    $families[] = $this->presets->googleFontNamed($family) ?: $family;
                }
            }
        }

        return array_values(array_unique($families));
    }

    /**
     * Rules for every family, from `custom/fonts/` or fetched into it, or null if one cannot be had.
     *
     * @param list<string> $families
     *
     * @return list<string>|null
     */
    private function facesFor(array $families, string $path, bool $fetch = true): ?array
    {
        $rules = [];
        foreach ($families as $family) {
            $stored = $this->themeManager->fontFaces($family);
            if ($stored === '' && $fetch) {
                try {
                    $this->themeManager->installFont($family);
                } catch (\Throwable) {
                }
                $stored = $this->themeManager->fontFaces($family);
            }
            if ($stored === '' || !preg_match_all('/@font-face\s*\{[^}]*\}/s', $stored, $blocks)) {
                return null;
            }
            foreach ($blocks[0] as $block) {
                $rules[] = $this->relocated($block, $path);
            }
        }

        return $rules;
    }

    /** A rule written for `custom/css-presets/`, pointing at the same font from wherever $path is. */
    private function relocated(string $rule, string $path): string
    {
        $up = str_repeat('../', substr_count(dirname($path), '/') + 1);

        return (string)preg_replace('~url\(\s*([\'"]?)(?:\.\./)+custom/~', 'url($1' . $up . 'custom/', $rule);
    }

    /** The first family of a font stack, when it is a webfont rather than something the reader has. */
    private function webfontIn(string $stack): string
    {
        if (trim($stack) === '' || PresetService::isSystemStack($stack) || str_starts_with(trim($stack), 'var(')) {
            return '';
        }
        $family = trim(explode(',', $stack)[0], " \t\n'\"");
        if ($family === '' || in_array(strtolower($family), self::GENERIC_FAMILIES, true)) {
            return '';
        }
        foreach (PresetService::FONT_STACKS as $systemStack) {
            if (strcasecmp(trim(explode(',', $systemStack)[0], " '\""), $family) === 0) {
                return '';
            }
        }

        return $family;
    }

    /** @param list<string> $faces */
    private function declares(array $faces, string $family): bool
    {
        foreach ($faces as $face) {
            if (preg_match('/font-family\s*:\s*[\'"]?' . preg_quote($family, '/') . '[\'"]?\s*;/i', $face)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $imports */
    private function imports(array $imports, string $family): bool
    {
        $needles = array_map('strtolower', [$family, str_replace(' ', '+', $family), rawurlencode($family)]);
        foreach ($imports as $import) {
            foreach ($needles as $needle) {
                if (str_contains(strtolower($import), $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every custom property a stylesheet declares, per scheme -- old names included.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function rawValuesOf(string $css): array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string)preg_replace('/@font-face\s*\{[^}]*\}/s', '', $css);
        $values = ['light' => [], 'dark' => []];

        $dark = '';
        if (preg_match_all('/@media[^{]*prefers-color-scheme\s*:\s*dark[^{]*\{(.*?)\n\}/s', $css, $matches)) {
            $dark .= implode("\n", $matches[1]);
        }
        if (preg_match_all('/\[data-theme=[\'"]dark[\'"]\]\s*\{(.*?)\n\}/s', $css, $matches)) {
            $dark .= implode("\n", $matches[1]);
        }
        $light = $dark === '' ? $css : str_replace($dark, '', $css);

        foreach (['light' => $light, 'dark' => $dark] as $scheme => $source) {
            if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $source, $found, PREG_SET_ORDER)) {
                foreach ($found as $match) {
                    $value = (string)preg_replace('/\s+/', ' ', trim($match[2]));
                    if ($value !== '') {
                        $values[$scheme][$match[1]] ??= $value;
                    }
                }
            }
        }

        return $values;
    }

    /** `--yw-radius-md: 1rem` on a scale whose 1 is `0.5rem` means 2. */
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

    /** The base type size as the slider takes it: whole pixels. */
    private function pixels(string $size): string
    {
        $size = trim($size);
        if (preg_match('/^([0-9.]+)px$/', $size, $match)) {
            return (string)(int)round((float)$match[1]) . 'px';
        }
        if (preg_match('/^([0-9.]+)(rem|em)$/', $size, $match)) {
            return (string)(int)round((float)$match[1] * 16) . 'px';
        }
        if (preg_match('/^([0-9.]+)pt$/', $size, $match)) {
            return (string)(int)round((float)$match[1] * 4 / 3) . 'px';
        }

        return $size;
    }
}

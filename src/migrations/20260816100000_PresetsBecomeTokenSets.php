<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\PresetService;

/**
 * ADR-0020: a Preset's nine variables become `--yw-*` Design tokens.
 *
 * `--primary-color` and the other eight are retired rather than aliased, so a wiki with a
 * preset of its own would wake up wearing core's defaults and no trace of why. This carries
 * the nine values onto the nine tokens they correspond to.
 *
 * **The result is an incomplete Preset, and that is the point.** Nine of forty-nine tokens
 * are known; the other forty are not, and inventing them was the rejected option -- a hover
 * colour quietly taken from somebody else's brand is a thing nobody would ever notice was
 * wrong. So the file says what it knows, the Personnalisation screen flags it as incomplete
 * and names what is missing, and the pages keep rendering meanwhile because core's own
 * tokens are still underneath. The webmaster finishes it when they choose to.
 *
 * Both places an instance can own a preset are covered: `custom/css-presets/` (its own) and
 * `custom/themes/<theme>/presets/` (its overrides of the theme's). `themes/` itself is code
 * -- on a farm it is shared and replaced on upgrade -- and is not touched.
 *
 * Everything in the file that is not the `:root` block is kept verbatim: a preset can carry
 * `@font-face` rules (the old save appended them) and rules of its own, and a migration that
 * dropped them would be deleting a webmaster's work rather than renaming it.
 *
 * Idempotent per file: one that already declares `--yw-primary` is left alone, so running
 * the upgrade twice cannot fold a migrated preset a second time.
 */
class PresetsBecomeTokenSets extends YesWikiMigration
{
    /** The nine variables, and the token each one's value means now. */
    private const MAPPING = [
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
            $rewritten = $this->rewrite($css);
            if (@file_put_contents($path, $rewritten) === false) {
                throw new RuntimeException("preset $path could not be written: check the permissions on " . dirname($path));
            }
            $missing = count($presets->missingIn($presets->valuesOf($rewritten)));
            $migrated[] = basename($path) . " ($missing tokens left to fill in)";
        }

        if ($migrated !== []) {
            $log->log(
                'migration',
                'presets carried over to the --yw-* design tokens (ADR-0020): ' . implode(', ', $migrated)
                . '. Each is INCOMPLETE until the missing tokens are set -- the wiki renders'
                . ' with core\'s own values for those. Finish them on /admin/preset.'
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
     * A file to rewrite is one that speaks the old vocabulary and not the new one.
     *
     * Both halves matter: a preset already written in tokens must not be touched, and a file
     * under `custom/css-presets/` that is not a preset at all -- somebody's stylesheet parked
     * there -- has nothing here to rename and is left as it is.
     */
    private function needsMigration(string $css): bool
    {
        if (preg_match('/--yw-[a-z0-9-]+\s*:/i', $css)) {
            return false;
        }
        foreach (array_keys(self::MAPPING) as $variable) {
            if (str_contains($css, '--' . $variable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rename the nine declarations in place, and say in the file itself what is missing.
     *
     * In place rather than rebuilt: the declarations keep their order, their formatting and
     * their comments, and anything else the file holds is untouched by construction. A
     * variable with no token -- one somebody added by hand -- is dropped from the `:root`
     * block's meaning but kept in the text, because it may be feeding a rule of their own
     * further down.
     */
    private function rewrite(string $css): string
    {
        foreach (self::MAPPING as $variable => $token) {
            $css = (string)preg_replace(
                '/--' . preg_quote($variable, '/') . '(?![a-z0-9-])/i',
                '--' . $token,
                $css
            );
        }

        $notice = <<<'CSS'
            /* Carried over from the nine `--primary-color`-style variables, which YesWiki
               retired in favour of `--yw-*` Design tokens (ADR-0020).

               THIS PRESET IS INCOMPLETE. Nine of its tokens are known -- the nine that had a
               variable before -- and the rest are not; they were not invented for you,
               because a colour guessed from somebody else's brand is wrong in a way nobody
               notices. Until they are filled in, pages use core's own values for them.

               Personnalisation (/admin/preset) lists what is missing and edits it. */

            CSS;

        return $notice . ltrim($css);
    }
}

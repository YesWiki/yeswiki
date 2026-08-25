<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;

/**
 * Repaints a copied preset's dark top bar with its dark page colour, as its light scheme already does.
 */
class ADarkBarMatchesItsDarkPage extends YesWikiMigration
{
    public function run()
    {
        $touched = [];

        foreach ($this->files() as $path) {
            $css = $this->getService(Storage::class)->read($path);
            if ($css === '') {
                throw new RuntimeException("preset $path could not be read");
            }

            $rewritten = $this->rewrite($css);
            if ($rewritten === $css) {
                continue;
            }
            try {
                $this->getService(Storage::class)->write($path, $rewritten);
            } catch (Throwable) {
                throw new RuntimeException("preset $path could not be written: check the permissions on " . dirname($path));
            }
            $touched[] = basename($path);
        }

        if ($touched !== []) {
            $this->getService(AdministrativeLogService::class)->log(
                'migration',
                'dark top bar aligned with the dark page in: ' . implode(', ', $touched)
                . '. Presets whose bar has a colour of its own were left untouched.'
            );
        }
    }

    /**
     * Every instance preset on disk.
     *
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

    /** The file with its dark bar repainted, or unchanged when its bar has a colour of its own. */
    private function rewrite(string $css): string
    {
        $light = $this->block($css, '/^:root \{(.*?)^\}/ms');
        $dark = $this->block($css, "/^:root\\[data-theme='dark'\\] \\{(.*?)^\\}/ms");
        if ($light === null || $dark === null) {
            return $css;
        }

        $lightBar = $this->token($light, 'yw-navbar-bg');
        $lightPage = $this->token($light, 'yw-surface');
        $darkBar = $this->token($dark, 'yw-navbar-bg');
        $darkRaised = $this->token($dark, 'yw-surface-raised');
        $darkPage = $this->token($dark, 'yw-surface');

        if ($lightBar === null || $lightPage === null || $lightBar !== $lightPage) {
            return $css;
        }
        if ($darkBar === null || $darkRaised === null || $darkPage === null || $darkBar !== $darkRaised) {
            return $css;
        }
        if ($darkBar === $darkPage) {
            return $css;
        }

        return (string)str_replace(
            '--yw-navbar-bg: ' . $darkBar . ';',
            '--yw-navbar-bg: ' . $darkPage . ';',
            $css
        );
    }

    /** The body of the first block matching $pattern, or null. */
    private function block(string $css, string $pattern): ?string
    {
        return preg_match($pattern, $css, $match) === 1 ? $match[1] : null;
    }

    /** The value of one custom property declared in $block, or null. */
    private function token(string $block, string $name): ?string
    {
        return preg_match('/--' . preg_quote($name, '/') . ':\s*([^;]+);/', $block, $match) === 1
            ? trim($match[1])
            : null;
    }
}

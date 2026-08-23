<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** A theme this page asked for and could not have. */
class ThemeResolutionError implements RequestScopedState
{
    /** @var array{theme: string, style: string, squelette: string}|null */
    private ?array $missingTheme = null;

    /** Record that the theme this page names is not installed. */
    public function themeNotFound(string $theme, string $style, string $squelette): void
    {
        $this->missingTheme = ['theme' => $theme, 'style' => $style, 'squelette' => $squelette];
    }

    /**
     * The theme that was missing, or null when the page rendered in the theme it asked for.
     *
     * @return array{theme: string, style: string, squelette: string}|null
     */
    public function takeMissingTheme(): ?array
    {
        $missing = $this->missingTheme;
        $this->missingTheme = null;

        return $missing;
    }

    public function startNewRequest(): void
    {
        $this->missingTheme = null;
    }
}

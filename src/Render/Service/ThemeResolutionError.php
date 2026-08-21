<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * A theme this page asked for and could not have.
 *
 * `ThemeManager` discovers it while resolving which theme to render in, and the page's own
 * handler is what tells the reader, several layers later. Nothing in between wants to know, which
 * is why it travelled as `$GLOBALS['template-error']` rather than as a return value.
 *
 * Under worker mode (ADR-0024) a global here means the next visitor sees a warning about a theme
 * that has nothing to do with them, or that has since been fixed, until something happens to
 * overwrite it. Built per request, this cannot outlive the page it is about.
 */
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
     * Reading it clears it: the message is shown once, to the reader it concerns.
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

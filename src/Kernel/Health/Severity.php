<?php

namespace YesWiki\Kernel\Health;

/**
 * How bad a failing check is, in two states rather than pass/fail (ADR-0026).
 *
 * Missing `ext-gd` is Broken. Missing `ext-intl` is Degraded, and here is what you lose. Only
 * Broken raises a badge; Degraded waits on the screen with its explanation, because a badge a
 * webmaster keeps seeing for something that is working teaches them to ignore every badge.
 */
enum Severity: string
{
    case Broken = 'broken';
    case Degraded = 'degraded';
}

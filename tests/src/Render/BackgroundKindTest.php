<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;
use YesWiki\Render\Service\ThemeManager;

/** A background photo covers the page whatever its format, a pattern is tiled, and the rest is no background (end-users 04). */
class BackgroundKindTest extends TestCase
{
    public function testTheExtensionDecidesTheLayout(): void
    {
        foreach (['plage.jpg', 'foret.jpeg', 'ciel.webp', 'mer.avif', 'PLAGE.JPG', 'Foret.WebP'] as $photo) {
            $this->assertSame('cover', ThemeManager::backgroundKind($photo), $photo);
        }
        foreach (['motif.png', 'trame.gif', 'MOTIF.PNG'] as $pattern) {
            $this->assertSame('tile', ThemeManager::backgroundKind($pattern), $pattern);
        }
        foreach (['notes.txt', 'sans-extension', 'archive.jpg.zip', '.htaccess'] as $other) {
            $this->assertNull(ThemeManager::backgroundKind($other), $other);
        }
    }
}

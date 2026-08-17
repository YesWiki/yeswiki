<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;

/**
 * Every `icon('…')` a template asks for is a symbol the sprite actually has.
 *
 * **This fails silently, which is the whole reason to test it.** `icon()` renders
 * `<use href="…icons.svg#name">`; a name the sprite does not carry renders *nothing* -- no
 * error, no console warning, no fallback glyph, just a button with no icon in it. Two of
 * these shipped in one afternoon (`layer-group` and `paint-brush`) and neither was visible
 * in any test, because every assertion about those buttons was about their `data-` attribute
 * or their label.
 *
 * The trap is a real one and will recur: `src/icon-map.json` maps the OLD FontAwesome names
 * onto Tabler's, so `layer-group` and `paint-brush` are perfectly good keys in that file --
 * they are simply not what the sprite is keyed by. `icon()` takes the sprite's id (Tabler's,
 * e.g. `stack-2`, `brush`); `iconFromLegacy()` is the one that takes the old name.
 */
class IconNamesResolveTest extends TestCase
{
    /** @return list<string> */
    private function spriteSymbols(): array
    {
        $sprite = (string)file_get_contents(YESWIKI_SOURCE_DIR . '/src/assets/icons.svg');
        preg_match_all('/symbol id="([a-z0-9-]+)"/', $sprite, $matches);

        return $matches[1];
    }

    /** @return list<array{0: string, 1: string}> file => icon name */
    private function iconCalls(): array
    {
        $calls = [];
        foreach (['templates', 'themes', 'extensions'] as $directory) {
            $path = YESWIKI_SOURCE_DIR . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'twig') {
                    continue;
                }
                $source = (string)file_get_contents($file->getPathname());
                if (preg_match_all("/icon\(\s*'([a-z0-9-]+)'/", $source, $matches)) {
                    foreach ($matches[1] as $name) {
                        $calls[] = [$file->getPathname(), $name];
                    }
                }
            }
        }

        return $calls;
    }

    public function testEveryIconATemplateAsksForIsInTheSprite(): void
    {
        $symbols = $this->spriteSymbols();
        $this->assertNotEmpty($symbols, 'the sprite itself must be built');

        $calls = $this->iconCalls();
        $this->assertNotEmpty($calls, 'templates do call icon() -- if not, this test proves nothing');

        $missing = [];
        foreach ($calls as [$file, $name]) {
            if (!in_array($name, $symbols, true)) {
                $missing[] = basename($file) . ": '" . $name . "'";
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            "these render as an empty <use> and show nothing at all.\n"
            . 'icon() takes the SPRITE id (Tabler); the old FontAwesome name goes to iconFromLegacy().'
        );
    }
}

<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;

/** Every `icon('…')` a template asks for is a symbol the sprite actually has. */
class IconNamesResolveTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function spriteSymbols(): array
    {
        $sprite = (string)file_get_contents(YESWIKI_PROGRAM_DIR . '/src/assets/icons.svg');
        preg_match_all('/symbol id="([a-z0-9-]+)"/', $sprite, $matches);

        return $matches[1];
    }

    /**
     * @return list<array{0: string, 1: string}> file => icon name
     */
    private function iconCalls(): array
    {
        $calls = [];
        foreach (['templates', 'themes', 'extensions'] as $directory) {
            $path = YESWIKI_PROGRAM_DIR . '/' . $directory;
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

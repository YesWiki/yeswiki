<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\DoryphoreLeftovers;

class DoryphoreLeftoversTest extends TestCase
{
    private string $instance;

    protected function setUp(): void
    {
        $this->instance = sys_get_temp_dir() . '/doryphore-leftovers-' . bin2hex(random_bytes(4));
        mkdir($this->instance . '/custom/fields', 0755, true);
        mkdir($this->instance . '/tools/bazar', 0755, true);
        mkdir($this->instance . '/tools/lms', 0755, true);
        file_put_contents($this->instance . '/custom/fields/VideoField.php', '<?php class VideoField extends Missing {}');
        file_put_contents($this->instance . '/tools/README.md', 'tools');
        mkdir($this->instance . '/custom/css-presets', 0755, true);
        mkdir($this->instance . '/custom/fonts/asap', 0755, true);
        mkdir($this->instance . '/custom/themes/margot/presets', 0755, true);
        mkdir($this->instance . '/custom/themes/margot/squelettes', 0755, true);
        file_put_contents($this->instance . '/custom/css-presets/R10.css', ':root { --primary-color: #54368d; }');
        file_put_contents($this->instance . '/custom/css-presets/notes.php', '<?php echo 1;');
        file_put_contents($this->instance . '/custom/fonts/asap/asap-normal-400-latin.woff2', 'woff2');
        file_put_contents($this->instance . '/custom/themes/margot/presets/house.css', ':root {}');
        file_put_contents($this->instance . '/custom/themes/margot/squelettes/1col.tpl.html', '<html>');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->instance));
    }

    private function configure(string $version, string $arrayName = 'wakkaConfig'): string
    {
        $file = $this->instance . '/wakka.config.php';
        file_put_contents($file, "<?php\n\${$arrayName} = " . var_export(['yeswiki_version' => $version], true) . ";\n");

        return $file;
    }

    public function testADoryphoreWikiHasItsCustomAndToolsMovedIntoPrivate(): void
    {
        $moved = DoryphoreLeftovers::setAside($this->instance, $this->configure('doryphore'));

        $this->assertDirectoryDoesNotExist($this->instance . '/custom/fields');
        $this->assertDirectoryDoesNotExist($this->instance . '/tools');
        $this->assertFileExists($this->instance . '/private/doryphore/custom/fields/VideoField.php');
        $this->assertDirectoryExists($this->instance . '/private/doryphore/tools/lms');
        $this->assertSame([
            'custom/',
            'custom/css-presets/ (a look, not code: copied back into custom/)',
            'custom/fonts/ (a look, not code: copied back into custom/)',
            'custom/themes/margot/presets/ (a look, not code: copied back into custom/)',
            'tools/bazar/ (Doryphore core, nothing to port)',
            'tools/lms/ (extension, to port)',
        ], $moved);
        $this->assertStringContainsString('tools/lms/ (extension, to port)', (string)file_get_contents($this->instance . '/private/doryphore/README.md'));
    }

    public function testItsPresetsFontsAndThemePresetsStayInCustomAndNoCodeDoes(): void
    {
        DoryphoreLeftovers::setAside($this->instance, $this->configure('doryphore'));

        $this->assertFileExists($this->instance . '/custom/css-presets/R10.css');
        $this->assertFileExists($this->instance . '/custom/fonts/asap/asap-normal-400-latin.woff2');
        $this->assertFileExists($this->instance . '/custom/themes/margot/presets/house.css');
        $this->assertFileDoesNotExist($this->instance . '/custom/css-presets/notes.php');
        $this->assertDirectoryDoesNotExist($this->instance . '/custom/themes/margot/squelettes');
        $this->assertFileExists($this->instance . '/private/doryphore/custom/css-presets/R10.css', 'the aside keeps the originals');
        $this->assertFileExists($this->instance . '/private/doryphore/custom/css-presets/notes.php');
        $this->assertStringContainsString('custom/fonts/ (a look, not code', (string)file_get_contents($this->instance . '/private/doryphore/README.md'));
    }

    public function testTheLookOfASetAsideCustomIsListedForARepair(): void
    {
        DoryphoreLeftovers::setAside($this->instance, $this->configure('doryphore'));

        $this->assertSame(
            ['css-presets/R10.css', 'fonts/asap/asap-normal-400-latin.woff2', 'themes/margot/presets/house.css'],
            array_keys(DoryphoreLeftovers::lookIn($this->instance . '/private/doryphore/custom'))
        );
    }

    public function testAnEctoplasmeWikiKeepsItsCustom(): void
    {
        $this->assertSame([], DoryphoreLeftovers::setAside($this->instance, $this->configure('ectoplasme', 'yeswikiConfig')));

        $this->assertDirectoryExists($this->instance . '/custom/fields');
        $this->assertDirectoryDoesNotExist($this->instance . '/private/doryphore');
    }

    public function testTheMoveHappensOnceEvenIfCustomComesBack(): void
    {
        $config = $this->configure('doryphore');
        DoryphoreLeftovers::setAside($this->instance, $config);
        mkdir($this->instance . '/custom/templates', 0755, true);
        unlink($this->instance . '/custom/css-presets/R10.css');

        $this->assertSame([], DoryphoreLeftovers::setAside($this->instance, $config));
        $this->assertDirectoryExists($this->instance . '/custom/templates');
    }

    public function testAWikiWithNoConfigurationIsLeftAlone(): void
    {
        $this->assertSame([], DoryphoreLeftovers::setAside($this->instance, $this->instance . '/yeswiki.config.php'));

        $this->assertDirectoryExists($this->instance . '/custom');
    }
}

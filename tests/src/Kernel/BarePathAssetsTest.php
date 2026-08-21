<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The icon sprite has to exist in a farm instance's own docroot. */
class BarePathAssetsTest extends YesWikiTestCase
{
    private function bootstrapIn(string $instanceDir): string
    {
        $this->getWiki();

        $script = sprintf(
            "<?php\ndefine('YESWIKI_PROGRAM_DIR', %s);\ndefine('YESWIKI_INSTANCE_DIR', %s);\nrequire %s;\necho 'ok';\n",
            var_export(\YESWIKI_PROGRAM_DIR, true),
            var_export($instanceDir, true),
            var_export(\YESWIKI_PROGRAM_DIR . '/src/bootstrap_paths.php', true)
        );
        $file = $instanceDir . '/bootstrap-probe.php';
        file_put_contents($file, $script);
        exec(sprintf('%s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $lines);

        return implode("\n", $lines);
    }

    public function testAnInstanceGetsTheSpriteAndKeepsItCurrent(): void
    {
        $instance = sys_get_temp_dir() . '/yeswiki-bare-assets-' . getmypid();
        if (!is_dir($instance) && !mkdir($instance, 0755, true)) {
            $this->markTestSkipped('could not lay out an instance');
        }

        try {
            $this->assertSame('ok', $this->bootstrapIn($instance));

            $sprite = $instance . '/src/assets/icons.svg';
            $this->assertFileExists($sprite, 'the sprite every icon points at');
            $this->assertSame(
                (string)file_get_contents(\YESWIKI_PROGRAM_DIR . '/src/assets/icons.svg'),
                (string)file_get_contents($sprite)
            );

            file_put_contents($sprite, '<svg><symbol id="stale"/></svg>');
            touch($sprite, filemtime(\YESWIKI_PROGRAM_DIR . '/src/assets/icons.svg') - 60);
            $this->bootstrapIn($instance);

            $this->assertStringNotContainsString(
                'stale',
                (string)file_get_contents($sprite),
                'an older copy is replaced by the source'
            );

            $before = filemtime($sprite);
            $this->bootstrapIn($instance);
            $this->assertSame($before, filemtime($sprite));
        } finally {
            exec('rm -rf ' . escapeshellarg($instance));
        }
    }

    /** A standalone install is its own source: nothing to copy, and nothing to overwrite. */
    public function testAStandaloneInstallIsLeftAlone(): void
    {
        $sprite = \YESWIKI_PROGRAM_DIR . '/src/assets/icons.svg';
        $before = filemtime($sprite);

        $this->assertSame(\YESWIKI_PROGRAM_DIR, \YESWIKI_INSTANCE_DIR, 'this repo is a standalone install');
        $this->assertSame($before, filemtime($sprite));
    }
}

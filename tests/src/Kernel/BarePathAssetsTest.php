<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The icon sprite has to exist in a farm instance's own docroot.
 *
 * Some 250 places -- templates, PHP handlers, and scripts building markup in the browser --
 * write `<use href="src/assets/icons.svg#name">`. That is a bare source path: it resolves
 * against the instance's docroot, which on a farm holds index.php and data folders and no
 * sources at all. It answered only where the vhost sends missing files through index.php,
 * and where it does not, every icon in the interface disappears -- reported from a farm as
 * `/ecto/src/assets/icons.svg` blocked for its MIME type, the webserver's 404 page being
 * offered as an SVG.
 *
 * Publishing it under `cache/assets/` would not help: the scripts write that path in the
 * browser, long after any URL could be substituted. So the instance gets the file.
 *
 * Run as a subprocess: the two path constants are defined once per process, and this is
 * about what happens when they differ.
 */
class BarePathAssetsTest extends YesWikiTestCase
{
    private function bootstrapIn(string $instanceDir): string
    {
        $this->getWiki(); // for the path constants

        $script = sprintf(
            "<?php\ndefine('YESWIKI_SOURCE_DIR', %s);\ndefine('YESWIKI_INSTANCE_DIR', %s);\nrequire %s;\necho 'ok';\n",
            var_export(\YESWIKI_SOURCE_DIR, true),
            var_export($instanceDir, true),
            var_export(\YESWIKI_SOURCE_DIR . '/src/bootstrap_paths.php', true)
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
                (string)file_get_contents(\YESWIKI_SOURCE_DIR . '/src/assets/icons.svg'),
                (string)file_get_contents($sprite)
            );

            // an updated source must reach the instance: the copy is not a one-off
            file_put_contents($sprite, '<svg><symbol id="stale"/></svg>');
            touch($sprite, filemtime(\YESWIKI_SOURCE_DIR . '/src/assets/icons.svg') - 60);
            $this->bootstrapIn($instance);

            $this->assertStringNotContainsString(
                'stale',
                (string)file_get_contents($sprite),
                'an older copy is replaced by the source'
            );

            // ...and an up-to-date one is left alone rather than recopied on every request
            $before = filemtime($sprite);
            $this->bootstrapIn($instance);
            $this->assertSame($before, filemtime($sprite));
        } finally {
            exec('rm -rf ' . escapeshellarg($instance));
        }
    }

    /**
     * A standalone install is its own source: nothing to copy, and nothing to overwrite.
     */
    public function testAStandaloneInstallIsLeftAlone(): void
    {
        $sprite = \YESWIKI_SOURCE_DIR . '/src/assets/icons.svg';
        $before = filemtime($sprite);

        $this->assertSame(\YESWIKI_SOURCE_DIR, \YESWIKI_INSTANCE_DIR, 'this repo is a standalone install');
        $this->assertSame($before, filemtime($sprite));
    }
}

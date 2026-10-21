<?php

namespace YesWiki\Test\Files;

use PHPUnit\Framework\TestCase;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\ConfigurationService;

/** A file rewritten whole or not at all, which is how the configuration survives a full disk. */
class LocalFilesWriteAtomicallyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/atomic-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->root);
    }

    public function testTheTargetIsReplacedAndKeepsItsPermissions(): void
    {
        $file = $this->root . '/yeswiki.config.php';
        file_put_contents($file, 'before');
        chmod($file, 0o640);

        $this->assertTrue((new LocalFiles())->writeAtomically($file, 'after'));
        clearstatcache();
        $this->assertSame('after', file_get_contents($file));
        $this->assertSame(0o640, fileperms($file) & 0o777);
        $this->assertSame(['yeswiki.config.php'], array_values(array_diff(scandir($this->root) ?: [], ['.', '..'])), 'no temporary file is left behind');
    }

    public function testAFailedWriteLeavesThePreviousFile(): void
    {
        $file = $this->root . '/yeswiki.config.php';
        file_put_contents($file, 'before');
        $files = new class extends LocalFiles {
            public function writeAtomically(string $path, string $contents): bool
            {
                return parent::writeAtomically($path . '/not-a-folder/x', $contents);
            }
        };

        $this->assertFalse($files->writeAtomically($file, 'after'));
        $this->assertSame('before', file_get_contents($file));
    }

    public function testTheConfigurationIsWrittenThroughIt(): void
    {
        $file = $this->root . '/yeswiki.config.php';
        file_put_contents($file, "<?php\n\n\$yeswikiConfig = ['wakka_name' => 'before'];\n");
        $service = new ConfigurationService();
        $config = $service->getConfiguration($file);
        $config->load();
        $config['wakka_name'] = 'after';

        $this->assertTrue($service->write($config));
        $this->assertStringContainsString("'after'", (string)file_get_contents($file));
    }
}

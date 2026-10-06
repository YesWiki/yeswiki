<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\AssetPublisher;

require_once 'src/YesWikiLoader.php';

/** An extension folder may be a link to a checkout elsewhere; its own files are served, nothing it points out of is. */
class SymlinkedExtensionAssetsTest extends TestCase
{
    private string $root;

    private string|false $previousCwd = false;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/symlinked-extension-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/instance/custom/extensions', 0755, true);
        mkdir($this->root . '/checkout/styles', 0755, true);
        file_put_contents($this->root . '/checkout/styles/ext.css', 'a{}');
        file_put_contents($this->root . '/secret.css', 'b{}');
        symlink($this->root . '/secret.css', $this->root . '/checkout/styles/escape.css');
        symlink($this->root . '/checkout', $this->root . '/instance/custom/extensions/linked');
        $this->previousCwd = getcwd();
        chdir($this->root . '/instance');
    }

    protected function tearDown(): void
    {
        if ($this->previousCwd !== false) {
            chdir($this->previousCwd);
        }
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function resolve(string $relPath): ?string
    {
        return (new \ReflectionMethod(AssetPublisher::class, 'resolveSourceFile'))->invoke(null, $relPath);
    }

    public function testAFileOfALinkedExtensionIsFound(): void
    {
        $this->assertSame(realpath($this->root . '/checkout/styles/ext.css'), $this->resolve('custom/extensions/linked/styles/ext.css'));
    }

    public function testALinkInsideTheExtensionCannotReachOutOfIt(): void
    {
        $this->assertNull($this->resolve('custom/extensions/linked/styles/escape.css'));
    }
}

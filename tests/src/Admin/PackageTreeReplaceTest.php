<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\PackageTree;

/** Replacing an installed folder: the new one in place on success, the old one back and a false on failure. */
class PackageTreeReplaceTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yw-package-tree-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/new/sub', 0o755, true);
        mkdir($this->root . '/installed', 0o755, true);
        file_put_contents($this->root . '/new/a.txt', 'new a');
        file_put_contents($this->root . '/new/sub/b.txt', 'new b');
        file_put_contents($this->root . '/installed/old.txt', 'old');
    }

    protected function tearDown(): void
    {
        @chmod($this->root . '/new/sub/b.txt', 0o644);
        (new ExposedPackageTree())->wipe($this->root);
    }

    public function testAFolderIsReplacedWhole(): void
    {
        $this->assertTrue((new ExposedPackageTree())->copyFor($this->root . '/new', $this->root . '/installed'));

        $this->assertSame('new b', file_get_contents($this->root . '/installed/sub/b.txt'));
        $this->assertFileDoesNotExist($this->root . '/installed/old.txt');
        $this->assertSame(['installed', 'new'], array_values(array_diff((array)scandir($this->root), ['.', '..'])));
    }

    public function testAFailedCopyPutsTheOldFolderBackAndSaysSo(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root reads an unreadable file');
        }
        chmod($this->root . '/new/sub/b.txt', 0);

        $tree = new ExposedPackageTree();
        $this->assertFalse($tree->copyFor($this->root . '/new', $this->root . '/installed'));
        $this->assertSame(['COPY', $this->root . '/installed/sub/b.txt'], [$tree->copyFailure()['step'] ?? '', $tree->copyFailure()['path'] ?? '']);

        $this->assertSame('old', file_get_contents($this->root . '/installed/old.txt'));
        $this->assertFileDoesNotExist($this->root . '/installed/a.txt');
        $this->assertSame(['installed', 'new'], array_values(array_diff((array)scandir($this->root), ['.', '..'])));
    }

    /** A package that is not there yet goes into a folder that must exist above it and be writable. */
    public function testAnUnwritableParentIsNamedBeforeAnythingIsCopied(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root writes anywhere');
        }
        $tree = new ExposedPackageTree();
        mkdir($this->root . '/locked');
        chmod($this->root . '/locked', 0o555);

        try {
            $this->assertSame($this->root . '/locked', $tree->parentFor($this->root . '/locked/extensions/ferme/'));
            $this->assertNull($tree->parentFor($this->root . '/installed/extensions/ferme/'));
            $this->assertNull($tree->parentFor($this->root . '/installed'));
        } finally {
            chmod($this->root . '/locked', 0o755);
        }
    }
}

/** PackageTree with its copy and delete reachable from a test. */
class ExposedPackageTree extends PackageTree
{
    public function copyFor(string $src, string $des): bool
    {
        return $this->copy($src, $des);
    }

    public function parentFor(string $path): ?string
    {
        return $this->unwritableParentOf($path);
    }

    public function wipe(string $path): bool
    {
        return $this->delete($path) === true;
    }
}

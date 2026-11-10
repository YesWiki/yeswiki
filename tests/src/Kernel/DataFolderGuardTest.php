<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\DataFolderGuard;

/** Replicas 10: Apache runs no PHP out of cache/, custom/ or files/, and an .htaccess somebody wrote there keeps what it says. */
#[CoversClass(DataFolderGuard::class)]
class DataFolderGuardTest extends TestCase
{
    private string $instance;

    protected function setUp(): void
    {
        $this->instance = sys_get_temp_dir() . '/yw-data-folders-' . bin2hex(random_bytes(4));
        foreach (['cache', 'custom', 'files', 'private'] as $folder) {
            mkdir($this->instance . '/' . $folder, 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->instance);
    }

    public function testAFolderWithoutAnHtaccessGetsTheRules(): void
    {
        DataFolderGuard::provision($this->instance);

        foreach (DataFolderGuard::FOLDERS as $folder) {
            $this->assertSame(DataFolderGuard::block(), file_get_contents("{$this->instance}/{$folder}/.htaccess"));
        }
        $this->assertFileDoesNotExist($this->instance . '/private/.htaccess', 'private/ has its own, stricter rule');
        $this->assertStringContainsString('Require all denied', DataFolderGuard::block());
        $this->assertMatchesRegularExpression('/FilesMatch "[^"]*php/', DataFolderGuard::block());
    }

    public function testProvisioningLeavesAnExistingHtaccessAlone(): void
    {
        file_put_contents($this->instance . '/custom/.htaccess', "Options -Indexes\n");

        DataFolderGuard::provision($this->instance);

        $this->assertSame("Options -Indexes\n", file_get_contents($this->instance . '/custom/.htaccess'), 'the request path never rewrites a file somebody wrote');
    }

    public function testGuardingAddsTheRulesBelowWhatAFileAlreadySays(): void
    {
        file_put_contents($this->instance . '/files/.htaccess', "Options -Indexes\nAddType image/webp .webp\n");

        $written = DataFolderGuard::guard($this->instance);

        $this->assertSame(['cache', 'custom', 'files'], $written);
        $this->assertSame("Options -Indexes\nAddType image/webp .webp\n\n" . DataFolderGuard::block(), file_get_contents($this->instance . '/files/.htaccess'));
    }

    public function testGuardingTwiceWritesOnce(): void
    {
        DataFolderGuard::guard($this->instance);

        $this->assertSame([], DataFolderGuard::guard($this->instance));
        $this->assertSame(1, substr_count((string)file_get_contents($this->instance . '/cache/.htaccess'), DataFolderGuard::BEGIN));
    }

    public function testEmptyingTheCacheKeepsItsHtaccess(): void
    {
        DataFolderGuard::provision($this->instance);
        file_put_contents($this->instance . '/cache/thumbnail.webp', 'x');

        (new CacheClearer())->clearEverything($this->instance);

        $this->assertFileExists($this->instance . '/cache/.htaccess');
        $this->assertFileDoesNotExist($this->instance . '/cache/thumbnail.webp');
    }
}

<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\CacheClearer;

class CacheClearerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yeswiki-cache-clearer-' . uniqid();
        mkdir($this->root . '/cache/container/prod/abc', 0777, true);
        file_put_contents($this->root . '/cache/container/prod/abc/Container.php', '<?php');
        mkdir($this->root . '/cache/templates/7f', 0777, true);
        file_put_contents($this->root . '/cache/templates/7f/x.php', '<?php');
        mkdir($this->root . '/cache/thumbs', 0777, true);
        file_put_contents($this->root . '/cache/thumbs/keep.webp', 'x');
        file_put_contents($this->root . '/cache/.gitkeep', '');
        file_put_contents($this->root . '/cache/maintenance.lock', '');
        file_put_contents($this->root . '/cache/hashcash.key', '123');
    }

    protected function tearDown(): void
    {
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->root);
    }

    public function testItEmptiesTheContainerAndTemplateCachesAndNothingElse(): void
    {
        $cleared = (new CacheClearer())->clear(CacheClearer::ALL, $this->root);

        $this->assertSame([CacheClearer::CONTAINER => 1, CacheClearer::TEMPLATES => 1], $cleared);
        $this->assertDirectoryExists($this->root . '/cache/container');
        $this->assertSame([], array_diff(scandir($this->root . '/cache/container'), ['.', '..']));
        $this->assertSame([], array_diff(scandir($this->root . '/cache/templates'), ['.', '..']));
        $this->assertFileExists($this->root . '/cache/thumbs/keep.webp');
    }

    public function testOneCacheCanBeClearedAlone(): void
    {
        $cleared = (new CacheClearer())->clear([CacheClearer::TEMPLATES], $this->root);

        $this->assertSame([CacheClearer::TEMPLATES => 1], $cleared);
        $this->assertFileExists($this->root . '/cache/container/prod/abc/Container.php');
    }

    public function testAMissingCacheDirectoryCountsAsEmpty(): void
    {
        $cleared = (new CacheClearer())->clear(CacheClearer::ALL, $this->root . '/nowhere');

        $this->assertSame([CacheClearer::CONTAINER => 0, CacheClearer::TEMPLATES => 0], $cleared);
    }

    public function testEverythingGoesExceptTheGitkeepAndTheMaintenanceLock(): void
    {
        $count = (new CacheClearer())->clearEverything($this->root);

        $this->assertSame(4, $count);
        $this->assertSame(['.gitkeep', 'maintenance.lock'], array_values(array_diff(scandir($this->root . '/cache'), ['.', '..'])));
    }
}

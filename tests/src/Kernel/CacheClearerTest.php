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
        foreach (['queue-pass.lock', 'queue-schedule.lock', 'queue-spawn.lock'] as $lock) {
            file_put_contents($this->root . '/cache/' . $lock, '');
        }
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

    public function testEverythingGoesExceptTheGitkeepAndTheQueueLocks(): void
    {
        $count = (new CacheClearer())->clearEverything($this->root);

        $this->assertSame(4, $count, 'the retired sweep\'s lock goes with the rest');
        $this->assertSame(['.gitkeep', 'queue-pass.lock', 'queue-schedule.lock', 'queue-spawn.lock'], array_values(array_diff(scandir($this->root . '/cache'), ['.', '..'])));
    }

    /** A remote backup's step holds its lock for a whole download: deleted under it, a second step would take a fresh one and write beside it. */
    public function testALockSomeoneHoldsIsKept(): void
    {
        $held = fopen($this->root . '/cache/remote-backup.lock', 'c');
        $this->assertNotFalse($held);
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));
        file_put_contents($this->root . '/cache/nobody-holds-this.lock', '');

        try {
            (new CacheClearer())->clearEverything($this->root);
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }

        $this->assertFileExists($this->root . '/cache/remote-backup.lock');
        $this->assertFileDoesNotExist($this->root . '/cache/nobody-holds-this.lock', 'a lock nobody holds is a leftover, and goes');
        $this->assertFileDoesNotExist($this->root . '/cache/maintenance.lock');
    }
}

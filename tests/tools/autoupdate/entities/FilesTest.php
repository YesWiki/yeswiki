<?php

namespace YesWiki\Test\AutoUpdate\Entity;

use PHPUnit\Framework\TestCase;
use YesWiki\AutoUpdate\Entity\Files;

require_once 'tools/autoupdate/entities/Files.php';

/**
 * A folder copy either lands whole or leaves the destination as it was, and always
 * says which of the two happened.
 */
class FilesTest extends TestCase
{
    private $base;
    private $src;
    private $des;
    private $probe;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/yeswiki_filestest_' . uniqid();
        $this->src = $this->base . '/src';
        $this->des = $this->base . '/des';

        mkdir($this->src . '/services', 0777, true);
        mkdir($this->des . '/services', 0777, true);
        file_put_contents($this->src . '/Kept.php', 'new');
        file_put_contents($this->src . '/services/Deep.php', 'new deep');
        file_put_contents($this->des . '/Kept.php', 'old');
        file_put_contents($this->des . '/services/Deep.php', 'old deep');
        file_put_contents($this->des . '/Stale.php', 'stale');

        $this->probe = new class() extends Files {
            public function run($src, $des)
            {
                return $this->copy($src, $des);
            }
        };
    }

    protected function tearDown(): void
    {
        @chmod($this->src . '/services', 0755);
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    public function testCopiedFolderReplacesTheDestination()
    {
        $this->assertTrue($this->probe->run($this->src, $this->des));

        $this->assertSame('new', file_get_contents($this->des . '/Kept.php'));
        $this->assertSame('new deep', file_get_contents($this->des . '/services/Deep.php'));
        $this->assertFileDoesNotExist($this->des . '/Stale.php');
    }

    public function testMissingSourceLeavesTheDestinationAlone()
    {
        $this->assertFalse($this->probe->run($this->base . '/absent', $this->des));

        $this->assertSame('old', file_get_contents($this->des . '/Kept.php'));
        $this->assertSame('old deep', file_get_contents($this->des . '/services/Deep.php'));
    }

    public function testAFailedCopyRestoresTheDestinationAndReportsIt()
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('root reads unreadable folders');
        }
        chmod($this->src . '/services', 0000);

        $this->assertFalse($this->probe->run($this->src, $this->des));

        $this->assertSame('old', file_get_contents($this->des . '/Kept.php'));
        $this->assertSame('old deep', file_get_contents($this->des . '/services/Deep.php'));
        $this->assertFileExists($this->des . '/Stale.php');
    }
}

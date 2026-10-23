<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Core\Service\DiskSpace;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversClass(DiskSpace::class)]
class DiskSpaceTest extends YesWikiTestCase
{
    private const GB = 1024 * 1024 * 1024;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    public function testAZfsUserQuotaCapsTheFreeSpaceOfTheDisk()
    {
        $diskSpace = $this->diskSpace("Filesystem   Type\ndurable/home zfs\n", "9663676416\n6328701440\n");

        $this->assertSame(9663676416 - 6328701440, $diskSpace->quotaHeadroom('/home/wiki'));
        $this->assertSame(['df', '--output=source,fstype', '/home/wiki'], $diskSpace->commands[0]);
        $this->assertSame(['zfs', 'get', '-Hp', '-o', 'value', 'userquota@wiki,userused@wiki', 'durable/home'], $diskSpace->commands[1]);
    }

    public function testAUserOverTheirQuotaHasNoRoomLeft()
    {
        $diskSpace = $this->diskSpace("Filesystem Type\ndurable/home zfs\n", "1000\n1200\n");

        $this->assertSame(0, $diskSpace->quotaHeadroom('/home/wiki'));
    }

    public function testNoQuotaMeansTheDiskAlone()
    {
        $this->assertNull($this->diskSpace("Filesystem Type\ndurable/home zfs\n", "0\n6328701440\n")->quotaHeadroom('/home/wiki'), 'a zero quota is no quota');
        $this->assertNull($this->diskSpace("Filesystem Type\n/dev/sda1 ext4\n", null)->quotaHeadroom('/home/wiki'), 'only zfs quotas are read');
        $this->assertNull($this->diskSpace(null, null)->quotaHeadroom('/home/wiki'), 'no df, no quota');
        $this->assertNull($this->diskSpace("Filesystem Type\ndurable/home zfs\n", null)->quotaHeadroom('/home/wiki'), 'zfs refusing to answer is no quota');
    }

    public function testTheFreeSpaceIsTheSmallerOfTheDiskAndTheQuota()
    {
        $diskSpace = $this->diskSpace("Filesystem Type\ndurable/home zfs\n", (9 * self::GB) . "\n" . (8 * self::GB) . "\n");

        $this->assertSame(self::GB, $diskSpace->free(sys_get_temp_dir()));
    }

    public function testSizesReadAsHumansWriteThem()
    {
        $this->assertSame('512 B', DiskSpace::human(512));
        $this->assertSame('2.0 kB', DiskSpace::human(2048));
        $this->assertSame('3.1 GB', DiskSpace::human(3334974976));
    }

    private function diskSpace(?string $df, ?string $zfs): DiskSpace
    {
        return new class($df, $zfs) extends DiskSpace {
            public array $commands = [];

            public function __construct(private ?string $df, private ?string $zfs)
            {
            }

            protected function user(): ?string
            {
                return 'wiki';
            }

            protected function run(array $command): ?string
            {
                $this->commands[] = $command;

                return $command[0] === 'df' ? $this->df : $this->zfs;
            }
        };
    }
}

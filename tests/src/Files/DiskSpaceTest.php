<?php

namespace YesWiki\Test\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YesWiki\Files\Service\DiskSpace;

/** The room left to write: the disk's free space, capped by a ZFS user quota when there is one. */
#[CoversClass(DiskSpace::class)]
class DiskSpaceTest extends TestCase
{
    private const GB = 1024 * 1024 * 1024;

    public function testAZfsUserQuotaCapsTheFreeSpaceOfTheDisk(): void
    {
        $diskSpace = $this->diskSpace("Filesystem   Type\ndurable/home zfs\n", "9663676416\n6328701440\n");

        $this->assertSame(9663676416 - 6328701440, $diskSpace->quotaHeadroom('/home/wiki'));
        $this->assertSame(['df', '--output=source,fstype', '/home/wiki'], $diskSpace->commands[0]);
        $this->assertSame(['zfs', 'get', '-Hp', '-o', 'value', 'userquota@wiki,userused@wiki', 'durable/home'], $diskSpace->commands[1]);
    }

    public function testAUserOverTheirQuotaHasNoRoomLeft(): void
    {
        $this->assertSame(0, $this->diskSpace("Filesystem Type\ndurable/home zfs\n", "1000\n1200\n")->quotaHeadroom('/home/wiki'));
    }

    public function testNoQuotaMeansTheDiskAlone(): void
    {
        $this->assertNull($this->diskSpace("Filesystem Type\ndurable/home zfs\n", "0\n6328701440\n")->quotaHeadroom('/home/wiki'), 'a zero quota is no quota');
        $this->assertNull($this->diskSpace("Filesystem Type\n/dev/sda1 ext4\n", null)->quotaHeadroom('/home/wiki'), 'only zfs quotas are read');
        $this->assertNull($this->diskSpace(null, null)->quotaHeadroom('/home/wiki'), 'no df, no quota');
        $this->assertNull($this->diskSpace("Filesystem Type\ndurable/home zfs\n", null)->quotaHeadroom('/home/wiki'), 'zfs refusing to answer is no quota');
    }

    public function testTheFreeSpaceIsTheSmallerOfTheDiskAndTheQuota(): void
    {
        $diskSpace = $this->diskSpace("Filesystem Type\ndurable/home zfs\n", (9 * self::GB) . "\n" . (8 * self::GB) . "\n");

        $this->assertSame((float)self::GB, $diskSpace->capped(2.0 * self::GB, '/home/wiki'));
        $this->assertSame(512.0, $diskSpace->capped(512.0, '/home/wiki'), 'a fuller disk than the quota is the limit');
        $this->assertSame((float)self::GB, $diskSpace->capped(null, '/home/wiki'), 'a disk that will not say leaves the quota');
    }

    private function diskSpace(?string $df, ?string $zfs): FakeDiskSpace
    {
        return new FakeDiskSpace($df, $zfs);
    }
}

/** DiskSpace answered by canned `df` and `zfs` outputs. */
class FakeDiskSpace extends DiskSpace
{
    /** @var list<list<string>> */
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
}

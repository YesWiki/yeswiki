<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;

/** What a holder lock says, from another process, about whoever took a claim or runs a pass: still there, gone, or nothing to tell. */
#[CoversClass(QueueHolderLock::class)]
class QueueHolderLockTest extends TestCase
{
    private string $dir;

    private QueueHolderLock $lock;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yw-holder-lock-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o777, true);
        $this->lock = new QueueHolderLock(QueueTestKit::locksIn($this->dir));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testAHeldTokenIsHeldFromAnotherProcessesView(): void
    {
        $token = $this->tokenOfThisMachine();
        $path = $this->absolutePathOf($token);

        $this->assertTrue($this->lock->hold($token));
        $this->assertSame('held', $this->seenFromAnotherProcess($path));

        $this->lock->release($token);

        $this->assertFileDoesNotExist($path, 'the file goes with the lock');
    }

    public function testAFreeLockIsFree(): void
    {
        $token = $this->tokenOfThisMachine();

        $ends = QueueTestHolders::lockHeldByAnotherProcess($this->absolutePathOf($token));
        $this->assertSame(0, $ends());

        $this->assertSame(QueueHolderLock::FREE, $this->lock->stateOf($token));
    }

    public function testAHeldLockIsHeld(): void
    {
        $token = $this->tokenOfThisMachine();

        $ends = QueueTestHolders::lockHeldByAnotherProcess($this->absolutePathOf($token));
        try {
            $this->assertSame(QueueHolderLock::HELD, $this->lock->stateOf($token));
        } finally {
            $exit = $ends();
        }

        $this->assertSame(0, $exit);
        $this->assertSame(QueueHolderLock::FREE, $this->lock->stateOf($token));
    }

    public function testAMissingFileIsAbsent(): void
    {
        $this->assertSame(QueueHolderLock::ABSENT, $this->lock->stateOf($this->tokenOfThisMachine()));
    }

    public function testATokenOfAnotherMachineIsAbsentAndNeverForgotten(): void
    {
        $token = QueueTestHolders::elsewhere()->token();
        $path = $this->absolutePathOf($token);
        mkdir(\dirname($path), 0o777, true);
        touch($path);

        $this->assertSame(QueueHolderLock::ABSENT, $this->lock->stateOf($token), 'a file here says nothing about a process there');

        $this->lock->forget($token);

        $this->assertFileExists($path);
    }

    public function testAnUnreadableTokenIsAbsent(): void
    {
        $this->assertSame(QueueHolderLock::ABSENT, $this->lock->stateOf('not-a-token'));
        $this->assertNull(QueueHolderLock::pathOf('not-a-token'));
    }

    public function testTheCacheClearerKeepsTheHolders(): void
    {
        $this->assertContains(QueueHolderLock::DIRECTORY, CacheClearer::KEPT);
    }

    public function testAForgottenLockOfThisMachineIsRemoved(): void
    {
        $token = $this->tokenOfThisMachine();
        $path = $this->absolutePathOf($token);
        mkdir(\dirname($path), 0o777, true);
        touch($path);

        $this->lock->forget($token);

        $this->assertFileDoesNotExist($path);
        $this->assertSame(QueueHolderLock::ABSENT, $this->lock->stateOf($token));
    }

    public function testReleasingATokenNobodyHoldsLeavesItsFile(): void
    {
        $token = $this->tokenOfThisMachine();
        $path = $this->absolutePathOf($token);
        mkdir(\dirname($path), 0o777, true);
        touch($path);

        $this->lock->release($token);

        $this->assertFileExists($path, 'the file of a lock this instance does not hold is not its to remove');
    }

    public function testAHoldFailsWhenSomeoneElseHoldsTheFile(): void
    {
        $token = $this->tokenOfThisMachine();

        $ends = QueueTestHolders::lockHeldByAnotherProcess($this->absolutePathOf($token));
        try {
            $this->assertFalse($this->lock->hold($token));
        } finally {
            $ends();
        }
    }

    public function testAHoldFailsWhenTheFileCannotBeCreated(): void
    {
        file_put_contents($this->dir . '/cache', 'a file where the directory should be');

        $this->assertFalse($this->lock->hold($this->tokenOfThisMachine()));
    }

    public function testATokenWithoutAMachineIsNotHeld(): void
    {
        $token = (new QueueClaimHolder(true, 1, '', ''))->token();

        $this->assertFalse($this->lock->hold($token), 'no judge would read a lock for a process that cannot be told from another');
        $this->assertDirectoryDoesNotExist($this->dir . '/' . QueueHolderLock::DIRECTORY);
    }

    public function testThePathIsTheNonceInTheHoldersDirectory(): void
    {
        $this->assertSame(QueueHolderLock::DIRECTORY . '/00ff12ab.lock', QueueHolderLock::pathOf('c12@abc.34/00ff12ab'));
    }

    /** A token as this process makes them; skips where the machine cannot be told from another, as nothing is held there. */
    private function tokenOfThisMachine(): string
    {
        $holder = QueueClaimHolder::thisProcess();
        if ($holder->machine === '') {
            $this->markTestSkipped('this machine does not show its processes in /proc');
        }

        return $holder->token();
    }

    private function absolutePathOf(string $token): string
    {
        $path = QueueHolderLock::pathOf($token);
        $this->assertNotNull($path);

        return $this->dir . '/' . $path;
    }

    /** What a process other than this one finds when it tries that lock without waiting: 'held' or 'free'. */
    private function seenFromAnotherProcess(string $path): string
    {
        $code = '$h = fopen($argv[1], "r"); echo $h !== false && flock($h, LOCK_EX | LOCK_NB) ? "free" : "held";';

        return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' -- ' . escapeshellarg($path)));
    }
}

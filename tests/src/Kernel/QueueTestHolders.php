<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Assert;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/**
 * Who may hold a claim, for the tests of what the queue decides about it (R19): a process gone from this machine, one still there, one on another machine.
 *
 * A test claims a message the normal way, then hands its claim to one of these, as if that
 * process had taken it.
 */
final class QueueTestHolders
{
    /** A holder on another machine: nothing about its process can be told from here, and the limit it recorded decides. */
    public static function elsewhere(): QueueClaimHolder
    {
        return new QueueClaimHolder(true, 4242, 'e15e7e7e7e7e', '1');
    }

    /** A process of this machine that has ended: it ran, and exited before this returned. */
    public static function gone(): QueueClaimHolder
    {
        self::needProc();
        $pid = (int)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo getmypid();'));
        Assert::assertGreaterThan(0, $pid);

        return new QueueClaimHolder(true, $pid, QueueClaimHolder::thisProcess()->machine, '1');
    }

    /**
     * A process of this machine that outlives the test (the one that started the suite), as the CLI would hold a claim, or as a server's process would.
     *
     * @param bool $ownProcess whether it runs nothing but its consumer (CLI), or serves requests (FPM, Apache, FrankenPHP)
     */
    public static function living(bool $ownProcess): QueueClaimHolder
    {
        self::needProc();
        $pid = posix_getppid();
        $stat = (string)file_get_contents("/proc/{$pid}/stat");
        $fields = explode(' ', trim(substr($stat, (int)strrpos($stat, ')') + 2)));

        return new QueueClaimHolder($ownProcess, $pid, QueueClaimHolder::thisProcess()->machine, $fields[19]);
    }

    /**
     * Another process takes the exclusive lock on that file and keeps it until the closure returns; it answers once the lock is taken.
     *
     * @return \Closure(): int lets the other process go, waits for it to end, and answers its exit code (0 when it held the lock all along)
     */
    public static function lockHeldByAnotherProcess(string $absolutePath): \Closure
    {
        if (!is_dir(\dirname($absolutePath))) {
            mkdir(\dirname($absolutePath), 0o777, true);
        }
        $code = '$h = fopen($argv[1], "c"); if ($h === false || !flock($h, LOCK_EX | LOCK_NB)) { exit(2); } fwrite(STDOUT, "ok\n"); fflush(STDOUT); fgets(STDIN); exit(0);';
        $process = proc_open([PHP_BINARY, '-r', $code, '--', $absolutePath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        Assert::assertIsResource($process, 'the other process could not be started');
        stream_set_timeout($pipes[1], 10);
        $answer = trim((string)fgets($pipes[1]));
        if ($answer !== 'ok') {
            fclose($pipes[0]);
            $error = (string)stream_get_contents($pipes[2]);
            proc_terminate($process);
            proc_close($process);
            Assert::fail("the other process did not take the lock: '{$answer}' {$error}");
        }

        return static function () use ($process, $pipes): int {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return proc_close($process);
        };
    }

    /** Whether that file's lock is held, free or absent, as a process other than this one finds it the way a judge does (QueueHolderLock's HELD, FREE, ABSENT). */
    public static function seenFromAnotherProcess(string $absolutePath): string
    {
        $code = '$h = @fopen($argv[1], "r"); if ($h === false) { echo "absent"; exit; } echo flock($h, LOCK_SH | LOCK_NB) ? "free" : "held";';

        return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' -- ' . escapeshellarg($absolutePath)));
    }

    /** Hands the claim on that message to that holder, as if its process had taken it; answers the token it now carries. */
    public static function hand(DbService $db, QueueSchema $schema, int $messageId, QueueClaimHolder $holder): string
    {
        $token = $holder->token();
        $db->query("UPDATE {$schema->table()} SET delivered_by = ? WHERE id = ?", [$token, $messageId]);

        return $token;
    }

    /** Skips the test where this machine does not show its processes in /proc: nothing can be told there, and the time rule is all there is. */
    private static function needProc(): void
    {
        if (QueueClaimHolder::thisProcess()->machine === '' || !function_exists('posix_getppid')) {
            Assert::markTestSkipped('this machine does not show its processes in /proc');
        }
    }
}

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

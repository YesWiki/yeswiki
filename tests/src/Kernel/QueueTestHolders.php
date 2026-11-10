<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Lock\DatabaseLocks;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/**
 * Who may hold a claim, for the tests of what the queue decides about it: a holder whose file is held, one whose file is free, one with no file.
 *
 * A test claims a message the normal way, then hands its claim to one of these tokens, as if
 * that holder had taken it.
 */
final class QueueTestHolders
{
    /** A token for a pass of this process, for a test that takes messages as a pass would and gives it to the transport with workFor(). */
    public static function passToken(): string
    {
        return QueueHolderLock::newToken();
    }

    /** A token with no file: nothing tells whether its holder is there, and the limit it recorded decides. */
    public static function token(): string
    {
        return QueueHolderLock::newToken();
    }

    /** A token whose file, in that instance directory, nobody holds: its holder is gone. */
    public static function gone(string $instanceDir): string
    {
        $token = QueueHolderLock::newToken();
        $file = $instanceDir . '/' . QueueHolderLock::pathOf($token);
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }
        touch($file);

        return $token;
    }

    /**
     * Another process takes the exclusive lock on that file and keeps it until the closure returns; it answers once the lock is taken.
     *
     * @return \Closure(): int lets the other process go, waits for it to end, and answers its exit code (0 when it held the lock all along)
     */
    public static function lockHeldByAnotherProcess(string $absolutePath): \Closure
    {
        [$process, $pipes] = self::holdInAnotherProcess($absolutePath);

        return static function () use ($process, $pipes): int {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return proc_close($process);
        };
    }

    /**
     * Another holder takes that lock of the wiki and keeps it until the closure returns: a second connection to its server, or another process where locks are files.
     *
     * @return \Closure(): int lets it go and answers 0
     */
    public static function wikiLockHeldElsewhere(ParameterBagInterface $params, string $name): \Closure
    {
        if ($params->get('db_driver') === 'sqlite') {
            return self::lockHeldByAnotherProcess(YESWIKI_INSTANCE_DIR . '/' . FileLocks::pathOf($name));
        }
        $locks = new DatabaseLocks(new DbService($params), $params);
        Assert::assertTrue($locks->tryAcquire($name), "another connection could not take the lock {$name}");

        return static function () use ($locks, $name): int {
            $locks->release($name);

            return 0;
        };
    }

    /**
     * The same, until the closure kills that process (SIGKILL: none of its code runs after, as when the host or the OOM killer ends a run).
     *
     * @return \Closure(): void kills the other process and waits for it to be gone
     */
    public static function lockHeldByAProcessToKill(string $absolutePath): \Closure
    {
        [$process, $pipes] = self::holdInAnotherProcess($absolutePath);

        return static function () use ($process, $pipes): void {
            proc_terminate($process, 9);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        };
    }

    /** @return array{resource, array<int, resource>} the process that holds the lock on that file, and its pipes */
    private static function holdInAnotherProcess(string $absolutePath): array
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

        return [$process, $pipes];
    }

    /** Whether that file's lock is held, free or absent, as a process other than this one finds it the way a judge does (QueueHolderLock's HELD, FREE, ABSENT). */
    public static function seenFromAnotherProcess(string $absolutePath): string
    {
        $code = '$h = @fopen($argv[1], "r"); if ($h === false) { echo "absent"; exit; } echo flock($h, LOCK_SH | LOCK_NB) ? "free" : "held";';

        return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' -- ' . escapeshellarg($absolutePath)));
    }

    /** Hands the claim on that message to that token, as if its holder had taken it; answers the token. */
    public static function hand(DbService $db, QueueSchema $schema, int $messageId, string $token): string
    {
        $db->query("UPDATE {$schema->table()} SET delivered_by = ? WHERE id = ?", [$token, $messageId]);

        return $token;
    }
}

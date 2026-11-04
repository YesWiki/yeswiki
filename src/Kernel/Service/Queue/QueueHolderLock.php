<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueClaimHolder;

/**
 * Tells, on one machine, whether whoever took a claim (or runs a pass) is still there: the process holds a lock on a file named after its token, and the kernel drops that lock when the process ends, however it ends.
 *
 * The token's pid says who to look for, but only /proc can say whether that pid is the same process; the lock says it for a process /proc cannot tell from another (a pid shared by two containers), and for a server's process that is alive and working.
 */
final class QueueHolderLock
{
    public const DIRECTORY = 'cache/queue-holders';

    public const HELD = 'held';
    public const FREE = 'free';
    public const ABSENT = 'absent';

    /** @var array<string, resource> the handles this process holds, by token */
    private array $handles = [];

    public function __construct(private readonly RuntimeLock $locks)
    {
    }

    /**
     * Creates the token's file and locks it, keeping the handle until release(); it refuses, without creating a file, a token that is not of this machine, since only this machine's tokens are ever judged by their file.
     *
     * @return bool false when the token is not of this machine, or when the file cannot be created or locked
     */
    public function hold(string $token): bool
    {
        $path = self::pathOf($token);
        if ($path === null || !self::isOfThisMachine($token)) {
            return false;
        }
        if (isset($this->handles[$token])) {
            return true;
        }

        $handle = $this->locks->acquire($path);
        if ($handle === null) {
            return false;
        }
        if (!$this->locks->tryLock($handle)) {
            $this->locks->release($handle);

            return false;
        }
        $this->handles[$token] = $handle;

        return true;
    }

    /** Removes the file and then closes the handle, so that nobody finds the file free before it is gone; nothing happens for a token this instance does not hold. */
    public function release(string $token): void
    {
        $handle = $this->handles[$token] ?? null;
        if ($handle === null) {
            return;
        }
        unset($this->handles[$token]);

        $path = self::pathOf($token);
        if ($path !== null) {
            $this->locks->forget($path);
        }
        $this->locks->release($handle);
    }

    /** HELD while the holder keeps its lock, FREE once it let go or ended, ABSENT when there is no file or the token is not of this machine; asked without creating or removing anything. */
    public function stateOf(string $token): string
    {
        $path = self::pathOf($token);
        if ($path === null || !self::isOfThisMachine($token)) {
            return self::ABSENT;
        }

        return match ($this->locks->probe($path)) {
            null => self::ABSENT,
            true => self::HELD,
            false => self::FREE,
        };
    }

    /** Removes the file of a holder that is gone, for a token of this machine only: another machine's file is none of ours to touch. */
    public function forget(string $token): void
    {
        $path = self::pathOf($token);
        if ($path !== null && self::isOfThisMachine($token)) {
            $this->locks->forget($path);
        }
    }

    /** The file of that token, relative to the instance; null for a token with no hexadecimal nonce. */
    public static function pathOf(string $token): ?string
    {
        return preg_match('~/([0-9a-f]+)$~', $token, $parts) === 1 ? self::DIRECTORY . '/' . $parts[1] . '.lock' : null;
    }

    private static function isOfThisMachine(string $token): bool
    {
        $machine = QueueClaimHolder::ofToken($token)?->machine;

        return $machine !== null && $machine !== '' && $machine === QueueClaimHolder::thisProcess()->machine;
    }
}

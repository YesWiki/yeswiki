<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Files\Service\RuntimeLock;

/**
 * Tells whether whoever runs a pass is still there: the pass holds a lock on a file named after its token, and the kernel drops that lock when the process ends, however it ends.
 *
 * One host is assumed (a shared host or a single container): every process that reads `cache/` shares one kernel, so a lock it finds held or free is the truth.
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

    /** A token for a pass: the pid, for whoever reads it, and a nonce that names its file. */
    public static function newToken(): string
    {
        return getmypid() . '/' . bin2hex(random_bytes(8));
    }

    /**
     * Creates the token's file and locks it, keeping the handle until release().
     *
     * @return bool false when the file cannot be created or locked
     */
    public function hold(string $token): bool
    {
        $path = self::pathOf($token);
        if ($path === null) {
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

    /** HELD while the holder keeps its lock, FREE once it let go or ended, ABSENT when there is no file; asked without creating or removing anything. */
    public function stateOf(string $token): string
    {
        $path = self::pathOf($token);
        if ($path === null) {
            return self::ABSENT;
        }

        return match ($this->locks->probe($path)) {
            null => self::ABSENT,
            true => self::HELD,
            false => self::FREE,
        };
    }

    /** Removes the file of a holder that is gone. */
    public function forget(string $token): void
    {
        $path = self::pathOf($token);
        if ($path !== null) {
            $this->locks->forget($path);
        }
    }

    /** The file of that token, relative to the instance; null for a token with no hexadecimal nonce. */
    public static function pathOf(string $token): ?string
    {
        return preg_match('~/([0-9a-f]+)$~', $token, $parts) === 1 ? self::DIRECTORY . '/' . $parts[1] . '.lock' : null;
    }
}

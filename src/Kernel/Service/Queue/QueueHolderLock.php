<?php

namespace YesWiki\Kernel\Service\Queue;

use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Lock\Locks;

/** Tells whether whoever runs a pass is still there: the pass holds a lock named after its token, which goes when its process ends, however it ends. */
final class QueueHolderLock
{
    public const DIRECTORY = 'cache/queue-holders';

    public const HELD = 'held';
    public const FREE = 'free';
    public const ABSENT = 'absent';

    /** @var array<string, string> the lock names this process holds, by token */
    private array $held = [];

    public function __construct(private readonly Locks $locks)
    {
    }

    /** A token for a pass: the pid, for whoever reads it, and a nonce that names its lock. */
    public static function newToken(): string
    {
        return getmypid() . '/' . bin2hex(random_bytes(8));
    }

    /** Takes the token's lock and keeps it until release(); false when it cannot be taken. */
    public function hold(string $token): bool
    {
        $name = self::nameOf($token);
        if ($name === null) {
            return false;
        }
        if (isset($this->held[$token])) {
            return true;
        }
        if (!$this->locks->tryAcquire($name)) {
            return false;
        }
        $this->held[$token] = $name;

        return true;
    }

    /** Forgets what the lock leaves behind and then lets it go, so that nobody finds it free before it is forgotten; nothing happens for a token this instance does not hold. */
    public function release(string $token): void
    {
        $name = $this->held[$token] ?? null;
        if ($name === null) {
            return;
        }
        unset($this->held[$token]);
        $this->locks->forget($name);
        $this->locks->release($name);
    }

    /** HELD while the holder keeps its lock, FREE once it let go or ended, ABSENT when nothing can tell; asked without creating or removing anything. */
    public function stateOf(string $token): string
    {
        $name = self::nameOf($token);
        if ($name === null) {
            return self::ABSENT;
        }

        return match ($this->locks->probe($name)) {
            null => self::ABSENT,
            true => self::HELD,
            false => self::FREE,
        };
    }

    /** Removes what a holder that is gone left behind. */
    public function forget(string $token): void
    {
        $name = self::nameOf($token);
        if ($name !== null) {
            $this->locks->forget($name);
        }
    }

    /** The lock of that token; null for a token with no hexadecimal nonce. */
    public static function nameOf(string $token): ?string
    {
        return preg_match('~/([0-9a-f]+)$~', $token, $parts) === 1 ? 'queue-holders/' . $parts[1] : null;
    }

    /** The file that stands for that token's lock where locks are files, relative to the instance. */
    public static function pathOf(string $token): ?string
    {
        $name = self::nameOf($token);

        return $name === null ? null : FileLocks::pathOf($name);
    }
}

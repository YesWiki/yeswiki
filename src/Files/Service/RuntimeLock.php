<?php

namespace YesWiki\Files\Service;

/**
 * The locks a wiki takes against itself, which are the one thing `Storage` deliberately cannot do.
 *
 * ADR-0022 names this among the reasons Runtime is a tier that refuses object storage: "the
 * maintenance and reindex locks need atomic create. S3 offers none of that." Flysystem has no
 * `O_CREAT|O_EXCL` and no advisory locking, so a lock taken through `Storage::write()` would be a
 * lock two processes could both believe they hold.
 *
 * So the primitives live here, on the local filesystem, addressed by the same instance-relative
 * paths everything else uses. Every lock in the wiki goes through this rather than each one
 * open-coding `fopen`, `flock` and `touch` with its own idea of what a failure means.
 */
class RuntimeLock
{
    private string $root;

    public function __construct()
    {
        $this->root = \defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd();
    }

    /** When this lock was last taken, or 0 if it never was. */
    public function lastTaken(string $path): int
    {
        return (int)@filemtime($this->absolute($path));
    }

    /** Stamp a lock with a time of the caller's clock, creating it and its directory if needed. */
    public function stampAt(string $path, int $time): void
    {
        $full = $this->absolute($path);
        $directory = \dirname($full);
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }
        @touch($full, $time);
        clearstatcache(true, $full);
    }

    /** Remove a stamp, so that lastTaken() answers 0 again; nothing happens when there is none. */
    public function forget(string $path): void
    {
        $full = $this->absolute($path);
        @unlink($full);
        clearstatcache(true, $full);
    }

    /**
     * Take an exclusive lock without waiting, and answer a handle to hold it with.
     *
     * `'c'` rather than `'w'`: it creates without truncating, so a process that is already holding
     * this lock does not have its file emptied underneath it before `flock` refuses.
     *
     * @return resource|null the handle to keep open while the lock is held, or null when it could
     *                       not be created; false-ish is deliberately not used, because "could not
     *                       open" and "somebody else holds it" are different answers
     */
    public function acquire(string $path)
    {
        $full = $this->absolute($path);
        $directory = \dirname($full);
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }

        $handle = @fopen($full, 'c');

        return $handle === false ? null : $handle;
    }

    /** Whether the handle took the lock. Releasing it is closing the handle. */
    /** @param resource|null $handle */
    public function tryLock($handle): bool
    {
        return \is_resource($handle) && flock($handle, LOCK_EX | LOCK_NB);
    }

    /**
     * Whether some handle holds the lock now, asked without creating the file.
     *
     * A shared lock, not an exclusive one: it still conflicts with the holder's exclusive lock, two
     * probes at once do not take each other for the holder, and it works on a read handle where an
     * exclusive one would not (NFS, whose client emulates flock with fcntl, wants a write handle for that).
     *
     * Only a lock refused because another handle holds it is "held": a filesystem that cannot lock at all
     * (an NFS mount without its lock daemon, some FUSE or SMB mounts) answers null, so that nothing is
     * taken for alive on a lock that was never there.
     *
     * @return bool|null null when the file cannot be opened (there is none, or it may not be read) or cannot be locked at all, true when another handle holds it, false when it is free
     */
    public function probe(string $path): ?bool
    {
        $handle = @fopen($this->absolute($path), 'r');
        if ($handle === false) {
            return null;
        }
        $wouldBlock = 0;
        $free = flock($handle, LOCK_SH | LOCK_NB, $wouldBlock);
        $this->release($handle);

        return match (true) {
            $free => false,
            $wouldBlock === 1 => true,
            default => null,
        };
    }

    /** Whether some handle holds the lock now, asked without creating the file. */
    public function isHeld(string $path): bool
    {
        return $this->probe($path) === true;
    }

    /** @param resource|null $handle */
    public function release($handle): void
    {
        if (\is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->root . '/' . ltrim($path, '/');
    }
}

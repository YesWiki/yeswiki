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

    /** Stamp a lock with the current time, creating it and its directory if needed. */
    public function stamp(string $path): void
    {
        $full = $this->absolute($path);
        $directory = \dirname($full);
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }
        @touch($full);
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

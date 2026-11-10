<?php

namespace YesWiki\Kernel\Service\Lock;

use YesWiki\Files\Service\RuntimeLock;

/** Locks as `flock` on `cache/<name>.lock`: they exclude processes of one machine only, which is all SQLite has (ADR-0031). */
final class FileLocks implements Locks
{
    public const DIRECTORY = 'cache/';

    public const EXTENSION = '.lock';

    /** @var array<string, resource> */
    private array $handles = [];

    public function __construct(private readonly RuntimeLock $files = new RuntimeLock())
    {
    }

    /** The file that stands for a lock, relative to the instance. */
    public static function pathOf(string $name): string
    {
        return self::DIRECTORY . $name . self::EXTENSION;
    }

    public function tryAcquire(string $name, bool &$heldElsewhere = false): bool
    {
        $heldElsewhere = false;
        if (isset($this->handles[$name])) {
            $heldElsewhere = true;

            return false;
        }
        $handle = $this->files->acquire(self::pathOf($name));
        if ($handle === null) {
            return false;
        }
        if (!$this->files->tryLock($handle, $refused)) {
            $heldElsewhere = $refused === true;
            $this->files->release($handle);

            return false;
        }
        $this->handles[$name] = $handle;

        return true;
    }

    public function release(string $name): void
    {
        $handle = $this->handles[$name] ?? null;
        unset($this->handles[$name]);
        $this->files->release($handle);
    }

    public function probe(string $name): ?bool
    {
        return $this->files->probe(self::pathOf($name));
    }

    public function forget(string $name): void
    {
        $this->files->forget(self::pathOf($name));
    }
}

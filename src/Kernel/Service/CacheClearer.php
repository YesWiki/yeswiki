<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\Storage;

/** Empties the caches a code change can leave stale: the compiled container and the compiled templates. */
class CacheClearer
{
    public const CONTAINER = 'cache/container';
    public const TEMPLATES = 'cache/templates';

    /** @var list<string> */
    public const ALL = [self::CONTAINER, self::TEMPLATES];

    /** What `clearEverything()` leaves alone: the placeholder git tracks, and the lock a maintenance run may be holding. */
    public const KEPT = ['.gitkeep', 'maintenance.lock'];

    private ?Storage $storage;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage;
    }

    /**
     * @param list<string> $which which of ALL to empty, every one by default
     * @param string|null  $root  the instance directory, this wiki's by default
     *
     * @return array<string, int> per cache, how many top-level entries went
     */
    public function clear(array $which = self::ALL, ?string $root = null): array
    {
        $storage = $this->storageFor($root);
        $cleared = [];
        foreach ($which as $cache) {
            $cleared[$cache] = $this->empty($storage, $cache, []);
        }

        return $cleared;
    }

    /**
     * Empties `cache/` itself: thumbnails, remote copies, the purifier, the hashcash secret, and the two above. Everything there is rebuilt on demand.
     *
     * @return int how many top-level entries went
     */
    public function clearEverything(?string $root = null): int
    {
        return $this->empty($this->storageFor($root), 'cache', self::KEPT);
    }

    /**
     * @param list<string> $kept
     */
    private function empty(Storage $storage, string $directory, array $kept): int
    {
        if (!$storage->directoryExists($directory)) {
            return 0;
        }
        $count = 0;
        foreach ($storage->listContents($directory) as $entry) {
            if (\in_array(basename($entry->path()), $kept, true)) {
                continue;
            }
            if ($entry->isDir()) {
                $storage->deleteDirectory($entry->path());
            } else {
                $storage->delete($entry->path());
            }
            $count++;
        }

        return $count;
    }

    private function storageFor(?string $root): Storage
    {
        if ($root !== null) {
            return Storage::rootedAt($root);
        }

        return $this->storage ??= new Storage();
    }
}

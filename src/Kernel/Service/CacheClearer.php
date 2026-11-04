<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;

/** Empties the caches a code change can leave stale: the compiled container and the compiled templates. */
class CacheClearer
{
    public const CONTAINER = 'cache/container';
    public const TEMPLATES = 'cache/templates';

    /** Rewritten whenever the compiled templates go, so a worker holding them in memory knows to restart. */
    public const TEMPLATES_STAMP = 'cache/templates.cleared';

    /** @var list<string> */
    public const ALL = [self::CONTAINER, self::TEMPLATES];

    public const KEPT = ['.gitkeep', QueueConsumer::PASS_LOCK, QueueScheduleTicker::LOCK, QueueHolderLock::DIRECTORY];

    private ?Storage $storage;

    private RuntimeLock $locks;

    public function __construct(?Storage $storage = null, ?RuntimeLock $locks = null)
    {
        $this->storage = $storage;
        $this->locks = $locks ?? new RuntimeLock();
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
        if (\in_array(self::TEMPLATES, $which, true)) {
            self::stampTemplatesCleared($storage);
        }

        return $cleared;
    }

    /**
     * Empties `cache/`.
     *
     * @return int how many top-level entries went
     */
    public function clearEverything(?string $root = null): int
    {
        return $this->empty($this->storageFor($root), 'cache', self::KEPT);
    }

    /** Tell every worker that the templates it compiled are no longer the ones on disk. */
    public static function stampTemplatesCleared(Storage $storage): void
    {
        $storage->write(self::TEMPLATES_STAMP, uniqid('', true));
    }

    /**
     * @param list<string> $kept
     */
    private function empty(Storage $storage, string $directory, array $kept): int
    {
        if (!$storage->directoryExists($directory)) {
            return 0;
        }
        $kept = array_map(basename(...), $kept);
        $count = 0;
        foreach ($storage->listContents($directory) as $entry) {
            if (\in_array(basename($entry->path()), $kept, true)) {
                continue;
            }
            if (!$entry->isDir() && str_ends_with($entry->path(), '.lock') && $this->isHeld($storage, $entry->path())) {
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

    private function isHeld(Storage $storage, string $path): bool
    {
        try {
            return $this->locks->isHeld($storage->absolutePath($path));
        } catch (StorageException) {
            return false;
        }
    }

    private function storageFor(?string $root): Storage
    {
        if ($root !== null) {
            return Storage::rootedAt($root);
        }

        return $this->storage ??= new Storage();
    }
}

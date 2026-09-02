<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\Filesystem\Filesystem;

/** Empties the caches a code change can leave stale: the compiled container and the compiled templates. */
class CacheClearer
{
    public const CONTAINER = 'cache/container';
    public const TEMPLATES = 'cache/templates';

    /** @var list<string> */
    public const ALL = [self::CONTAINER, self::TEMPLATES];

    /** What `clearEverything()` leaves alone: the placeholder git tracks, and the lock a maintenance run may be holding. */
    public const KEPT = ['.gitkeep', 'maintenance.lock'];

    /**
     * @param list<string> $which which of ALL to empty, every one by default
     * @param string|null  $root  the instance directory, the working directory by default
     *
     * @return array<string, int> per cache, how many top-level entries went
     */
    public function clear(array $which = self::ALL, ?string $root = null): array
    {
        $root ??= (string)getcwd();
        $filesystem = new Filesystem();
        $cleared = [];
        foreach ($which as $cache) {
            $dir = $root . '/' . $cache;
            $entries = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];
            foreach ($entries as $entry) {
                $filesystem->remove($dir . '/' . $entry);
            }
            $cleared[$cache] = \count($entries);
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
        $dir = ($root ?? (string)getcwd()) . '/cache';
        $entries = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..', ...self::KEPT])) : [];
        $filesystem = new Filesystem();
        foreach ($entries as $entry) {
            $filesystem->remove($dir . '/' . $entry);
        }

        return \count($entries);
    }
}

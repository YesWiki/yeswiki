<?php

namespace YesWiki\Files\Service;

/**
 * The local filesystem, addressed by absolute path, for the cases that cannot go through Storage.
 *
 * There are exactly two of them, and both are named in ADR-0022 rather than invented here.
 *
 * **A library that demands a real path.** `ZipArchive` ignores stream wrappers, and
 * `Zebra_Image`, `HTMLPurifier::cleanFile` and `getimagesize` want a filename rather than a
 * stream. That is why the ADR rejected a `yeswiki://` wrapper and chose `withLocalCopy()` and
 * `withLocalTarget()` instead: the download and the upload are explicit, and in between the
 * library gets a path it can open. This is the other half of that bargain -- the reading and
 * walking those libraries' callers do around the path they were handed.
 *
 * **A moment with no Instance to be rooted at.** Archiving a wiki walks its Program tree as well
 * as its own directory; cloning one reads a remote wiki's bytes before this wiki owns them;
 * destroying one writes the archive somewhere that will still exist afterwards. `Storage` is
 * rooted at one Instance by construction, so none of those can be asked of it.
 *
 * What this is **not** is a way around the rule. A path under `files/`, `custom/`, `cache/` or
 * `private/` belongs to a wiki and goes through `Storage`, which may put it in a bucket. If you
 * reach for this to read one of those, the answer is wrong on the deployment nobody can debug.
 */
class LocalFiles
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function size(string $path): int
    {
        return is_file($path) ? (int)filesize($path) : 0;
    }

    public function modifiedAt(string $path): int
    {
        return (int)@filemtime($path);
    }

    /** The bytes, or the empty string when the file is not there. */
    public function read(string $path): string
    {
        return is_file($path) ? (string)file_get_contents($path) : '';
    }

    public function write(string $path, string $contents): bool
    {
        return file_put_contents($path, $contents) !== false;
    }

    /** Add to the end of a file, for the callers that keep a log rather than a document. */
    public function append(string $path, string $contents): bool
    {
        return file_put_contents($path, $contents, FILE_APPEND) !== false;
    }

    public function makeDirectory(string $path): bool
    {
        return is_dir($path) || @mkdir($path, 0o755, true) || is_dir($path);
    }

    public function remove(string $path): bool
    {
        return !file_exists($path) || @unlink($path);
    }

    public function rename(string $from, string $to): bool
    {
        return rename($from, $to);
    }

    /** @return resource|null a write handle, for the libraries that fill one */
    public function openForWriting(string $path)
    {
        $handle = fopen($path, 'wb');

        return $handle === false ? null : $handle;
    }

    public function realPath(string $path): string|false
    {
        return realpath($path);
    }

    public function freeSpace(string $directory): float|false
    {
        return disk_free_space($directory);
    }

    /**
     * What is directly inside a directory, sorted, without the two entries nobody means.
     *
     * @return list<string> names, not paths
     */
    public function entriesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $names = [];
        foreach ((array)scandir($directory) as $name) {
            if (is_string($name) && $name !== '.' && $name !== '..') {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /**
     * @return list<string> absolute paths matching a shell pattern
     */
    public function matching(string $pattern): array
    {
        return glob($pattern) ?: [];
    }

    /** A scratch file whose name nothing else will take, in the system's temporary directory. */
    public function temporaryFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new \RuntimeException('Unable to make a temporary file.');
        }

        return $path;
    }
}

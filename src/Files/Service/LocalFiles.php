<?php

namespace YesWiki\Files\Service;

/** Local filesystem access by absolute path, for libraries that need a real path. */
class LocalFiles
{
    public function __construct(private readonly DiskSpace $diskSpace = new DiskSpace())
    {
    }

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

    public function isLink(string $path): bool
    {
        return is_link($path);
    }

    /** Remove a directory if it is empty, and say whether it is gone. */
    public function removeEmptyDirectory(string $path): bool
    {
        return !is_dir($path) || @rmdir($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /** The user id owning $path itself, a link not followed, or null when it is not there. */
    public function ownerOf(string $path): ?int
    {
        $stat = @lstat($path);

        return $stat === false ? null : (int)$stat['uid'];
    }

    /** The group id owning $path itself, a link not followed, or null when it is not there. */
    public function groupOf(string $path): ?int
    {
        $stat = @lstat($path);

        return $stat === false ? null : (int)$stat['gid'];
    }

    /** Give $path to this user and group, which only root may do for someone else's file. */
    public function changeOwner(string $path, int $user, int $group): bool
    {
        return @lchown($path, $user) && @lchgrp($path, $group);
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

    /** Write next to the target and rename over it, so a full disk or quota leaves the previous file whole. */
    public function writeAtomically(string $path, string $contents): bool
    {
        $target = is_link($path) ? (string)realpath($path) : $path;
        if ($target === '') {
            return false;
        }
        if (!is_writable(dirname($target))) {
            return @file_put_contents($target, $contents) === strlen($contents);
        }
        $temporary = dirname($target) . DIRECTORY_SEPARATOR . '.' . pathinfo($target, PATHINFO_FILENAME) . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handle = @fopen($temporary, 'x');
        if ($handle === false) {
            return false;
        }
        $bytes = @fwrite($handle, $contents);
        $flushed = @fflush($handle);
        $closed = fclose($handle);
        if ($bytes !== strlen($contents) || !$flushed || !$closed) {
            @unlink($temporary);

            return false;
        }
        @chmod($temporary, file_exists($target) ? (fileperms($target) & 0o777) : 0o644);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);

            return false;
        }

        return true;
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

    /** Marks the file as just used, for the caches that are pruned oldest first. */
    public function touch(string $path): bool
    {
        return @touch($path);
    }

    public function rename(string $from, string $to): bool
    {
        return rename($from, $to);
    }

    /** @return resource|null a read handle, for the readers that go through a file piece by piece */
    public function openForReading(string $path)
    {
        $handle = @fopen($path, 'rb');

        return $handle === false ? null : $handle;
    }

    /** @return resource|null a write handle, for the libraries that fill one */
    public function openForWriting(string $path)
    {
        $handle = fopen($path, 'wb');

        return $handle === false ? null : $handle;
    }

    /** @return resource|null a handle that writes after what the file already holds */
    public function openForAppending(string $path)
    {
        $handle = fopen($path, 'ab');

        return $handle === false ? null : $handle;
    }

    public function realPath(string $path): string|false
    {
        return realpath($path);
    }

    /** Free bytes on the disk holding $directory, capped by a ZFS user quota, null when the host will not say. */
    public function freeSpace(string $directory): ?float
    {
        $free = function_exists('disk_free_space') ? @disk_free_space($directory) : false;

        return $this->diskSpace->capped($free === false ? null : $free, $directory);
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

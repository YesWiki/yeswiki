<?php

namespace YesWiki\Admin\Service;

/**
 * The local filesystem work of installing a package: unzip here, copy there, delete afterwards.
 *
 * This was `Content\Entity\Files`, which was neither content nor an entity. It is the tree
 * manipulation behind `PackageCore`, `PackageExt` and `PackageTheme`: a downloaded zip is
 * extracted into a scratch directory and its contents are copied into the code the wiki runs --
 * `src/`, `themes/`, `vendor/`, an extension's own folder.
 *
 * **None of that is an Instance's data, and none of it can go through `Storage`.** ADR-0022's
 * tiers describe what a wiki owns; this writes the Program. `ZipArchive` ignores stream wrappers
 * and needs real paths (the ADR says so when it rejects `yeswiki://`), and what lands here is PHP
 * that will be `include`d, which is the same reason `custom/extensions/` is Runtime rather than
 * Public. A package installer that went through a bucket would install code nothing can execute.
 *
 * So it addresses the filesystem directly, on purpose, in one place, with that written down.
 */
class PackageTree
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

    public function makeDirectory(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0o755, true);
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

    public function remove(string $path): bool
    {
        return !file_exists($path) || @unlink($path);
    }

    /**
     * What is directly inside a directory, without the two entries nobody means.
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
     * @return list<string> paths matching a shell pattern
     */
    public function matching(string $pattern): array
    {
        return glob($pattern) ?: [];
    }

    /**
     * @return string the path of a fresh, empty temporary directory
     */
    protected function tmpdir()
    {
        $path = tempnam(YESWIKI_INSTANCE_DIR . '/cache', 'yeswiki_');
        if ($path === false) {
            throw new \RuntimeException('could not create a temporary file in ' . YESWIKI_INSTANCE_DIR . '/cache');
        }

        if (is_file($path)) {
            unlink($path);
        }

        mkdir($path);

        return $path;
    }

    /**
     * @param string|null $path
     *
     * @return true|list<string> true when nothing is left at $path, otherwise the paths that could not be deleted
     */
    protected function delete($path)
    {
        if (empty($path)) {
            return true;
        }

        if (is_file($path)) {
            if (@unlink($path)) {
                return true;
            }

            return [$path];
        }

        if (is_dir($path)) {
            return $this->deleteFolder($path);
        }

        return true;
    }

    /**
     * Copy a file or a whole folder over $des, leaving the old folder in place when the copy fails.
     *
     * @param string $src
     * @param string $des
     *
     * @return bool
     */
    protected function copy($src, $des)
    {
        if (is_file($src)) {
            return $this->copyFile($src, $des);
        }
        if (is_dir($src)) {
            return $this->replaceFolder($src, $des);
        }

        return false;
    }

    /** Put a folder aside, beside itself so the rename stays on one disk, and put it back when the copy fails. */
    private function replaceFolder(string $srcPath, string $desPath): bool
    {
        $desPath = rtrim($desPath, '/');
        $aside = null;

        if (file_exists($desPath) || is_link($desPath)) {
            $aside = \dirname($desPath) . '/.' . basename($desPath) . '.replaced-' . bin2hex(random_bytes(4));
            if (!@rename($desPath, $aside)) {
                return false;
            }
        }

        if (@mkdir($desPath, 0o755, true) && $this->copyFolder($srcPath, $desPath)) {
            if ($aside !== null) {
                $this->delete($aside);
            }

            return true;
        }

        $this->delete($desPath);
        if ($aside !== null) {
            @rename($aside, $desPath);
        }

        return false;
    }

    private function copyFile(string $src, string $des): bool
    {
        if ((is_dir($des) || is_link($des)) && $this->delete($des) !== true) {
            return false;
        }

        return @copy($src, $des);
    }

    /**
     * @param string $path
     *
     * @return true|list<string> true when everything under $path can be written, otherwise the paths that cannot
     */
    protected function isWritable($path)
    {
        try {
            if (!@file_exists($path) and @is_writable(dirname($path))) {
                return true;
            }

            if (@is_file($path)) {
                if (@is_writable($path)) {
                    return true;
                }

                return [$path];
            }

            if (@is_dir($path)) {
                return $this->isWritableFolder($path);
            }

            return [$path];
        } catch (\Throwable $pThrowable) {
            return [$path];
        }
    }

    /**
     * @param string      $sourceUrl
     * @param string|null $destPath     where to write, a temporary file when null
     * @param int         $timeoutInSec
     *
     * @return string the path the body was written to
     */
    public function download($sourceUrl, $destPath = null, $timeoutInSec = 5)
    {
        if ($destPath === null) {
            $destPath = tempnam(YESWIKI_INSTANCE_DIR . '/cache', 'tmp_to_delete_');
            if ($destPath === false) {
                throw new \RuntimeException('could not create a temporary file in ' . YESWIKI_INSTANCE_DIR . '/cache');
            }
        }
        $fp = fopen($destPath, 'wb');
        if ($fp === false) {
            throw new \RuntimeException("could not open $destPath for writing");
        }
        $ch = curl_init($sourceUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_exec($ch);
        fclose($fp);

        return $destPath;
    }

    /**
     * @param string $path
     *
     * @return true|list<string>
     */
    private function isWritableFolder($path)
    {
        $file2ignore = ['.', '..', '.git'];

        $vNotWritables = [];

        if (@is_dir($path)) {
            if (@is_writable($path) !== true) {
                $vNotWritables[] = $path;
            }

            if ($res = @opendir($path)) {
                while (($file = @readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $vIsWritable = $this->isWritable($path . '/' . $file);

                        if ($vIsWritable !== true) {
                            $vNotWritables = array_merge($vNotWritables, $vIsWritable);
                        }
                    }
                }
                @closedir($res);
            } else {
                $vNotWritables[] = $path;
            }
        } else {
            $vNotWritables[] = $path;
        }

        if (count($vNotWritables) == 0) {
            return true;
        }

        return $vNotWritables;
    }

    /**
     * @param string $path
     *
     * @return true|list<string>
     */
    private function deleteFolder($path)
    {
        $file2ignore = ['.', '..'];
        if (is_link($path)) {
            if (@unlink($path)) {
                return true;
            }

            return [$path];
        }
        $vNotDeleteds = [];

        if ($res = opendir($path)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    $vDeleteStatus = $this->delete(rtrim($path, '/') . '/' . $file);

                    if ($vDeleteStatus !== true) {
                        $vNotDeleteds = array_merge($vNotDeleteds, $vDeleteStatus);
                    }
                }
            }
            closedir($res);
        }

        if (!@rmdir($path)) {
            $vNotDeleteds[] = $path;
        }

        if (count($vNotDeleteds) == 0) {
            return true;
        }

        return $vNotDeleteds;
    }

    /** Copy what is inside $srcPath into the existing folder $desPath, saying whether all of it made it. */
    private function copyFolder(string $srcPath, string $desPath): bool
    {
        $res = @opendir($srcPath);
        if ($res === false) {
            return false;
        }

        $copied = true;
        while (($file = readdir($res)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $from = rtrim($srcPath, '/') . '/' . $file;
            $to = rtrim($desPath, '/') . '/' . $file;
            if (is_dir($from)) {
                if (!@mkdir($to) || !$this->copyFolder($from, $to)) {
                    $copied = false;
                }
            } elseif (!$this->copyFile($from, $to)) {
                $copied = false;
            }
        }
        closedir($res);

        return $copied;
    }
}

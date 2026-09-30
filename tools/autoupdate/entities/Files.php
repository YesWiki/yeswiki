<?php

namespace YesWiki\AutoUpdate\Entity;

class Files
{
    protected function tmpdir()
    {
        $path = tempnam(realpath('cache'), 'yeswiki_');

        if (is_file($path)) {
            unlink($path);
        }

        mkdir($path);

        return $path;
    }

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
    }

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

    /**
     * Put a folder aside before rebuilding it, and put it back when the copy fails.
     */
    private function replaceFolder($srcPath, $desPath)
    {
        $desPath = rtrim($desPath, '/');
        $aside = null;

        if (file_exists($desPath) or is_link($desPath)) {
            $aside = $this->tmpdir() . '/' . basename($desPath);
            if (!@rename($desPath, $aside)) {
                return false;
            }
        }

        if (@mkdir($desPath) and $this->copyFolder($srcPath, $desPath) === true) {
            if ($aside !== null) {
                $this->delete(dirname($aside));
            }

            return true;
        }

        $this->delete($desPath);
        if ($aside !== null and @rename($aside, $desPath)) {
            $this->delete(dirname($aside));
        }

        return false;
    }

    private function copyFile($src, $des)
    {
        if (is_dir($des) or is_link($des)) {
            if ($this->delete($des) !== true) {
                return false;
            }
        }

        return copy($src, $des);
    }

    protected function isWritable($path)
    {
        try {
            // la destination n'existe pas et droits d'écriture sur le repertoire
            // de destination
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

            // TODO Gérer les liens
            return [$path];
        } catch (\Throwable $pThrowable) {
            return [$path];
        }
    }

    public function download($sourceUrl, $destPath = null, $timeoutInSec = 5)
    {
        if ($destPath === null) {
            $destPath = tempnam('cache', 'tmp_to_delete_');
        }
        $fp = fopen($destPath, 'wb');
        $ch = curl_init($sourceUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_exec($ch);
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }
        fclose($fp);

        return $destPath;
    }

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

    private function copyFolder($srcPath, $desPath)
    {
        $res = @opendir($srcPath);
        if ($res === false) {
            return false;
        }

        $copied = true;
        while (($file = readdir($res)) !== false) {
            if ($file === '.' or $file === '..') {
                continue;
            }
            $from = rtrim($srcPath, '/') . '/' . $file;
            $to = rtrim($desPath, '/') . '/' . $file;
            if (is_dir($from)) {
                if (!@mkdir($to) or $this->copyFolder($from, $to) !== true) {
                    $copied = false;
                }
            } elseif ($this->copyFile($from, $to) !== true) {
                $copied = false;
            }
        }
        closedir($res);

        return $copied;
    }
}

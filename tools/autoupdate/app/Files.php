<?php

namespace AutoUpdate;

class Files
{
    protected function tmpdir()
    {
        $cachePath = (!empty($this->wiki->config['dataPath'])) ? $this->wiki->config['dataPath'].'/cache' : 'cache';
        
        $path = tempnam(realpath($cachePath), 'yeswiki_');

        if (is_file($path)) {
            unlink($path);
        }

        mkdir($path);
        return $path;
    }

    protected function delete($path)
    {
        if (empty($path)) {
            return false;
        }
        if (is_file($path)) {
            if (unlink($path)) {
                return true;
            }
            return false;
        }
        if (is_dir($path)) {
            return $this->deleteFolder($path);
        }
    }

    protected function copy($src, $des)
    {
        if (is_file($des) or is_dir($des) or is_link($des)) {
            $this->delete($des);
        }
        if (is_file($src)) {
            return copy($src, $des);
        }
        if (is_dir($src)) {
            if (!mkdir($des)) {
                return false;
            }
            return $this->copyFolder($src, $des);
        }
        return false;
    }

    protected function isWritable($path)
    {
        // la destination n'existe pas et droits d'écriture sur le repertoire
        // de destination
        if (!file_exists($path) and is_writable(dirname($path))) {
            return true;
        }

        if (is_file($path)) {
            return is_writable($path);
        }

        if (is_dir($path)) {
            return $this->isWritableFolder($path);
        }

        // TODO Gérer les liens
        return false;
    }

    public function download($sourceUrl, $destPath = null, $timeoutInSec = 5)
    {
        if ($destPath === null) {
            $cachePath = (!empty($this->wiki->config['dataPath'])) ? $this->wiki->config['dataPath'].'/cache' : 'cache';
            $destPath = tempnam($cachePath, 'tmp_to_delete_');
        }
        $fp = fopen($destPath, 'wb');
        $ch = curl_init($sourceUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        return $destPath;
    }

    private function isWritableFolder($path)
    {
        $file2ignore = array('.', '..', '.git');
        if ($res = opendir($path)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    if (!$this->isWritable($path . '/' . $file)) {
                        // TODO remonter les fichiers/dossier qui posent
                        // problèmes
                        return false;
                    }
                }
            }
            closedir($res);
        }
        return true;
    }

    private function deleteFolder($path)
    {
        $file2ignore = array('.', '..');
        if (is_link($path)) {
            unlink($path);
        } else {
            if ($res = opendir($path)) {
                while (($file = readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $this->delete($path . '/' . $file);
                    }
                }
                closedir($res);
            }
            rmdir($path);
        }
        return true;
    }

    private function copyFolder($srcPath, $desPath)
    {
        $file2ignore = array('.', '..');
        if ($res = opendir($srcPath)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    $this->copy($srcPath . '/' . $file, $desPath . '/' . $file);
                }
            }
            closedir($res);
        }
        return true;
    }
}

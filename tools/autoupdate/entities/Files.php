<?php

namespace YesWiki\AutoUpdate\Entity;

use Throwable;

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
        if (empty($path)) return true;
        
        if (is_file($path)) {
            if (@unlink($path)) {
                return true;
            } else {
                return [ $path ]; 
            }
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
        try {    
            // la destination n'existe pas et droits d'écriture sur le repertoire
            // de destination
            if (!@file_exists($path) and @is_writable(dirname($path))) {
                return true;
            }

            if (@is_file($path)) {
                if (@is_writable($path)) return true;
                else return [ $path ];
            }

            if (@is_dir($path)) {
                return $this->isWritableFolder($path);
            }

            // TODO Gérer les liens
            return [ $path ];
        } catch (Throwable $pThrowable) {
            return [ $path ];
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
        curl_close($ch);
        fclose($fp);

        return $destPath;
    }

    private function isWritableFolder($path)
    {
        $file2ignore = ['.', '..', '.git'];
    
        $vNotWritables = [];

        if (@is_dir($path)) {	        
            if (@is_writable($path) !== true) $vNotWritables [] = $path;

            if ($res = @opendir($path)) {
                while (($file = @readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $vIsWritable = $this->isWritable($path . '/' . $file);
                        
                        if ($vIsWritable !== true)
                            $vNotWritables = array_merge ($vNotWritables, $vIsWritable);
                        }
                    }
                @closedir($res);
            } else {
                $vNotWritables [] = $path;
            }
        } else {
            $vNotWritables [] = $path;
        }
        
        if (count ($vNotWritables) == 0) return true;
        else return $vNotWritables;
    }

    private function deleteFolder($path)
    {    
        $file2ignore = ['.', '..'];
        if (is_link($path)) {
            if (@unlink($path)) return true;
            else return [ $path ];
        } else {
        
            $vNotDeleteds = [];
        
            if ($res = opendir($path)) {
                while (($file = readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $vDeleteStatus = $this->delete(rtrim ($path, '/') . '/' . $file);
                        
                        if ($vDeleteStatus !== true) {                        
                            $vNotDeleteds = array_merge ($vNotDeleteds, $vDeleteStatus);
                        }
                    }
                }
                closedir($res);
            }

            if (!@rmdir($path)) $vNotDeleteds [] = $path;
        }

        if (count ($vNotDeleteds) == 0) return true;
        else return $vNotDeleteds;
    }

    private function copyFolder($srcPath, $desPath)
    {
        $file2ignore = ['.', '..'];
        if ($res = opendir($srcPath)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    $this->copy(rtrim ($srcPath, '/') . '/' . $file, rtrim ($desPath, '/') . '/' . $file);
                }
            }
            closedir($res);
        }

        return true;
    }
}

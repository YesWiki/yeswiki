<?php

namespace YesWiki\Files\Service;

use YesWiki\Kernel\Service\SsrfUrlValidator;

/** Fetching a file from somewhere else, and naming it safely once it is here. */
class RemoteFile
{
    /** The last segment of $url, safe to use as a filename. */
    public static function filenameFor(string $url): string
    {
        $str = (string)preg_replace('/[\r\n\t ]+/', ' ', basename($url));
        $str = (string)preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
        $str = str_replace(' ', '-', $str);

        return (string)preg_replace('/-+/', '-', $str);
    }

    /** Download $url to $localPath, or report why not. */
    public static function download(string $url, string $localPath): bool
    {
        // Static, so both services are built here rather than injected: they hold nothing, and
        // this is called from places that have no container to hand.
        $storage = new Storage();
        $localFiles = new LocalFiles();

        if ($storage->exists($localPath)) {
            return true;
        }
        try {
            $pin = (new SsrfUrlValidator())->curlPin($url, ['http', 'https']);
        } catch (\Throwable $error) {
            echo _t('BAZ_IMAGE_FILE_NOT_FOUND') . ' : ' . $url;

            return false;
        }
        if ($ch = curl_init($url)) {
            foreach ($pin as $option => $optionValue) {
                curl_setopt($ch, $option, $optionValue);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $imgcontent = curl_exec($ch);
            $error = curl_error($ch);
            if (PHP_VERSION_ID < 80500) {
                curl_close($ch);
            }
            $file = is_string($imgcontent) ? $localFiles->openForWriting($localPath) : null;
            if ($file !== null) {
                fputs($file, (string)$imgcontent);
                fclose($file);
            }
            if ($error) {
                echo $error;

                return false;
            }

            return true;
        }
        echo _t('BAZ_IMAGE_FILE_NOT_FOUND') . ' : ' . $url;

        return false;
    }
}

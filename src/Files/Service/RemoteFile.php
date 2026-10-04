<?php

namespace YesWiki\Files\Service;

use YesWiki\Kernel\Service\PinnedFetcher;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/** Fetching a file from somewhere else, and naming it safely once it is here. */
class RemoteFile
{
    public const CONNECT_TIMEOUT = 10;

    public const TIMEOUT = 60;

    /** The last segment of $url, safe to use as a filename. */
    public static function filenameFor(string $url): string
    {
        $str = (string)preg_replace('/[\r\n\t ]+/', ' ', basename($url));
        $str = (string)preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
        $str = str_replace(' ', '-', $str);

        return (string)preg_replace('/-+/', '-', $str);
    }

    /** Download $url to the instance path $localPath, saying whether there is a file there afterwards; nothing is written when the fetch fails. */
    public static function download(string $url, string $localPath, ?string &$error = null): bool
    {
        $error = null;
        $storage = new Storage();
        if ($storage->exists($localPath)) {
            return true;
        }

        try {
            $bytes = (new PinnedFetcher(new SsrfUrlValidator()))->fetch($url, [
                'connectTimeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
            ]);
            $storage->write($localPath, $bytes);
        } catch (\Throwable $failure) {
            $error = $failure->getMessage();

            return false;
        }

        return true;
    }
}

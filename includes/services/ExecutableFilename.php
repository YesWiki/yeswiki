<?php

namespace YesWiki\Core\Service;

/**
 * Tells whether a web server could run a file, or read it as its own configuration, because of its name.
 */
class ExecutableFilename
{
    public const PATTERN = '/(\.(php\d*|phtml|pht|phar|phps|pgif|inc|shtml|shtm|cgi|fcgi|pl|py|sh|asp|aspx|jsp)$|^\.ht|^\.user\.ini$)/i';

    public static function matches(string $filename): bool
    {
        $name = basename(str_replace('\\', '/', $filename));

        return preg_match(self::PATTERN, rtrim($name, " .\t\n\r\0\x0B")) === 1;
    }
}

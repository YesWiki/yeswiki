<?php
/**
 * Yeswiki is a great wiki
 * This file load the autoload file only once and load the wiki as singleton
 * Created to allow tests without running YesWiki but in the same state as production
 *
 * @category Wiki
 * @package  YesWiki
 * @license  AGPL version 3
 * @link     https://yeswiki.net
 */

namespace YesWiki\Core;

use Doctrine\Common\Annotations\AnnotationRegistry;
use YesWiki\Wiki;

class YesWikiLoader
{
    // singleton
    private static $wiki;

    protected function __construct()
    {
    } // prevent public usage
    protected function __clone()
    {
    } // prevent public usage

    public static function getWiki(bool $test = false): Wiki
    {
        if (is_null(self::$wiki)) {
            require_once 'includes/autoload.inc.php';
            $loader = require_once 'vendor/autoload.php';
            if ($loader !== true) { // not true if not already included
                AnnotationRegistry::registerLoader([$loader, 'loadClass']);
            }

            $loadedWiki = require_once 'includes/YesWiki.php';
            if ($loadedWiki !== true || is_null(self::$wiki)) {
                // params to succeed to instanciate wiki for tests
                if ($test) {
                    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '';
                    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $_SESSION = $_SESSION ?? [];
                }

                self::$wiki = new Wiki();
            }
        }
        return self::$wiki;
    }
}

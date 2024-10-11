<?php
/**
 * Yeswiki is a great wiki
 * This file loads the autoload file only once and loads the wiki as singleton
 * Created to allow tests without running YesWiki but in the same state as production.
 *
 * @category Wiki
 *
 * @license  AGPL version 3
 *
 * @see     https://yeswiki.net
 */

namespace YesWiki\Core;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Exception;
use Throwable;
use YesWiki\Wiki;

class YesWikiLoader
{
    // singleton
    private static $wiki;

    protected function __construct()
    {
    }

    // prevent public usage
    protected function __clone()
    {
    } // prevent public usage

    public static function getWiki(bool $test = false): Wiki
    {
        if (is_null(self::$wiki)) {
            require_once 'includes/autoload.inc.php';
            try {
                if (!file_exists('vendor/autoload.php')) {
                    throw new Exception('ERROR ! : Folder `vendor/` seems not to be entirely copied ! (Maybe a YesWiki update aborted before its end !)<br/><strong>Could you manually copy the folder `vendor/` on your server by ftp ?</strong><br/>');
                }
                $loader = require_once 'vendor/autoload.php';
            } catch (Throwable $th) {
                $message = $th->getMessage();
                // echo message directly because TemplateEngine not ready here
                echo "<div style=\"border:1px red solid;background-color: #FFCCCC;margin:3px;padding:5px;border-radius:5px;\">$message</div>";
                exit();
            }
            if ($loader !== true) { // not true if not already included
                AnnotationRegistry::registerLoader([$loader, 'loadClass']);
            }

            $loadedWiki = require_once 'includes/YesWiki.php';
            if ($loadedWiki !== true || is_null(self::$wiki)) {
                // params to succeed to instanciate wiki for tests
                if ($test) {
                    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '';
                    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $_SESSION = $_SESSION ?? [];
                }

                self::$wiki = new Wiki();
            }
        }

        return self::$wiki;
    }
}

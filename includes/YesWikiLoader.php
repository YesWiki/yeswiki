<?php
/**
 * Yeswiki is a great wiki
 * This file loads the autoload file only once and loads the wiki as singleton
 * Created to allow tests without running YesWiki but in the same state as production
 *
 * @category Wiki
 * @package  YesWiki
 * @license  AGPL version 3
 * @link     https://yeswiki.net
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
    } // prevent public usage

    protected function __clone()
    {
    } // prevent public usage

    protected static function getYesWikiAutoloaders() {
        spl_autoload_register(function ($className) {
            // Autoload services
            if (preg_match("/^YesWiki\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?(?:\\\\([^\\\\]+))?$/", $className, $matches)) {
                if (empty($matches[2])) {
                    // not currently managed
                } elseif (empty($matches[3])) {
                    switch ($matches[1]) {
                        case 'Core':
                            if (file_exists('includes/' . $matches[2] . '.php')) {
                                require 'includes/' . $matches[2] . '.php';
                            }
                            break;
                        default:
                            // actions or handlers, directly managed by Performer
                            break;
                    }
                } else {
                    // basePath
                    switch ($matches[1]) {
                        case 'Core':
                            $basePath = 'includes';
                            break;
                        case 'Custom':
                            $basePath = 'custom';
                            break;
                        default:
                            $extension = strtolower($matches[1]);
                            $basePath = "tools/$extension";
                            break;
                    }
                    // Autoload services
                    switch ($matches[2]) {
                        case 'Service':
                            require "$basePath/services/{$matches[3]}.php";
                            break;
                        case 'Controller':
                            require "$basePath/controllers/{$matches[3]}.php";
                            break;
                        case 'Field':
                            if ($matches[1] != "Core") {
                                require "$basePath/fields/{$matches[3]}.php";
                            }
                            break;
                        case 'Commands':
                            require "$basePath/commands/{$matches[3]}.php";
                            break;
                        case 'Entity':
                            require "$basePath/entities/{$matches[3]}.php";
                            break;
                        case 'Exception':
                            require "$basePath/exceptions/{$matches[3]}.php";
                            break;
                        case 'Trait':
                            require "$basePath/traits/{$matches[3]}.php";
                            break;
                        default:
                            // do nothing
                            break;
                    }
                }
            }
        });        
    }

    public static function getWiki(bool $test = false): Wiki
    {
        if (is_null(self::$wiki)) {
            self::getYesWikiAutoloaders();
            try {
                if (!file_exists('vendor/autoload.php')) {
                    throw new Exception("ERROR ! : Folder `vendor/` seems not to be entirely copied ! (Maybe a YesWiki update aborted before its end !)<br/><strong>Could you manually copy the folder `vendor/` on your server by ftp ?</strong><br/>");
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

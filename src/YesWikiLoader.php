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

use Symfony\Component\Dotenv\Dotenv;
use YesWiki\YesWikiRuntime;

class YesWikiLoader
{
    // singleton
    /** @var YesWikiRuntime|null */
    private static $runtime;

    protected function __construct()
    {
    }

    // prevent public usage
    protected function __clone()
    {
    } // prevent public usage

    /**
     * Load per-instance environment variables from private/.env, read from private/
     * because the YesWiki root is the web root: a top-level .env would be downloadable
     * (private/ is deny-all, see bootstrap_paths.php). Real environment variables are
     * never overridden by the file, so Docker/CI injected values keep priority.
     * usePutenv() makes the values visible to the getenv() calls used across the
     * codebase, not only to $_ENV/$_SERVER readers.
     *
     * Idempotent, so entry points needing env vars before getWiki() (e.g. the console
     * script) can call it early. Callers must have loaded bootstrap_paths.php and the
     * composer autoloader first.
     */
    public static function loadEnv(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $values = self::envFileValues();
        if ($values !== []) {
            (new Dotenv())->usePutenv()->populate($values);
        }
    }

    /**
     * The raw entries parsed from private/.env, unfiltered by the real environment.
     * Used by EnvironmentConfiguration: config overrides distinguish file-authored
     * values (any config key allowed) from process environment values (known
     * variables only), which getenv() alone cannot tell apart.
     */
    public static function envFileValues(): array
    {
        static $values = null;
        if ($values === null) {
            $envFile = YESWIKI_INSTANCE_DIR . '/private/.env';
            $values = is_file($envFile)
                ? (new Dotenv())->parse((string)file_get_contents($envFile), $envFile)
                : [];
        }

        return $values;
    }

    public static function getWiki(bool $test = false): YesWikiRuntime
    {
        if (is_null(self::$runtime)) {
            require_once __DIR__ . '/bootstrap_paths.php';
            require_once __DIR__ . '/autoload.inc.php';
            try {
                if (!file_exists(YESWIKI_SOURCE_DIR . '/vendor/autoload.php')) {
                    throw new \Exception('ERROR ! : Folder `vendor/` seems not to be entirely copied ! (Maybe a YesWiki update aborted before its end !)<br/><strong>Could you manually copy the folder `vendor/` on your server by ftp ?</strong><br/>');
                }
                $loader = require_once YESWIKI_SOURCE_DIR . '/vendor/autoload.php';
            } catch (\Throwable $th) {
                $message = $th->getMessage();
                // echo message directly because TemplateEngine not ready here
                echo "<div style=\"border:1px red solid;background-color: #FFCCCC;margin:3px;padding:5px;border-radius:5px;\">$message</div>";
                exit;
            }

            self::loadEnv();

            $loadedRuntime = require_once __DIR__ . '/YesWikiRuntime.php';
            if ($loadedRuntime !== true || is_null(self::$runtime)) {
                // params to succeed to instanciate wiki for tests
                if ($test) {
                    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '';
                    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $_SESSION = $_SESSION ?? [];
                }

                self::$runtime = new YesWikiRuntime();
            }
        }

        return self::$runtime;
    }
}

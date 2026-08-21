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
     *
     * @return array<string, string>
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
                if (!file_exists(YESWIKI_PROGRAM_DIR . '/vendor/autoload.php')) {
                    throw new \Exception('ERROR ! : Folder `vendor/` seems not to be entirely copied ! (Maybe a YesWiki update aborted before its end !)<br/><strong>Could you manually copy the folder `vendor/` on your server by ftp ?</strong><br/>');
                }
                $loader = require_once YESWIKI_PROGRAM_DIR . '/vendor/autoload.php';
                self::checkAutoloaderIsCurrent();
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

    /**
     * The generated autoloader knows every namespace `composer.json` declares.
     *
     * **Replacing the code is not enough on its own.** PSR-4 prefixes are baked into
     * `vendor/composer/autoload_psr4.php` when composer runs; they are not read from
     * `composer.json` at runtime. Ectoplasme introduced eleven prefixes Doryphore never had
     * (`YesWiki\Import\`, `YesWiki\Render\`, `YesWiki\Content\` and the rest), so an
     * instance whose files were swapped without `composer install` boots with an autoloader
     * that cannot see half the application.
     *
     * What that failure looks like, unhelpfully, is a Symfony container error naming
     * whichever file happens to sort first in the directory it gave up on:
     *
     *     Expected to find class "YesWiki\Import\Service\ImapImporter" in file
     *     "…/src/Import/Service/ImapImporter.php" … but it was not found!
     *
     * That file is fine. It is simply the first one alphabetically in the first namespace the
     * container tried to scan. Nothing in the message says "run composer", and the class it
     * names is a red herring, which is the whole reason for checking here instead.
     */
    private static function checkAutoloaderIsCurrent(): void
    {
        $generated = YESWIKI_PROGRAM_DIR . '/vendor/composer/autoload_psr4.php';
        $manifest = YESWIKI_PROGRAM_DIR . '/composer.json';
        if (!is_file($generated) || !is_file($manifest)) {
            return;
        }

        $declared = json_decode((string)file_get_contents($manifest), true);
        $declared = is_array($declared) ? ($declared['autoload']['psr-4'] ?? []) : [];
        if (!is_array($declared) || $declared === []) {
            return;
        }

        /** @var array<string, mixed> $mapped */
        $mapped = require $generated;
        $missing = array_values(array_diff(array_keys($declared), array_keys($mapped)));
        if ($missing === []) {
            return;
        }

        throw new \Exception('ERROR ! : the autoloader in <code>vendor/</code> is older than this code. It does not know ' . implode(', ', array_map(static fn (string $prefix): string => '<code>' . htmlspecialchars($prefix) . '</code>', $missing)) . ', so most of YesWiki cannot be loaded.<br/><br/><strong>Run <code>composer install</code> in ' . htmlspecialchars(YESWIKI_PROGRAM_DIR) . '</strong> (or <code>composer dump-autoload</code> if <code>vendor/</code> is already up to date), then reload. If you have an opcode cache, restart PHP too.');
    }
}

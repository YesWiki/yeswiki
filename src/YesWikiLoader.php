<?php

// Loads the autoloader once and the wiki as a singleton, so tests run in the same state as production.

namespace YesWiki\Core;

use Symfony\Component\Dotenv\Dotenv;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\DoryphoreLeftovers;

class YesWikiLoader
{
    /** @var YesWikiRuntime|null */
    private static $runtime;

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    /** Loads the instance's private/.env, kept in private/ because the wiki root is the web root. */
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

    /** @return array<string, string> the raw entries of an instance's private/.env, unfiltered by the real environment */
    public static function envFileValues(?string $instanceDir = null): array
    {
        static $byInstance = [];

        $instanceDir ??= YESWIKI_INSTANCE_DIR;
        if (!isset($byInstance[$instanceDir])) {
            $envFile = $instanceDir . '/private/.env';
            $byInstance[$instanceDir] = is_file($envFile)
                ? (new Dotenv())->parse((string)file_get_contents($envFile), $envFile)
                : [];
        }

        return $byInstance[$instanceDir];
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
                echo "<div style=\"border:1px red solid;background-color: #FFCCCC;margin:3px;padding:5px;border-radius:5px;\">$message</div>";
                exit;
            }

            self::loadEnv();
            self::setDoryphoreLeftoversAside();

            $loadedRuntime = require_once __DIR__ . '/YesWikiRuntime.php';
            if ($loadedRuntime !== true || is_null(self::$runtime)) {
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

    /** Moves a Doryphore wiki's leftovers aside, then starts over on fresh files: its updater may have swapped them under a warm opcode cache. */
    private static function setDoryphoreLeftoversAside(): void
    {
        if (getenv('YESWIKI_CONFIG_FILE') === false) {
            DoryphoreLeftovers::renameConfiguration(YESWIKI_INSTANCE_DIR);
        }
        $moved = DoryphoreLeftovers::setAside(YESWIKI_INSTANCE_DIR, ConfigurationFileProvider::getConfigFileFromEnv());
        if ($moved === []) {
            return;
        }
        (new CacheClearer())->clear(CacheClearer::ALL, YESWIKI_INSTANCE_DIR);
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'note | Doryphore leftovers set aside in ' . DoryphoreLeftovers::ASIDE . ":\n  " . implode("\n  ", $moved) . "\n");

            return;
        }
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
        exit;
    }

    /** The generated autoloader knows every namespace `composer.json` declares. */
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

        $mapped = require $generated;
        if (!is_array($mapped)) {
            return;
        }
        $missing = array_values(array_diff(array_keys($declared), array_keys($mapped)));
        if ($missing === []) {
            return;
        }

        throw new \Exception('ERROR ! : the autoloader in <code>vendor/</code> is older than this code. It does not know ' . implode(', ', array_map(static fn (string $prefix): string => '<code>' . htmlspecialchars($prefix) . '</code>', $missing)) . ', so most of YesWiki cannot be loaded.<br/><br/><strong>Run <code>composer install</code> in ' . htmlspecialchars(YESWIKI_PROGRAM_DIR) . '</strong> (or <code>composer dump-autoload</code> if <code>vendor/</code> is already up to date), then reload. If you have an opcode cache, restart PHP too.');
    }
}

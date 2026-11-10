<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Service\Storage;

/** `/healthz` and `/readyz`, answered before the wiki serves a page: no session, no queue pass, nothing journaled (replicas 08). */
final class Probes
{
    public const LIVENESS = 'healthz';

    public const READINESS = 'readyz';

    /** Which probe this request asks for, null for every other request. */
    public static function asked(): ?string
    {
        $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $mount = rtrim(str_replace('\\', '/', \dirname($script === '' ? '/index.php' : $script)), '/');
        foreach ([self::LIVENESS, self::READINESS] as $probe) {
            if ($path === $mount . '/' . $probe) {
                return $probe;
            }
        }

        return null;
    }

    /** The process answers: nothing else is asked, so a database failover restarts no replica. */
    public static function live(): void
    {
        self::answer(200, 'text/plain', 'ok');
    }

    /** Whether this replica can serve: its database answers and its storage does; 503 naming what does not. The wiki is not booted, since booting reads a page. */
    public static function ready(): void
    {
        require_once \dirname(__DIR__, 2) . '/autoload.inc.php';
        require_once YESWIKI_PROGRAM_DIR . '/vendor/autoload.php';
        require_once \dirname(__DIR__, 2) . '/YesWikiLoader.php';
        YesWikiLoader::loadEnv();

        $checks = ['database' => 'ok', 'storage' => 'ok'];
        $unreachable = (new DbService(new ParameterBag(self::configuration()), connectNow: false))->ping();
        if ($unreachable !== null) {
            $checks['database'] = 'failed';
            error_log('readyz: the database does not answer: ' . $unreachable);
        }
        try {
            (new Storage())->exists('files/.yeswiki-readyz');
        } catch (\Throwable $storage) {
            $checks['storage'] = 'failed';
            error_log('readyz: the storage does not answer: ' . $storage->getMessage());
        }
        $ready = !array_filter($checks, static fn (string $state): bool => $state !== 'ok');

        self::answer($ready ? 200 : 503, 'application/json', (string)json_encode(['ready' => $ready, 'checks' => $checks], JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> the configuration file, with what the environment overrides */
    private static function configuration(): array
    {
        $file = (new ConfigurationService())->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $file->load();
        $config = [];
        foreach ($file as $key => $value) {
            $config[(string)$key] = $value;
        }

        return EnvironmentConfiguration::apply($config);
    }

    private static function answer(int $status, string $type, string $body): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $type . '; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo $body;
    }
}

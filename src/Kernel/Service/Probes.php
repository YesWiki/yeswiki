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

    /** Whether this replica can serve: its database and storage answer and, when replicated, its migrations have run; 503 naming what does not. The wiki is not booted. */
    public static function ready(): void
    {
        require_once \dirname(__DIR__, 2) . '/autoload.inc.php';
        require_once YESWIKI_PROGRAM_DIR . '/vendor/autoload.php';
        require_once \dirname(__DIR__, 2) . '/YesWikiLoader.php';
        YesWikiLoader::loadEnv();

        $checks = ['database' => 'ok', 'storage' => 'ok'];
        $config = self::configuration();
        $db = new DbService(new ParameterBag($config), connectNow: false);
        $unreachable = $db->ping();
        if ($unreachable !== null) {
            $checks['database'] = 'failed';
            error_log('readyz: the database does not answer: ' . $unreachable);
        } elseif (Replication::isOn()) {
            $checks['migrations'] = self::migrations($db, $config);
        }
        try {
            (new Storage())->exists('files/.yeswiki-readyz');
        } catch (\Throwable $storage) {
            $checks['storage'] = 'failed';
            error_log('readyz: the storage does not answer: ' . $storage->getMessage());
        }
        $ready = !array_filter($checks, static fn (string $state): bool => $state !== 'ok' && $state !== SchemaStep::AHEAD);

        self::answer($ready ? 200 : 503, 'application/json', (string)json_encode(['ready' => $ready, 'checks' => $checks], JSON_UNESCAPED_SLASHES));
    }

    /**
     * Whether this code's migrations have all run: `behind` keeps a new replica out until the migrate Job has run, `ahead` keeps an old one serving reads.
     *
     * @param array<string, mixed> $config
     */
    private static function migrations(DbService $db, array $config): string
    {
        try {
            $state = (new SchemaStep($db))->state(SchemaStep::foldersFor($config));
        } catch (\Throwable $failure) {
            error_log('readyz: the migrations cannot be compared: ' . $failure->getMessage());

            return 'failed';
        }

        return $state === SchemaStep::IN_STEP ? 'ok' : $state;
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

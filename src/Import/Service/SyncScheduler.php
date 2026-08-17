<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConsoleService;

/** Syncs, without any external cron, the data sources flagged `syncOnMaintenance`. */
class SyncScheduler
{
    /** Where the last sync of each source is recorded, under the cache directory. */
    private const STATE_DIR = 'importer';

    /** A run's kept output, tail first: a log nobody rotates must not grow forever. */
    private const MAX_LOG_LENGTH = 20000;

    protected ParameterBagInterface $params;
    protected ContainerInterface $services;

    public function __construct(ParameterBagInterface $params, ContainerInterface $services)
    {
        $this->params = $params;
        $this->services = $services;
    }

    /** Claim whatever is due and get it imported off the visitor's clock. */
    public function onMaintenance(?int $maintenanceStartedAt = null): void
    {
        try {
            $dueSources = $this->claimDueSources($maintenanceStartedAt);
        } catch (\Throwable $ex) {
            return;
        }
        if (empty($dueSources)) {
            return;
        }

        $couldNotSpawn = [];
        foreach ($dueSources as $id => $options) {
            if (!$this->spawnSync((string)$id)) {
                $couldNotSpawn[$id] = $options;
            }
        }
        if (!empty($couldNotSpawn)) {
            register_shutdown_function(function () use ($couldNotSpawn) {
                $this->runAfterResponse($couldNotSpawn);
            });
        }
    }

    /**
     * The sources due for an automatic sync right now, already claimed (their state file is stamped before returning, so a concurrent request sees them as just-synced instead of starting the same import in parallel).
     *
     * @param int|null $maintenanceStartedAt when the housekeeping run asking this began. A
     *                                       source that has synced since then has already been taken by this run --
     *                                       which is what stops two page views crossing the interval together from
     *                                       importing the same source twice. Null means "whatever ran before, this
     *                                       is a fresh ask", for the console and for tests.
     *
     * @return array<string, array<string, mixed>> [sourceId => sourceOptions]
     */
    public function claimDueSources(?int $maintenanceStartedAt = null): array
    {
        $due = [];
        foreach ($this->dataSources() as $id => $options) {
            if (empty($options['syncOnMaintenance'])) {
                continue;
            }
            $id = (string)$id;
            $lastRun = $this->lastRunTime($id);
            if ($maintenanceStartedAt !== null && $lastRun >= $maintenanceStartedAt) {
                continue;
            }

            $minIntervalInSec = max(0, (int)($options['syncIntervalInMin'] ?? 0)) * 60;
            if ($minIntervalInSec > 0 && (time() - $lastRun) < $minIntervalInSec) {
                continue;
            }
            if (!$this->claim($id)) {
                continue;
            }
            $due[$id] = $options;
        }

        return $due;
    }

    /**
     * Sync the given sources in this process and record what happened.
     *
     * @param array<string, array<string, mixed>> $sources
     */
    public function run(array $sources): void
    {
        if (empty($sources)) {
            return;
        }
        $importerManager = $this->services->get(ImporterManager::class);
        foreach ($sources as $id => $options) {
            ob_start();
            try {
                $result = $importerManager->syncSource((string)$id, $options);
            } catch (\Throwable $ex) {
                $result = 'Erreur : ' . $ex->getMessage();
            }
            $this->recordRun((string)$id, trim(ob_get_clean() . "\n" . $result));
        }
    }

    /**
     * Record a source's sync outcome, whoever ran it -- the console command and the admin page report theirs too, so "when did this last sync" has one answer rather than one per trigger.
     */
    public function recordRun(string $source, string $output): void
    {
        $file = $this->stateFile($source);
        if ($file === null) {
            return;
        }
        if (strlen($output) > self::MAX_LOG_LENGTH) {
            $output = '[...]' . substr($output, -self::MAX_LOG_LENGTH);
        }
        @file_put_contents($file, $output);
    }

    /**
     * What the last sync of $source did, or null if it never ran.
     *
     * @return array{time: int, output: string}|null
     */
    public function getLastSync(string $source): ?array
    {
        $file = $this->stateFile($source);
        if ($file === null || !is_file($file)) {
            return null;
        }

        return [
            'time' => (int)filemtime($file),
            'output' => (string)file_get_contents($file),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dataSources(): array
    {
        $dataSources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];

        return is_array($dataSources) ? $dataSources : [];
    }

    /** Hand one source's import to a console process. */
    private function spawnSync(string $source): bool
    {
        try {
            return $this->services->get(ConsoleService::class)
                ->startConsoleAsync('importer:sync', ['-s', $source]) !== null;
        } catch (\Throwable $unavailable) {
            return false;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $dueSources
     */
    private function runAfterResponse(array $dueSources): void
    {
        @ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        @set_time_limit(0);
        try {
            $this->run($dueSources);
        } catch (\Throwable $ex) {
        }
    }

    private function lastRunTime(string $source): int
    {
        $file = $this->stateFile($source);
        $time = ($file !== null && is_file($file)) ? @filemtime($file) : false;

        return $time === false ? 0 : (int)$time;
    }

    /**
     * Take this source's slot by stamping its state file now, before the import itself (which happens elsewhere, and may take minutes): the point of the stamp is that no other request starts the same import meanwhile.
     */
    private function claim(string $source): bool
    {
        $file = $this->stateFile($source);
        if ($file === null) {
            return false;
        }
        if (!is_file($file)) {
            return @file_put_contents($file, '') !== false;
        }

        return @touch($file);
    }

    /**
     * Path of $source's state file, or null when the directory it belongs in cannot be created (a read-only cache directory just means no automatic sync on this wiki).
     */
    private function stateFile(string $source): ?string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $source);
        if ($name === '' || $name === null) {
            return null;
        }
        $dir = $this->cachePath() . '/' . self::STATE_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        return $dir . '/' . $name . '.log';
    }

    private function cachePath(): string
    {
        $attachConfig = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $attachConfig = is_array($attachConfig) ? $attachConfig : [];
        $path = !empty($attachConfig['cache_path']) ? $attachConfig['cache_path'] : 'cache';

        return rtrim((string)$path, '/');
    }
}

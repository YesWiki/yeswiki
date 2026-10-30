<?php

namespace YesWiki\Import\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Service\Storage;

/**
 * What the last sync of each data source did: the log under the cache directory that the importers page shows and that ImportSchedule counts the interval of a source from.
 *
 * Whoever syncs a source records the outcome here -- the scheduled task, the console command, the admin page and the API route -- so that "when did this last sync" has one answer rather than one per trigger.
 */
class SyncScheduler
{
    /** Where the last sync of each source is recorded, under the cache directory. */
    private const STATE_DIR = 'importer';

    /** A run's kept output, tail first: a log nobody rotates must not grow forever. */
    private const MAX_LOG_LENGTH = 20000;

    protected ParameterBagInterface $params;
    protected Storage $storage;

    public function __construct(ParameterBagInterface $params, Storage $storage)
    {
        $this->params = $params;
        $this->storage = $storage;
    }

    /** Record a source's sync outcome, well or badly: the log's modification time is when it last ran. */
    public function recordRun(string $source, string $output): void
    {
        $file = $this->stateFile($source);
        if ($file === null) {
            return;
        }
        if (strlen($output) > self::MAX_LOG_LENGTH) {
            $output = '[...]' . substr($output, -self::MAX_LOG_LENGTH);
        }
        $this->storage->storeDerived($file, $output);
    }

    /**
     * What the last sync of $source did, or null if it never ran.
     *
     * @return array{time: int, output: string}|null
     */
    public function getLastSync(string $source): ?array
    {
        $time = $this->lastSyncTime($source);
        if ($time === null) {
            return null;
        }

        return [
            'time' => $time,
            'output' => $this->storage->read((string)$this->stateFile($source)),
        ];
    }

    /** When $source last synced, as a Unix time, or null if it never ran: what the schedule needs, without reading the log. */
    public function lastSyncTime(string $source): ?int
    {
        $file = $this->stateFile($source);
        if ($file === null || !$this->storage->fileExists($file)) {
            return null;
        }

        return $this->storage->lastModified($file);
    }

    /** Path of $source's state file, or null when the source's name leaves nothing to make one from. */
    private function stateFile(string $source): ?string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $source);
        if ($name === '' || $name === null) {
            return null;
        }

        return $this->cachePath() . '/' . self::STATE_DIR . '/' . $name . '.log';
    }

    private function cachePath(): string
    {
        $attachConfig = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $attachConfig = is_array($attachConfig) ? $attachConfig : [];
        $path = !empty($attachConfig['cache_path']) ? $attachConfig['cache_path'] : 'cache';

        return rtrim((string)$path, '/');
    }
}

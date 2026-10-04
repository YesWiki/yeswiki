<?php

namespace YesWiki\Admin\Service;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;

/** Fetches a backup from another wiki into this one's backups, one polled step per request, for the admin backups screen. */
class RemoteBackupService
{
    public const JOB_FILENAME = 'remote-backup.json';

    public const SLICE_SECONDS = 20;

    public const BUSY_FOR = 60;

    public const STEP_IDLE = 'idle';

    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
        private readonly RemoteWikiArchive $remote,
    ) {
    }

    /**
     * Sign in to the remote wiki and keep what the next steps need; the password is not kept.
     *
     * @param array<string, mixed> $params what to ask the remote for on top of a full archive
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when a fetch is already running, this wiki is busy, or the remote refuses
     */
    public function start(string $url, string $username, string $password, array $params = []): array
    {
        if ($this->readJob() !== []) {
            throw new \Exception('A remote backup is already running. Give it up before starting another one.');
        }
        if ($this->archiveService->isReadOnly()) {
            throw new \Exception(_t('ADMIN_BACKUPS_REMOTE_WIKI_BUSY'));
        }

        $job = $this->remote->start($url, $username, $password, $params);
        $job['part'] = $this->partPath();
        $job['busySince'] = 0;
        $this->writeJob($job);

        return $this->state($job);
    }

    /**
     * Where the running fetch is, without moving it.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $job = $this->readJob();

        return $job === [] ? ['step' => self::STEP_IDLE, 'running' => false] : $this->state($job);
    }

    /**
     * Move the running fetch one step further, then say where it is.
     *
     * @return array<string, mixed>
     */
    public function advance(): array
    {
        $job = $this->readJob();
        if ($job === []) {
            return ['step' => self::STEP_IDLE, 'running' => false];
        }
        if ($job['step'] === RemoteWikiArchive::STEP_DOWNLOADING) {
            if (time() - (int)($job['busySince'] ?? 0) < self::BUSY_FOR) {
                return $this->state($job);
            }
            $job['busySince'] = time();
            $this->writeJob($job);
            if (function_exists('set_time_limit')) {
                @set_time_limit(self::SLICE_SECONDS + RemoteWikiArchive::REQUEST_MAX_DURATION + 60);
            }
        }

        try {
            $job = $this->remote->advance($job, \dirname((string)$job['part']), (string)$job['part'], self::SLICE_SECONDS);
            $job['busySince'] = 0;
            if ($job['step'] !== RemoteWikiArchive::STEP_DOWNLOADING && empty($job['stored']) && $this->localFiles->isFile((string)$job['part'])) {
                $job['filename'] = $this->store($job);
                $job['stored'] = true;
            }
        } catch (\Throwable $th) {
            $this->abandon($job);

            return ['step' => self::STEP_IDLE, 'running' => false, 'error' => $th->getMessage()];
        }

        if ($job['step'] === RemoteWikiArchive::STEP_DONE) {
            $this->deleteJob();
        } else {
            $this->writeJob($job);
        }

        return $this->state($job);
    }

    /**
     * Give up, and take the remote archive and the half-downloaded file with it.
     *
     * @return array<string, mixed>
     */
    public function cancel(): array
    {
        $job = $this->readJob();
        if ($job !== []) {
            $this->abandon($job);
        }

        return ['step' => self::STEP_IDLE, 'running' => false];
    }

    /** @param array<string, mixed> $job */
    private function abandon(array $job): void
    {
        $this->remote->abandon($job);
        if (!empty($job['part'])) {
            $this->localFiles->remove((string)$job['part']);
        }
        $this->deleteJob();
    }

    /**
     * Put the downloaded archive among this wiki's backups, under a name nothing else has.
     *
     * @param array<string, mixed> $job
     */
    private function store(array $job): string
    {
        $folder = $this->archiveService->getPrivateFolder();
        $filename = (string)$job['filename'];
        while ($this->storage->fileExists("$folder/$filename")) {
            $filename = (string)preg_replace_callback(
                '/^(\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-)(\d{2})/',
                static fn (array $matches): string => $matches[1] . str_pad((string)(((int)$matches[2] + 1) % 60), 2, '0', STR_PAD_LEFT),
                $filename
            );
        }

        $part = (string)$job['part'];
        if ($this->storage->isRemote("$folder/$filename")) {
            $this->storage->writeFrom("$folder/$filename", $part);
            $this->localFiles->remove($part);
        } elseif (!$this->localFiles->rename($part, $this->storage->absolutePath("$folder/$filename"))) {
            throw new \Exception('Cannot move the downloaded backup into the backups folder.');
        }

        return $filename;
    }

    /** A file to download into that survives between requests: beside the backups when they are on this disk. */
    private function partPath(): string
    {
        $folder = $this->archiveService->getPrivateFolder();
        $name = 'remote-' . bin2hex(random_bytes(6)) . '.part';
        if ($this->storage->isRemote("$folder/$name")) {
            return rtrim(sys_get_temp_dir(), '/') . "/yeswiki-$name";
        }

        return $this->storage->absolutePath("$folder/$name");
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function state(array $job): array
    {
        $bytes = (int)($job['bytes'] ?? 0);
        if ($job['step'] === RemoteWikiArchive::STEP_DOWNLOADING && !empty($job['part'])) {
            $bytes = $this->localFiles->size((string)$job['part']);
        }

        return [
            'step' => $job['step'],
            'running' => $job['step'] !== RemoteWikiArchive::STEP_DONE,
            'source' => $job['baseUrl'] ?? '',
            'filename' => $job['filename'] ?? '',
            'bytes' => $bytes,
            'total' => (int)($job['total'] ?? 0),
            'output' => $job['output'] ?? '',
            'warning' => $job['warning'] ?? '',
        ];
    }

    private function jobPath(): string
    {
        return $this->archiveService->getPrivateFolder() . '/' . self::JOB_FILENAME;
    }

    /** @return array<string, mixed> */
    private function readJob(): array
    {
        if (!$this->storage->fileExists($this->jobPath())) {
            return [];
        }
        $job = json_decode($this->storage->read($this->jobPath()), true);

        return is_array($job) && isset($job['step']) ? $job : [];
    }

    /** @param array<string, mixed> $job */
    private function writeJob(array $job): void
    {
        $this->storage->write($this->jobPath(), (string)json_encode($job));
    }

    private function deleteJob(): void
    {
        if ($this->storage->fileExists($this->jobPath())) {
            $this->storage->delete($this->jobPath());
        }
    }
}

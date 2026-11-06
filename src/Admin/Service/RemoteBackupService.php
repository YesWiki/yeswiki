<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Message\AdvanceRemoteBackup;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\Queue\QueueStatistics;

/**
 * Fetches a backup from another wiki into this one's backups, for the admin backups screen (spec 9.6): a job file the
 * queue moves one step or download slice per AdvanceRemoteBackup; the screen starts it, polls status(), may cancel it.
 * Once over, the job file keeps how it ended (the backup stored, or why it failed) until the next start() replaces it.
 */
class RemoteBackupService
{
    public const JOB_FILENAME = 'remote-backup.json';

    public const SLICE_SECONDS = 20;

    public const RETRY_PAUSE_SECONDS = 30;

    public const STEP_IDLE = 'idle';

    private const WAITING_STEPS = [RemoteWikiArchive::STEP_ARCHIVING, RemoteWikiArchive::STEP_IDENTIFYING];

    public const STEP_LOCK = 'cache/remote-backup.lock';

    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
        private readonly RemoteWikiArchive $remote,
        private readonly MessageBusInterface $bus,
        private readonly QueueStatistics $queueStatistics,
        private readonly RuntimeLock $runtimeLock = new RuntimeLock(),
    ) {
    }

    /**
     * Sign in to the remote wiki, keep what the next steps need, and queue the first one; the password is not kept.
     *
     * @param array<string, mixed> $params what to ask the remote for on top of a full archive
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when a fetch is already running, this wiki is busy, or the remote refuses
     */
    public function start(string $url, string $username, string $password, array $params = []): array
    {
        $previous = $this->readJob();
        if (self::isRunning($previous) && !$this->hasStopped($previous)) {
            throw new \Exception('A remote backup is already running. Give it up before starting another one.');
        }
        if ($this->archiveService->isReadOnly()) {
            throw new \Exception(_t('ADMIN_BACKUPS_REMOTE_WIKI_BUSY'));
        }

        $job = $this->remote->start($url, $username, $password, $params);
        if (self::isRunning($previous)) {
            $this->forget($previous);
        }
        $job['part'] = $this->partPath();
        $this->writeJob($job);
        if (!$this->queueNextStep()) {
            $this->abandon($job);

            throw new \Exception('The background queue could not take the fetch: see the Journal.');
        }

        return $this->status();
    }

    /**
     * Where the fetch is, without moving it: running, or how the last one ended.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $job = $this->readJob();
        if ($job === []) {
            return ['step' => self::STEP_IDLE, 'running' => false];
        }
        if (isset($job['error'])) {
            return ['step' => self::STEP_IDLE, 'running' => false, 'error' => (string)$job['error']];
        }
        if ($this->hasStopped($job)) {
            return ['running' => false, 'error' => _t('ADMIN_BACKUPS_REMOTE_STOPPED')] + $this->state($job);
        }

        return $this->state($job);
    }

    /**
     * Move the running fetch one step further, then say where it is (`error` when the step failed): an AdvanceRemoteBackup.
     *
     * @return array<string, mixed>
     */
    public function advance(): array
    {
        $lock = $this->runtimeLock->acquire(self::STEP_LOCK);
        if ($lock === null) {
            throw new \RuntimeException('The remote backup lock ' . self::STEP_LOCK . ' cannot be created');
        }
        if (!$this->runtimeLock->tryLock($lock)) {
            $this->runtimeLock->release($lock);

            return ['step' => self::STEP_IDLE, 'running' => false];
        }

        try {
            return $this->advanceLocked();
        } finally {
            $this->runtimeLock->release($lock);
        }
    }

    /** @return array<string, mixed> */
    private function advanceLocked(): array
    {
        $job = $this->readJob();
        if (!self::isRunning($job)) {
            return ['step' => self::STEP_IDLE, 'running' => false];
        }

        $slice = self::SLICE_SECONDS;
        $previousLimit = null;
        if ($job['step'] === RemoteWikiArchive::STEP_DOWNLOADING && function_exists('set_time_limit')) {
            $previousLimit = (int)ini_get('max_execution_time');
            @set_time_limit($slice + RemoteWikiArchive::REQUEST_MAX_DURATION + 60);
        }

        try {
            $job = $this->remote->advance($job, \dirname((string)$job['part']), (string)$job['part'], $slice);
            if (!$this->isCurrent($job)) {
                $this->forget($job);

                return ['step' => self::STEP_IDLE, 'running' => false];
            }
            if ($job['step'] !== RemoteWikiArchive::STEP_DOWNLOADING && empty($job['stored']) && $this->localFiles->isFile((string)$job['part'])) {
                $job['filename'] = $this->store($job);
                $job['stored'] = true;
            }
        } catch (\Throwable $th) {
            $this->abandon($job, $th->getMessage());

            return ['step' => self::STEP_IDLE, 'running' => false, 'error' => $th->getMessage()];
        } finally {
            if ($previousLimit !== null) {
                @set_time_limit($previousLimit);
            }
        }

        $this->writeJob($job);

        return $this->state($job);
    }

    /** Queues the running fetch's next step: at once, or after a pause while the remote writes its archive or after a failed slice. */
    public function queueNextStep(): bool
    {
        $job = $this->readJob();
        if (!self::isRunning($job)) {
            return false;
        }

        $stamps = [];
        $pause = max(
            in_array($job['step'], self::WAITING_STEPS, true) ? RemoteWikiArchive::SETTLE_SECONDS : 0,
            (int)($job['failures'] ?? 0) * self::RETRY_PAUSE_SECONDS
        );
        if ($pause > 0) {
            $stamps[] = new DelayStamp($pause * 1000);
        }
        $id = $this->bus->dispatch(new AdvanceRemoteBackup(), $stamps)->last(TransportMessageIdStamp::class)?->getId();
        if ($id === null) {
            return false;
        }

        $job = $this->readJob();
        if (self::isRunning($job)) {
            $job['message'] = (int)$id;
            $this->writeJob($job);
        }

        return true;
    }

    /**
     * Give up, and take the remote archive and the half-downloaded file with it.
     *
     * @return array<string, mixed>
     */
    public function cancel(): array
    {
        $job = $this->readJob();
        if (self::isRunning($job)) {
            $this->abandon($job);
        } elseif ($job !== []) {
            $this->deleteJob();
        }

        return ['step' => self::STEP_IDLE, 'running' => false];
    }

    /**
     * Tidy up after the job and close it: the job file goes, or keeps the reason when it failed.
     *
     * @param array<string, mixed> $job
     */
    private function abandon(array $job, ?string $error = null): void
    {
        $this->forget($job);
        if (!$this->isCurrent($job)) {
            return;
        }
        if ($error === null) {
            $this->deleteJob();
        } else {
            $this->writeJob(['step' => self::STEP_IDLE, 'error' => $error, 'baseUrl' => $job['baseUrl'] ?? '']);
        }
    }

    /**
     * Leave nothing of the job behind, here or on the remote wiki, without touching the job file.
     *
     * @param array<string, mixed> $job
     */
    private function forget(array $job): void
    {
        $this->remote->abandon($job);
        if (!empty($job['part'])) {
            $this->localFiles->remove((string)$job['part']);
        }
    }

    /**
     * Whether $job is still the fetch the job file holds: not given up nor replaced since it was read.
     *
     * @param array<string, mixed> $job
     */
    private function isCurrent(array $job): bool
    {
        $current = $this->readJob();

        return self::isRunning($current) && ($current['part'] ?? null) === ($job['part'] ?? null);
    }

    /**
     * Whether the job has steps left to take, rather than being over, in success or failure.
     *
     * @param array<string, mixed> $job
     */
    private static function isRunning(array $job): bool
    {
        return $job !== [] && !isset($job['error']) && $job['step'] !== RemoteWikiArchive::STEP_DONE;
    }

    /**
     * Whether the last step queued for the job died or failed without the job hearing of it: no step will follow.
     *
     * @param array<string, mixed> $job
     */
    private function hasStopped(array $job): bool
    {
        $messageId = $job['message'] ?? null;
        if (!is_int($messageId)) {
            return false;
        }
        $state = $this->queueStatistics->messageState($messageId);

        return $state === 'failed' || $state === 'killed';
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

    /** A file to download into that survives between messages: beside the backups when they are on this disk. */
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

<?php

namespace YesWiki\Core\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a full backup from another YesWiki and leaves nothing behind on it.
 *
 * The work is cut into steps a single HTTP request can finish, because the archive of a large
 * wiki takes minutes and no web server waits that long. The browser calls status() until done.
 */
class RemoteBackupService
{
    public const JOB_FILENAME = 'remote-backup.json';
    public const PART_SUFFIX = '.part';
    public const STEP_ARCHIVING = 'archiving';
    public const STEP_IDENTIFYING = 'identifying';
    public const STEP_DOWNLOADING = 'downloading';
    public const STEP_CLEANING = 'cleaning';
    public const STEP_DONE = 'done';
    public const STEP_IDLE = 'idle';
    protected const REQUEST_TIMEOUT = 30;
    protected const RESUMABLE_SLICE_SECONDS = 20;
    protected const DOWNLOAD_STALLED_AFTER = 60;
    protected const PROGRESS_EVERY_SECONDS = 1;
    protected const DOWNLOAD_ATTEMPTS = 5;
    protected const ARCHIVE_START_GRACE = 30;
    protected const ARCHIVE_SETTLE_SECONDS = 10;
    protected const IDENTIFY_TIMEOUT = 1800;

    protected $archiveService;
    protected $client;

    public function __construct(ArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    /**
     * Log in to the remote wiki and ask it for a full archive.
     *
     * @throws \Exception
     */
    public function start(string $url, string $username, string $password): array
    {
        if (!empty($this->readJob())) {
            throw new \Exception('A remote backup is already running. Cancel it before starting another one.');
        }
        $baseUrl = $this->baseUrl($url);
        if (empty($username) || empty($password)) {
            throw new \Exception('The administrator name and password of the remote wiki are both needed.');
        }

        $cookie = $this->login($baseUrl, $username, $password);
        $remote = $this->assertRemoteCanArchive($baseUrl, $cookie);
        $this->assertLocalSpace((int)($remote['estimatedSize'] ?? 0));

        $job = [
            'baseUrl' => $baseUrl,
            'cookie' => $cookie,
            'knownArchives' => array_column($this->remoteArchives($baseUrl, $cookie), 'filename'),
            'remoteUid' => $this->startRemoteArchive($baseUrl, $cookie),
            'step' => self::STEP_ARCHIVING,
            'startedAt' => time(),
            'sawRunning' => false,
            'filename' => '',
            'total' => 0,
            'bytes' => 0,
            'resumable' => true,
            'downloadingSince' => 0,
            'output' => '',
        ];
        $this->writeJob($job);

        return $this->state($job);
    }

    /**
     * Move the running job one step further, then describe it.
     */
    public function status(): array
    {
        $job = $this->readJob();
        if (empty($job)) {
            return ['step' => self::STEP_IDLE, 'running' => false];
        }

        try {
            switch ($job['step']) {
                case self::STEP_ARCHIVING:
                    $job = $this->pollRemoteArchive($job);
                    break;
                case self::STEP_IDENTIFYING:
                    $job = $this->identifyArchive($job);
                    break;
                case self::STEP_DOWNLOADING:
                    $job = $this->download($job);
                    break;
                case self::STEP_CLEANING:
                    $job = $this->cleanRemote($job);
                    break;
            }
        } catch (\Throwable $throwable) {
            $this->abandon($job);

            return ['step' => self::STEP_IDLE, 'running' => false, 'error' => $throwable->getMessage()];
        }

        if ($job['step'] === self::STEP_DONE) {
            $this->deleteJob();
        } else {
            $this->writeJob($job);
        }

        return $this->state($job);
    }

    /**
     * Give up, and take the remote archive and the half-downloaded file with it.
     */
    public function cancel(): array
    {
        $job = $this->readJob();
        if (empty($job)) {
            return ['step' => self::STEP_IDLE, 'running' => false];
        }
        $this->abandon($job);

        return ['step' => self::STEP_IDLE, 'running' => false];
    }

    /**
     * Drop the job, and take with it whatever it left on either side.
     *
     * @param array<string,mixed> $job
     */
    protected function abandon(array $job): void
    {
        if (!empty($job['filename'])) {
            $partPath = $this->localPath($job['filename']) . self::PART_SUFFIX;
            if (file_exists($partPath)) {
                @unlink($partPath);
            }
        }
        try {
            if (!empty($job['remoteFilename'])) {
                $this->deleteRemoteArchive($job);
            } elseif ($job['step'] === self::STEP_ARCHIVING) {
                $this->call($job['baseUrl'], 'api/archives', $job['cookie'], [
                    'action' => 'stopArchive',
                    'uid' => $job['remoteUid'],
                ]);
            }
        } catch (\Throwable $throwable) {
        }
        $this->deleteJob();
    }

    protected function pollRemoteArchive(array $job): array
    {
        $status = $this->call($job['baseUrl'], "api/archives/uidstatus/{$job['remoteUid']}", $job['cookie']);
        $job['output'] = is_string($status['output'] ?? null) ? $status['output'] : '';

        if (!empty($status['finished'])) {
            $job['step'] = self::STEP_IDENTIFYING;

            return $job;
        }
        if (!empty($status['stopped'])) {
            throw new \Exception('The backup was stopped on the remote wiki.');
        }
        if (empty($status['started'])) {
            if (!empty($job['sawRunning'])) {
                $job['step'] = self::STEP_IDENTIFYING;

                return $job;
            }
            if (time() - (int)$job['startedAt'] > self::ARCHIVE_START_GRACE) {
                throw new \Exception('The remote wiki never started the backup it was asked to make.');
            }

            return $job;
        }
        $job['sawRunning'] = true;

        return $job;
    }

    /**
     * A zip grows on disk while it is written, so the new archive is taken only once its size settles.
     */
    protected function identifyArchive(array $job): array
    {
        $job['identifyingSince'] = (int)($job['identifyingSince'] ?? time());
        $candidate = null;
        foreach ($this->remoteArchives($job['baseUrl'], $job['cookie']) as $archive) {
            if (($archive['type'] ?? '') === 'full' && !in_array($archive['filename'], $job['knownArchives'], true)) {
                $candidate = $archive;
                break;
            }
        }

        if (is_null($candidate)) {
            if (time() - $job['identifyingSince'] > self::IDENTIFY_TIMEOUT) {
                throw new \Exception('The remote wiki produced no backup.');
            }

            return $job;
        }

        $size = (int)($candidate['size'] ?? 0);
        if (($job['candidate'] ?? '') !== $candidate['filename'] || ($job['candidateSize'] ?? -1) !== $size) {
            $job['candidate'] = $candidate['filename'];
            $job['candidateSize'] = $size;
            $job['candidateSince'] = time();

            return $job;
        }
        if ($size === 0 || time() - (int)$job['candidateSince'] < self::ARCHIVE_SETTLE_SECONDS) {
            return $job;
        }

        $this->assertLocalSpace((int)($size * 1.05));

        $job['filename'] = $this->freeLocalName($this->nameForSource($candidate['filename'], $job['baseUrl']));
        $job['remoteFilename'] = $candidate['filename'];
        $job['total'] = $size;
        $job['step'] = self::STEP_DOWNLOADING;

        return $job;
    }

    protected function download(array $job): array
    {
        $partPath = $this->localPath($job['filename']) . self::PART_SUFFIX;
        $job['bytes'] = file_exists($partPath) ? (int)filesize($partPath) : 0;

        $busy = (time() - (int)$job['downloadingSince']) < self::DOWNLOAD_STALLED_AFTER;
        if ($busy) {
            return $job;
        }

        $offset = $job['resumable'] ? $job['bytes'] : 0;
        $response = $this->client()->request(
            'GET',
            $this->apiUrl($job['baseUrl'], "api/archives/{$job['remoteFilename']}"),
            [
                'headers' => ['Cookie' => $job['cookie'], 'Range' => "bytes=$offset-"],
                'buffer' => false,
                'timeout' => self::REQUEST_TIMEOUT,
                'max_duration' => 0,
            ]
        );
        $code = $response->getStatusCode();
        if ($code !== 200 && $code !== 206) {
            throw new \Exception("The remote wiki refused to send the backup file (HTTP $code).");
        }

        $acceptRanges = strtolower($response->getHeaders(false)['accept-ranges'][0] ?? '');
        $job['resumable'] = $code === 206 || $acceptRanges === 'bytes';
        $handle = fopen($partPath, $code === 206 && $offset > 0 ? 'ab' : 'wb');
        if ($handle === false) {
            throw new \Exception('Cannot write the downloaded backup into the backups folder.');
        }

        $deadline = $job['resumable'] ? microtime(true) + self::RESUMABLE_SLICE_SECONDS : 0;
        $nextProgress = microtime(true) + self::PROGRESS_EVERY_SECONDS;
        $job['downloadingSince'] = time();
        $this->writeJob($job);
        $complete = false;
        $written = 0;

        try {
            foreach ($this->client()->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }
                if ($chunk->isLast()) {
                    $complete = true;
                    break;
                }
                $written += fwrite($handle, $chunk->getContent());
                if (microtime(true) > $nextProgress) {
                    $nextProgress = microtime(true) + self::PROGRESS_EVERY_SECONDS;
                    $job['bytes'] = $offset + $written;
                    $job['downloadingSince'] = time();
                    $this->writeJob($job);
                }
                if ($deadline > 0 && microtime(true) > $deadline) {
                    $response->cancel();
                    break;
                }
            }
            $job['failures'] = 0;
        } catch (\Throwable $throwable) {
            $job = $this->downloadFailed($job, $throwable->getMessage());
        } finally {
            $job['bytes'] = $offset + $written;
            fclose($handle);
            $job['downloadingSince'] = 0;
        }

        if ($complete || ($job['total'] > 0 && $job['bytes'] >= $job['total'])) {
            $zip = new \ZipArchive();
            if ($zip->open($partPath) !== true) {
                @unlink($partPath);

                return $this->downloadFailed($job, 'The downloaded file is not a readable zip archive.');
            }
            $zip->close();
            if (!rename($partPath, $this->localPath($job['filename']))) {
                throw new \Exception('Cannot move the downloaded backup into the backups folder.');
            }
            $job['step'] = self::STEP_CLEANING;
        }

        return $job;
    }

    /**
     * @param array<string,mixed> $job
     *
     * @return array<string,mixed>
     *
     * @throws \Exception once the attempts are spent
     */
    protected function downloadFailed(array $job, string $message): array
    {
        $job['failures'] = (int)($job['failures'] ?? 0) + 1;
        $job['error'] = $message;
        if ($job['failures'] >= self::DOWNLOAD_ATTEMPTS) {
            throw new \Exception("The backup could not be downloaded: $message");
        }

        return $job;
    }

    protected function cleanRemote(array $job): array
    {
        $this->deleteRemoteArchive($job);
        $job['step'] = self::STEP_DONE;

        return $job;
    }

    protected function deleteRemoteArchive(array $job): void
    {
        if (empty($job['remoteFilename'])) {
            return;
        }
        $this->call(
            $job['baseUrl'],
            "api/archives/{$job['remoteFilename']}",
            $job['cookie'],
            ['action' => 'delete']
        );
    }

    protected function login(string $baseUrl, string $username, string $password): string
    {
        $response = $this->client()->request('POST', $this->apiUrl($baseUrl, 'api/login'), [
            'body' => ['username' => $username, 'password' => $password],
            'timeout' => self::REQUEST_TIMEOUT,
        ]);
        $code = $response->getStatusCode();
        try {
            $data = $code === 200 ? $response->toArray(false) : [];
        } catch (\Throwable $throwable) {
            $data = [];
        }
        if ($code === 401) {
            throw new \Exception('The remote wiki refused these credentials.');
        }
        if ($code !== 200 || empty($data['user'])) {
            throw new \Exception("This address does not answer as a YesWiki (HTTP $code on api/login).");
        }
        if (empty($data['isAdmin'])) {
            throw new \Exception("'{$data['user']}' is not an administrator of the remote wiki.");
        }

        $cookies = [];
        foreach ($response->getHeaders(false)['set-cookie'] ?? [] as $setCookie) {
            $cookies[] = trim(explode(';', $setCookie)[0]);
        }
        if (empty($cookies)) {
            throw new \Exception('The remote wiki opened no session.');
        }

        return implode('; ', $cookies);
    }

    /**
     * @return array<string,mixed> the remote's archiving status, with the size it expects when it is recent enough to say
     *
     * @throws \Exception
     */
    protected function assertRemoteCanArchive(string $baseUrl, string $cookie): array
    {
        $status = $this->call($baseUrl, 'api/archives/archivingStatus/', $cookie);
        if (isset($status['canExec']) && !$status['canExec']) {
            throw new \Exception('The remote wiki cannot run a backup in the background, so it cannot be fetched from here.');
        }
        if (!empty($status['canArchive'])) {
            return $status;
        }
        if (!empty($status['archiving'])) {
            throw new \Exception('The remote wiki is already making a backup.');
        }
        if (isset($status['enoughSpace']) && !$status['enoughSpace']) {
            throw new \Exception('The remote wiki has not enough free space to make its backup' . $this->spaceDetail((int)($status['estimatedSize'] ?? 0), $status['freeSpace'] ?? null) . '.');
        }

        throw new \Exception('The remote wiki cannot make a backup right now.');
    }

    /**
     * @throws \Exception when the backups folder cannot hold that many bytes
     */
    protected function assertLocalSpace(int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $free = $this->localFreeSpace();
        if (!is_null($free) && $free < $bytes) {
            throw new \Exception('Not enough free space here to download the backup' . $this->spaceDetail($bytes, $free) . '.');
        }
    }

    protected function localFreeSpace(): ?int
    {
        $free = @disk_free_space($this->archiveService->getPrivateFolder());

        return $free === false ? null : (int)$free;
    }

    protected function spaceDetail(int $needed, $free): string
    {
        if ($needed <= 0 || !is_numeric($free)) {
            return '';
        }

        return ' (' . $this->humanSize($needed) . ' needed, ' . $this->humanSize((int)$free) . ' free)';
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string)$bytes : number_format($value, 1)) . ' ' . $units[$unit];
    }

    protected function startRemoteArchive(string $baseUrl, string $cookie): string
    {
        $data = $this->call($baseUrl, 'api/archives', $cookie, [
            'action' => 'startArchive',
            'params' => ['savefiles' => '1', 'savedatabase' => '1'],
            'callAsync' => '1',
        ]);
        if (empty($data['uid'])) {
            throw new \Exception('The remote wiki did not start the backup.');
        }

        return (string)$data['uid'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function remoteArchives(string $baseUrl, string $cookie): array
    {
        $archives = $this->call($baseUrl, 'api/archives', $cookie);

        return array_values(array_filter($archives, 'is_array'));
    }

    /**
     * @param array<string,mixed> $post
     *
     * @return array<mixed>
     */
    protected function call(string $baseUrl, string $path, string $cookie, ?array $post = null): array
    {
        $options = [
            'headers' => ['Cookie' => $cookie],
            'timeout' => self::REQUEST_TIMEOUT,
        ];
        if (!is_null($post)) {
            $options['body'] = $post;
        }
        $response = $this->client()->request(is_null($post) ? 'GET' : 'POST', $this->apiUrl($baseUrl, $path), $options);
        $code = $response->getStatusCode();
        if ($code === 401 || $code === 403) {
            throw new \Exception('The remote wiki closed the session before the backup was fetched.');
        }
        try {
            $data = $response->toArray(false);
        } catch (\Throwable $throwable) {
            throw new \Exception("The remote wiki did not answer as an API on '$path' (HTTP $code).");
        }
        if ($code !== 200) {
            throw new \Exception("The remote wiki answered HTTP $code on '$path': " . ($data['error'] ?? 'no detail'));
        }

        return $data;
    }

    /**
     * The address of a wiki, whatever part of it was pasted in.
     */
    public function baseUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = "https://$url";
        }
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            throw new \Exception('This is not a valid wiki address.');
        }
        $path = preg_replace('#/(index|wakka)\.php$#i', '', $parts['path'] ?? '');
        $port = empty($parts['port']) ? '' : ":{$parts['port']}";
        $baseUrl = strtolower($parts['scheme']) . "://{$parts['host']}$port" . rtrim($path, '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('This is not a valid wiki address.');
        }

        return $baseUrl;
    }

    protected function apiUrl(string $baseUrl, string $path): string
    {
        return "$baseUrl/?$path";
    }

    protected function client(): HttpClientInterface
    {
        if (is_null($this->client)) {
            $this->client = HttpClient::create(['max_redirects' => 3]);
        }

        return $this->client;
    }

    protected function localPath(string $filename): string
    {
        return $this->archiveService->getPrivateFolder() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * A wiki names its backups after itself; an older one that does not gets named after the
     * address it was fetched from.
     */
    protected function nameForSource(string $filename, string $baseUrl): string
    {
        $parts = ArchiveFilename::parse($filename);

        return empty($parts['source']) ? ArchiveFilename::withSource($filename, $baseUrl) : $filename;
    }

    /**
     * Two wikis archived in the same second would otherwise land on the same name.
     */
    protected function freeLocalName(string $filename): string
    {
        while (file_exists($this->localPath($filename))) {
            $filename = preg_replace_callback(
                '/^(\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-)(\d{2})/',
                function ($matches) {
                    return $matches[1] . str_pad((int)$matches[2] + 1, 2, '0', STR_PAD_LEFT);
                },
                $filename
            );
        }

        return $filename;
    }

    /**
     * @param array<string,mixed> $job
     *
     * @return array<string,mixed>
     */
    protected function state(array $job): array
    {
        return [
            'step' => $job['step'],
            'running' => $job['step'] !== self::STEP_DONE,
            'filename' => $job['filename'],
            'bytes' => $job['bytes'],
            'total' => $job['total'],
            'resumable' => $job['resumable'],
            'output' => $job['output'],
            'warning' => $job['error'] ?? '',
        ];
    }

    protected function jobPath(): string
    {
        return $this->archiveService->getPrivateFolder() . DIRECTORY_SEPARATOR . self::JOB_FILENAME;
    }

    /**
     * @return array<string,mixed>
     */
    protected function readJob(): array
    {
        $path = $this->jobPath();
        if (!file_exists($path)) {
            return [];
        }
        $job = json_decode((string)file_get_contents($path), true);

        return is_array($job) ? $job : [];
    }

    /**
     * @param array<string,mixed> $job
     */
    protected function writeJob(array $job): void
    {
        file_put_contents($this->jobPath(), json_encode($job));
    }

    protected function deleteJob(): void
    {
        $path = $this->jobPath();
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

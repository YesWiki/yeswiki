<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/** Asks a remote wiki for an archive and brings it back, one short step at a time so a web request can carry each (first-class-binary 06). */
class RemoteWikiArchive
{
    public const STEP_CHECKING = 'checking';
    public const STEP_STARTING = 'starting';
    public const STEP_ARCHIVING = 'archiving';
    public const STEP_IDENTIFYING = 'identifying';
    public const STEP_DOWNLOADING = 'downloading';
    public const STEP_CLEANING = 'cleaning';
    public const STEP_DONE = 'done';

    public const REQUEST_TIMEOUT = 30;

    public const REQUEST_MAX_DURATION = 45;

    public const START_GRACE = 30;

    public const SETTLE_SECONDS = 10;

    public const IDENTIFY_TIMEOUT = 1800;

    public const DOWNLOAD_ATTEMPTS = 5;

    public const MAX_REDIRECTS = 3;

    private ?HttpClientInterface $client = null;

    /** @var callable(string): void */
    private $say;

    public function __construct(
        ?callable $say = null,
        private readonly LocalFiles $localFiles = new LocalFiles(),
        private readonly ?SsrfUrlValidator $guard = null,
    ) {
        $this->say = $say ?? static function (string $message): void {};
    }

    /** The address of a wiki, whatever part of it was pasted in. */
    public static function baseUrlOf(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $base = strtolower($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        $path = rtrim((string)($parts['path'] ?? ''), '/');
        if ($path !== '' && !str_contains(basename($path), '.')) {
            $base .= $path;
        } elseif ($path !== '') {
            $base .= rtrim(\dirname($path), '/');
        }

        return $base;
    }

    /**
     * Log in, ask for an archive, wait for it, and write it to $destination, for a caller that can wait as long as it takes.
     *
     * @throws \Exception naming what the remote wiki said, at every step
     */
    public function fetchInto(string $url, string $username, string $password, string $destination): void
    {
        $part = $destination . '.part';
        $job = $this->start($url, $username, $password);
        $this->tell('Signed in to ' . $job['baseUrl'] . ' as ' . $username);

        try {
            while ($job['step'] !== self::STEP_DONE) {
                $before = $job['step'];
                $job = $this->advance($job, \dirname($destination), $part);
                if ($job['step'] !== $before) {
                    $this->tell($this->describe($job));
                }
                if ($before === self::STEP_DOWNLOADING && $job['step'] === self::STEP_CLEANING && !$this->localFiles->rename($part, $destination)) {
                    throw new \Exception("Cannot put the archive at $destination.");
                }
                if (in_array($job['step'], [self::STEP_ARCHIVING, self::STEP_IDENTIFYING], true)) {
                    sleep(2);
                }
            }
        } catch (\Throwable $th) {
            $this->abandon($job);
            $this->localFiles->remove($part);

            throw $th;
        }
    }

    /**
     * Log in to the remote wiki and describe the job that will bring its archive here.
     *
     * @param array<string, mixed> $params what to ask the remote for on top of a full archive, as api/archives takes them
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when the address is no wiki, or the account is not one of its administrators
     */
    public function start(string $url, string $username, string $password, array $params = []): array
    {
        $baseUrl = self::baseUrlOf($url);
        if ($baseUrl === '') {
            throw new \Exception("'$url' is not an address.");
        }
        if ($username === '' || $password === '') {
            throw new \Exception('The administrator name and password of the remote wiki are both needed.');
        }
        [$baseUrl, $cookie] = $this->login($baseUrl, $username, $password);

        $params = array_merge(['savefiles' => '1', 'savedatabase' => '1'], $params);

        return [
            'baseUrl' => $baseUrl,
            'cookie' => $cookie,
            'params' => $params,
            'expectedType' => self::archiveType($params),
            'step' => self::STEP_CHECKING,
            'knownArchives' => [],
            'remoteUid' => '',
            'startedAt' => time(),
            'sawRunning' => false,
            'remoteFilename' => '',
            'filename' => '',
            'total' => 0,
            'bytes' => 0,
            'resumable' => true,
            'failures' => 0,
            'warning' => '',
            'output' => '',
        ];
    }

    /**
     * Move the job one step further.
     *
     * @param array<string, mixed> $job
     * @param string               $localFolder where the download lands, to check it has room
     * @param string               $part        the file the download is written to, across calls
     * @param int                  $slice       seconds a download may take before handing back, 0 for as long as it needs
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when the remote wiki refuses, or the job cannot go on
     */
    public function advance(array $job, string $localFolder, string $part, int $slice = 0): array
    {
        return match ($job['step']) {
            self::STEP_CHECKING => $this->check($job, $localFolder),
            self::STEP_STARTING => $this->askForArchive($job),
            self::STEP_ARCHIVING => $this->pollArchive($job),
            self::STEP_IDENTIFYING => $this->identifyArchive($job, $localFolder),
            self::STEP_DOWNLOADING => $this->download($job, $part, $slice),
            self::STEP_CLEANING => $this->clean($job),
            default => $job,
        };
    }

    /**
     * Leave nothing behind on the remote wiki: stop the archive it is making, or delete the one it made.
     *
     * @param array<string, mixed> $job
     */
    public function abandon(array $job): void
    {
        try {
            if (!empty($job['remoteFilename'])) {
                $this->deleteRemoteArchive($job);
            } elseif (($job['step'] ?? '') === self::STEP_ARCHIVING && !empty($job['remoteUid'])) {
                $this->call($job, 'api/archives', ['action' => 'stopArchive', 'uid' => $job['remoteUid']]);
            }
        } catch (\Throwable $th) {
            $this->tell('Could not tidy up on the remote wiki: ' . $th->getMessage());
        }
    }

    /**
     * How the remote names the archive these params ask for, to tell it apart from the ones it already had.
     *
     * @param array<string, mixed> $params
     */
    public static function archiveType(array $params): string
    {
        $asked = static fn (string $key): bool => in_array($params[$key] ?? null, [1, '1', true, 'true'], true);
        if (!$asked('savedatabase')) {
            return 'only_files';
        }

        return $asked('savefiles') ? 'full' : 'only_db';
    }

    /** @param array<string, mixed> $job */
    public function describe(array $job): string
    {
        return match ($job['step']) {
            self::STEP_STARTING => 'The remote wiki can make an archive',
            self::STEP_ARCHIVING => 'The remote wiki is making an archive',
            self::STEP_IDENTIFYING => 'Looking for the archive it made',
            self::STEP_DOWNLOADING => sprintf('Archive %s is %d bytes and has stopped growing', $job['remoteFilename'], $job['total']),
            self::STEP_CLEANING => 'Downloaded ' . $job['filename'],
            self::STEP_DONE => 'Removed ' . $job['remoteFilename'] . ' from the remote wiki',
            default => (string)$job['step'],
        };
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function check(array $job, string $localFolder): array
    {
        $status = $this->call($job, 'api/archives/archivingStatus');
        if (isset($status['canExec']) && !$status['canExec']) {
            throw new \Exception('The remote wiki cannot run a backup in the background, so it cannot be fetched from here.');
        }
        if (empty($status['canArchive'])) {
            if (!empty($status['archiving'])) {
                throw new \Exception('The remote wiki is already making a backup.');
            }
            if (isset($status['enoughSpace']) && !$status['enoughSpace']) {
                throw new \Exception('The remote wiki has not enough free space to make its backup' . ArchiveService::spaceDetail((int)($status['estimatedSize'] ?? 0), $status['freeSpace'] ?? null) . '.');
            }

            throw new \Exception('The remote wiki cannot make a backup right now.');
        }
        $this->assertLocalSpace($localFolder, (int)($status['estimatedSize'] ?? 0));
        $job['step'] = self::STEP_STARTING;

        return $job;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function askForArchive(array $job): array
    {
        $job['knownArchives'] = array_column($this->archives($job), 'filename');
        $data = $this->call($job, 'api/archives', [
            'action' => 'startArchive',
            'params' => $job['params'],
            'callAsync' => '1',
        ]);
        if (empty($data['uid'])) {
            throw new \Exception('The remote wiki did not start the backup.');
        }
        $job['remoteUid'] = (string)$data['uid'];
        $job['startedAt'] = time();
        $job['step'] = self::STEP_ARCHIVING;

        return $job;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function pollArchive(array $job): array
    {
        $status = $this->call($job, "api/archives/uidstatus/{$job['remoteUid']}");
        $job['output'] = is_string($status['output'] ?? null) ? $status['output'] : '';

        if (!empty($status['finished'])) {
            $job['step'] = self::STEP_IDENTIFYING;

            return $job;
        }
        if (!empty($status['stopped'])) {
            throw new \Exception('The backup was stopped on the remote wiki.');
        }
        if (!empty($status['started'])) {
            $job['sawRunning'] = true;

            return $job;
        }
        if (!empty($job['sawRunning'])) {
            $job['step'] = self::STEP_IDENTIFYING;

            return $job;
        }
        if (time() - (int)$job['startedAt'] > self::START_GRACE) {
            if ($this->newArchive($job) !== null) {
                $job['step'] = self::STEP_IDENTIFYING;

                return $job;
            }

            throw new \Exception('The remote wiki never started the backup it was asked to make.');
        }

        return $job;
    }

    /**
     * The new archive, taken once its size has held still: a zip grows on disk while it is written.
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function identifyArchive(array $job, string $localFolder): array
    {
        $job['identifyingSince'] = (int)($job['identifyingSince'] ?? time());
        $candidate = $this->newArchive($job);
        if ($candidate === null) {
            if (time() - $job['identifyingSince'] > self::IDENTIFY_TIMEOUT) {
                throw new \Exception('The remote wiki produced no backup.');
            }

            return $job;
        }

        $size = (int)($candidate['size'] ?? 0);
        if (($job['candidate'] ?? '') !== $candidate['filename'] || ($job['candidateSize'] ?? -1) !== $size) {
            $job['candidate'] = (string)$candidate['filename'];
            $job['candidateSize'] = $size;
            $job['candidateSince'] = time();

            return $job;
        }
        if ($size === 0 || time() - (int)$job['candidateSince'] < self::SETTLE_SECONDS) {
            return $job;
        }

        $this->assertLocalSpace($localFolder, (int)ceil($size * 1.05));
        $job['remoteFilename'] = (string)$candidate['filename'];
        $job['filename'] = self::nameForSource((string)$candidate['filename'], (string)$job['baseUrl'], (string)$job['expectedType']);
        $job['total'] = $size;
        $job['step'] = self::STEP_DOWNLOADING;

        return $job;
    }

    /** The local name of a fetched archive: the remote's own when it names its source, one after the address it came from otherwise. */
    public static function nameForSource(string $remoteFilename, string $baseUrl, string $type = 'full'): string
    {
        $parts = ArchiveFilename::parse($remoteFilename);
        if ($parts === []) {
            return ArchiveFilename::forNow($type, $baseUrl);
        }

        return $parts['source'] === '' ? ArchiveFilename::withSource($remoteFilename, $baseUrl) : $remoteFilename;
    }

    /**
     * One slice of the download, resumed where the last one stopped when the remote takes ranges.
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function download(array $job, string $part, int $slice): array
    {
        $offset = $job['resumable'] && $this->localFiles->isFile($part) ? $this->localFiles->size($part) : 0;
        $complete = false;
        $handle = null;

        try {
            $response = $this->request($job, 'GET', "api/archives/{$job['remoteFilename']}", [
                'headers' => ['Range' => "bytes=$offset-"],
                'buffer' => false,
                'max_duration' => 0,
            ]);
            $code = $response->getStatusCode();
            if ($code !== 200 && $code !== 206) {
                throw new \Exception("The remote wiki refused to send the backup file (HTTP $code).");
            }
            $acceptRanges = strtolower($response->getHeaders(false)['accept-ranges'][0] ?? '');
            $job['resumable'] = $code === 206 || $acceptRanges === 'bytes';
            if ($code !== 206) {
                $offset = 0;
            }
            $handle = $offset > 0 ? $this->localFiles->openForAppending($part) : $this->localFiles->openForWriting($part);
            if ($handle === null) {
                throw new \Exception("Cannot write $part.");
            }

            $deadline = $slice > 0 && $job['resumable'] ? microtime(true) + $slice : 0;
            foreach ($this->client()->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }
                if ($chunk->isLast()) {
                    $complete = true;
                    break;
                }
                fwrite($handle, $chunk->getContent());
                if ($deadline > 0 && microtime(true) > $deadline) {
                    $response->cancel();
                    break;
                }
            }
            $job['failures'] = 0;
            $job['warning'] = '';
        } catch (\Throwable $th) {
            $job['failures'] = (int)$job['failures'] + 1;
            $job['warning'] = $th->getMessage();
            if ($job['failures'] >= self::DOWNLOAD_ATTEMPTS) {
                throw new \Exception('The backup could not be downloaded: ' . $th->getMessage(), 0, $th);
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $job['bytes'] = $this->localFiles->size($part);
        if (!$complete && !($job['total'] > 0 && $job['bytes'] >= $job['total'])) {
            return $job;
        }

        $zip = new \ZipArchive();
        if ($zip->open($part) !== true) {
            $this->localFiles->remove($part);
            $job['bytes'] = 0;
            $job['failures'] = (int)$job['failures'] + 1;
            $job['warning'] = 'What came back is not a readable zip archive.';
            if ($job['failures'] >= self::DOWNLOAD_ATTEMPTS) {
                throw new \Exception($job['warning']);
            }

            return $job;
        }
        $zip->close();
        $job['step'] = self::STEP_CLEANING;

        return $job;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function clean(array $job): array
    {
        try {
            $this->deleteRemoteArchive($job);
        } catch (\Throwable $th) {
            $job['warning'] = 'Could not remove ' . $job['remoteFilename'] . ' from the remote wiki: ' . $th->getMessage();
            $this->tell($job['warning']);
        }
        $job['step'] = self::STEP_DONE;

        return $job;
    }

    /**
     * The first archive of the expected type the remote did not have before it was asked.
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>|null
     */
    private function newArchive(array $job): ?array
    {
        foreach ($this->archives($job) as $archive) {
            if (($archive['type'] ?? '') === $job['expectedType'] && !in_array($archive['filename'] ?? '', $job['knownArchives'], true)) {
                return $archive;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $job */
    private function deleteRemoteArchive(array $job): void
    {
        if (empty($job['remoteFilename'])) {
            return;
        }
        $this->call($job, 'api/archives', ['action' => 'delete', 'filesnames' => [$job['remoteFilename']]]);
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return list<array<string, mixed>>
     */
    private function archives(array $job): array
    {
        return array_values(array_filter($this->call($job, 'api/archives'), 'is_array'));
    }

    /**
     * Sign in, following the redirects a wiki sends to its canonical address, each one checked again.
     *
     * @return array{0: string, 1: string} the address that answered, and the session cookie it opened
     */
    private function login(string $baseUrl, string $username, string $password): array
    {
        for ($hop = 0;; $hop++) {
            $response = $this->request(['baseUrl' => $baseUrl, 'cookie' => ''], 'POST', 'api/login', [
                'body' => ['username' => $username, 'password' => $password],
            ]);
            try {
                $code = $response->getStatusCode();
                $location = $response->getHeaders(false)['location'][0] ?? '';
            } catch (TransportExceptionInterface $th) {
                throw new \Exception("The wiki at $baseUrl cannot be reached: " . $th->getMessage(), 0, $th);
            }
            if ($code < 300 || $code >= 400 || $location === '') {
                break;
            }
            if ($hop >= self::MAX_REDIRECTS) {
                throw new \Exception("$baseUrl redirects too many times.");
            }
            $redirected = self::baseUrlOf($location);
            if ($redirected === '' || $redirected === $baseUrl) {
                throw new \Exception("$baseUrl redirects to '$location', which is not a wiki address.");
            }
            $baseUrl = $redirected;
        }

        if ($code === 401) {
            throw new \Exception('The remote wiki refused these credentials.');
        }
        try {
            $data = $code === 200 ? $response->toArray(false) : [];
        } catch (\Throwable $th) {
            $data = [];
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
        if ($cookies === []) {
            throw new \Exception('The remote wiki opened no session.');
        }

        return [$baseUrl, implode('; ', $cookies)];
    }

    /**
     * @param array<string, mixed>      $job
     * @param array<string, mixed>|null $post
     *
     * @return array<mixed>
     */
    private function call(array $job, string $path, ?array $post = null): array
    {
        $options = $post === null ? [] : ['body' => $post];
        $response = $this->request($job, $post === null ? 'GET' : 'POST', $path, $options);
        try {
            $code = $response->getStatusCode();
        } catch (TransportExceptionInterface $th) {
            throw new \Exception("The remote wiki stopped answering on '$path': " . $th->getMessage(), 0, $th);
        }

        if ($code === 401 || $code === 403) {
            throw new \Exception('The remote wiki closed the session before the backup was fetched.');
        }

        try {
            $data = $response->toArray(false);
        } catch (\Throwable $th) {
            throw new \Exception("The remote wiki did not answer as an API on '$path' (HTTP $code).");
        }

        if ($code !== 200) {
            throw new \Exception("The remote wiki answered HTTP $code on '$path': " . ($data['error'] ?? 'no detail'));
        }

        return $data;
    }

    /**
     * A request to the remote wiki, pinned to the address the guard checked when there is one.
     *
     * @param array<string, mixed> $job
     * @param array<string, mixed> $options
     */
    private function request(array $job, string $method, string $path, array $options): ResponseInterface
    {
        $url = rtrim((string)$job['baseUrl'], '/') . '/?' . ltrim($path, '/');
        $options['headers'] = ($options['headers'] ?? []) + (empty($job['cookie']) ? [] : ['Cookie' => $job['cookie']]);
        $options += [
            'timeout' => self::REQUEST_TIMEOUT,
            'max_duration' => self::REQUEST_MAX_DURATION,
            'max_redirects' => 0,
        ];
        if ($this->guard !== null) {
            $options['resolve'] = $this->guard->resolveSafe($url, ['https', 'http']);
        }

        try {
            return $this->client()->request($method, $url, $options);
        } catch (TransportExceptionInterface $th) {
            throw new \Exception("The wiki at {$job['baseUrl']} cannot be reached: " . $th->getMessage(), 0, $th);
        }
    }

    /**
     * @throws \Exception when the disk receiving the download cannot hold that many bytes
     */
    private function assertLocalSpace(string $directory, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $free = $this->localFiles->freeSpace($directory);
        if ($free !== null && $free < $bytes) {
            throw new \Exception('Not enough free space here to download the backup' . ArchiveService::spaceDetail($bytes, $free) . '.');
        }
    }

    private function client(): HttpClientInterface
    {
        if ($this->client === null) {
            $this->client = HttpClient::create();
        }

        return $this->client;
    }

    private function tell(string $message): void
    {
        ($this->say)($message);
    }
}

<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Asks a remote wiki for a full archive and brings it back (first-class-binary 06). */
class RemoteWikiArchive
{
    public const REQUEST_TIMEOUT = 30;

    public const START_GRACE = 30;

    public const SETTLE_SECONDS = 10;

    public const IDENTIFY_TIMEOUT = 1800;

    private ?HttpClientInterface $client = null;

    private string $cookie = '';

    private string $baseUrl = '';

    /** @var callable(string): void */
    private $say;

    public function __construct(?callable $say = null)
    {
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

        $base = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        $path = rtrim((string)($parts['path'] ?? ''), '/');
        if ($path !== '' && !str_contains(basename($path), '.')) {
            $base .= $path;
        }

        return $base;
    }

    /**
     * Log in, ask for a full archive, wait for it, and write it to $destination.
     *
     * @throws \Exception naming what the remote wiki said, at every step
     */
    public function fetchInto(string $url, string $username, string $password, string $destination): void
    {
        $this->baseUrl = self::baseUrlOf($url);
        if ($this->baseUrl === '') {
            throw new \Exception("'$url' is not an address.");
        }

        $this->cookie = $this->login($username, $password);
        $this->tell('Signed in to ' . $this->baseUrl . ' as ' . $username);

        $this->assertCanArchive();
        $known = array_column($this->archives(), 'filename');

        $uid = $this->startArchive();
        $this->tell('The remote wiki is making a full archive');

        $this->waitForArchive($uid, $known);
        $candidate = $this->settledArchive($known);
        $this->tell(sprintf('Archive %s is %d bytes and has stopped growing', $candidate['filename'], $candidate['size']));

        $this->download((string)$candidate['filename'], $destination);
        $this->tell('Downloaded to ' . $destination);

        $this->deleteRemoteArchive((string)$candidate['filename']);
    }

    private function login(string $username, string $password): string
    {
        $response = $this->client()->request('POST', $this->apiUrl('api/login'), [
            'body' => ['username' => $username, 'password' => $password],
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        $code = $response->getStatusCode();
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

        return implode('; ', $cookies);
    }

    private function assertCanArchive(): void
    {
        $status = $this->call('api/archives/archivingStatus');
        if (!empty($status['canArchive'])) {
            return;
        }
        if (!empty($status['archiving'])) {
            throw new \Exception('The remote wiki is already making a backup.');
        }
        if (isset($status['canExec']) && !$status['canExec']) {
            throw new \Exception('The remote wiki cannot run a backup in the background, so it cannot be fetched from here.');
        }

        throw new \Exception('The remote wiki cannot make a backup right now.');
    }

    private function startArchive(): string
    {
        $data = $this->call('api/archives', [
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
     * @param list<string> $known filenames that were there before this run asked for one
     */
    private function waitForArchive(string $uid, array $known): void
    {
        $askedAt = time();
        $sawRunning = false;

        while (true) {
            $status = $this->call("api/archives/uidstatus/$uid");

            if (!empty($status['finished'])) {
                return;
            }
            if (!empty($status['stopped'])) {
                throw new \Exception('The backup was stopped on the remote wiki.');
            }
            if (empty($status['started'])) {
                if ($sawRunning) {
                    return;
                }
                if (time() - $askedAt > self::START_GRACE) {
                    if ($this->newArchiveAppeared($known)) {
                        return;
                    }

                    throw new \Exception('The remote wiki never started the backup it was asked to make.');
                }
            } else {
                $sawRunning = true;
            }

            sleep(2);
        }
    }

    /**
     * Whether the archive turned up anyway, for a wiki small enough to finish it before the first poll: the remote forgets a job as soon as it is done, so "never started" and "already over" look the same from here.
     *
     * @param list<string> $known
     */
    private function newArchiveAppeared(array $known): bool
    {
        foreach ($this->archives() as $archive) {
            if (($archive['type'] ?? '') === 'full' && !in_array($archive['filename'] ?? '', $known, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The new archive, once its size has held still: a zip grows on disk while it is written, and the listing shows it from the moment it is created.
     *
     * @param list<string> $known filenames that were there before this run asked for one
     *
     * @return array{filename: string, size: int}
     */
    private function settledArchive(array $known): array
    {
        $since = time();
        $name = '';
        $size = -1;
        $steadySince = time();

        while (true) {
            $candidate = null;
            foreach ($this->archives() as $archive) {
                if (($archive['type'] ?? '') === 'full' && !in_array($archive['filename'] ?? '', $known, true)) {
                    $candidate = $archive;
                    break;
                }
            }

            if ($candidate === null) {
                if (time() - $since > self::IDENTIFY_TIMEOUT) {
                    throw new \Exception('The remote wiki produced no backup.');
                }
                sleep(2);

                continue;
            }

            $seen = (int)($candidate['size'] ?? 0);
            if ($name !== (string)$candidate['filename'] || $size !== $seen) {
                $name = (string)$candidate['filename'];
                $size = $seen;
                $steadySince = time();
                sleep(2);

                continue;
            }

            if ($seen > 0 && time() - $steadySince >= self::SETTLE_SECONDS) {
                return ['filename' => $name, 'size' => $seen];
            }

            sleep(2);
        }
    }

    private function download(string $filename, string $destination): void
    {
        $response = $this->client()->request('GET', $this->apiUrl("api/archives/$filename"), [
            'headers' => ['Cookie' => $this->cookie],
            'buffer' => false,
            'timeout' => self::REQUEST_TIMEOUT,
            'max_duration' => 0,
        ]);

        $code = $response->getStatusCode();
        if ($code !== 200 && $code !== 206) {
            throw new \Exception("The remote wiki refused to send the backup file (HTTP $code).");
        }

        $part = $destination . '.part';
        $handle = fopen($part, 'wb');
        if ($handle === false) {
            throw new \Exception("Cannot write $part.");
        }

        try {
            foreach ($this->client()->stream($response) as $chunk) {
                if ($chunk->isTimeout() || $chunk->isLast()) {
                    continue;
                }
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        $zip = new \ZipArchive();
        if ($zip->open($part) !== true) {
            unlink($part);

            throw new \Exception('What came back is not a readable zip archive.');
        }
        $zip->close();

        if (!rename($part, $destination)) {
            throw new \Exception("Cannot put the archive at $destination.");
        }
    }

    private function deleteRemoteArchive(string $filename): void
    {
        try {
            $this->call('api/archives', ['action' => 'delete', 'filesnames' => [$filename]]);
            $this->tell("Removed $filename from the remote wiki");
        } catch (\Throwable $th) {
            $this->tell("Could not remove $filename from the remote wiki: " . $th->getMessage());
        }
    }

    /** @return list<array<string, mixed>> */
    private function archives(): array
    {
        return array_values(array_filter($this->call('api/archives'), 'is_array'));
    }

    /**
     * @param array<string, mixed>|null $post
     *
     * @return array<mixed>
     */
    private function call(string $path, ?array $post = null): array
    {
        $options = [
            'headers' => ['Cookie' => $this->cookie],
            'timeout' => self::REQUEST_TIMEOUT,
        ];
        if ($post !== null) {
            $options['body'] = $post;
        }

        $response = $this->client()->request($post === null ? 'GET' : 'POST', $this->apiUrl($path), $options);
        $code = $response->getStatusCode();

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

    private function apiUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/?' . ltrim($path, '/');
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

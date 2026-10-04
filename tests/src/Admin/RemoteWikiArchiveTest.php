<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/** Fetching another wiki's backup one step at a time, against a remote wiki played by a mock HTTP client. */
class RemoteWikiArchiveTest extends TestCase
{
    private string $folder = '';
    private string $zipBytes = '';
    private string $remoteName = '2026-08-20T13-29-28_remote-org_archive.zip';
    private bool $archiveMade = false;
    private bool $isAdmin = true;

    /** @var list<string> */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/yw-remote-archive-' . bin2hex(random_bytes(4));
        mkdir($this->folder, 0o755, true);
        $zipPath = $this->folder . '/source.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('files/photo.txt', str_repeat('remote bytes ', 200));
        $zip->close();
        $this->zipBytes = (string)file_get_contents($zipPath);
        unlink($zipPath);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->folder));
    }

    private function remote(): RemoteWikiArchive
    {
        $remote = new RemoteWikiArchive();
        (new \ReflectionProperty(RemoteWikiArchive::class, 'client'))->setValue($remote, new MockHttpClient(fn (string $method, string $url, array $options) => $this->answer($method, $url, $options)));

        return $remote;
    }

    /** @param array<string, mixed> $options */
    private function answer(string $method, string $url, array $options): MockResponse
    {
        $query = (string)parse_url($url, PHP_URL_QUERY);
        $this->calls[] = "$method $query";
        $json = static fn (mixed $data, array $info = []): MockResponse => new MockResponse((string)json_encode($data), $info + ['http_code' => 200]);

        if ($query === 'api/login') {
            return $json(['user' => 'RemoteAdmin', 'isAdmin' => $this->isAdmin], ['response_headers' => ['Set-Cookie: YesWiki-main=abc; path=/']]);
        }
        if ($query === 'api/archives/archivingStatus') {
            return $json(['canArchive' => true, 'canExec' => true, 'estimatedSize' => 1000]);
        }
        if ($query === 'api/archives' && $method === 'GET') {
            return $json($this->archiveMade ? [['filename' => $this->remoteName, 'type' => 'full', 'size' => \strlen($this->zipBytes)]] : []);
        }
        if ($query === 'api/archives' && $method === 'POST') {
            $this->archiveMade = true;

            return $json(['uid' => 'remoteuid', 'main' => true]);
        }
        if ($query === 'api/archives/uidstatus/remoteuid') {
            return $json(['started' => true, 'finished' => true, 'output' => 'END']);
        }
        if ($query === 'api/archives/' . $this->remoteName) {
            $headers = $options['normalized_headers']['range'][0] ?? '';
            $from = preg_match('/bytes=(\d+)-/', $headers, $matches) ? (int)$matches[1] : 0;
            if ($from > 0) {
                return new MockResponse(substr($this->zipBytes, $from), ['http_code' => 206, 'response_headers' => ['Accept-Ranges: bytes']]);
            }

            return new MockResponse($this->zipBytes, ['http_code' => 200, 'response_headers' => ['Accept-Ranges: bytes']]);
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    private function runUntilDone(RemoteWikiArchive $remote, array $job, string $part): array
    {
        $steps = [];
        for ($i = 0; $i < 20 && $job['step'] !== RemoteWikiArchive::STEP_DONE; $i++) {
            if (isset($job['candidateSince'])) {
                $job['candidateSince'] = time() - RemoteWikiArchive::SETTLE_SECONDS - 1;
            }
            $job = $remote->advance($job, $this->folder, $part, 5);
            $steps[] = $job['step'];
        }
        $job['steps'] = $steps;

        return $job;
    }

    public function testTheArchiveIsAskedForWaitedForDownloadedAndRemovedThere(): void
    {
        $remote = $this->remote();
        $part = $this->folder . '/download.part';

        $job = $this->runUntilDone($remote, $remote->start('https://remote.org/?PagePrincipale', 'RemoteAdmin', 'secret'), $part);

        $this->assertSame(RemoteWikiArchive::STEP_DONE, $job['step'], implode(', ', $job['steps']));
        $this->assertSame('https://remote.org', $job['baseUrl']);
        $this->assertSame($this->zipBytes, file_get_contents($part));
        $this->assertSame($this->remoteName, $job['filename'], 'a backup that names its source keeps its name');
        $this->assertContains('POST api/archives', $this->calls, 'the remote archive is deleted once downloaded');
        $this->assertSame('POST api/archives', end($this->calls));
    }

    public function testAnArchiveThatDoesNotNameItsSourceIsNamedAfterTheAddress(): void
    {
        $this->remoteName = '2026-08-20T13-29-28_archive.zip';
        $remote = $this->remote();

        $job = $this->runUntilDone($remote, $remote->start('https://remote.org/', 'RemoteAdmin', 'secret'), $this->folder . '/download.part');

        $this->assertSame('2026-08-20T13-29-28_remote-org_archive.zip', $job['filename']);
    }

    public function testADownloadCutShortResumesWhereItStopped(): void
    {
        $remote = $this->remote();
        $part = $this->folder . '/download.part';
        file_put_contents($part, substr($this->zipBytes, 0, 100));
        $job = $remote->start('https://remote.org', 'RemoteAdmin', 'secret');
        $job['step'] = RemoteWikiArchive::STEP_DOWNLOADING;
        $job['remoteFilename'] = $this->remoteName;
        $job['total'] = \strlen($this->zipBytes);

        $job = $remote->advance($job, $this->folder, $part, 5);

        $this->assertSame(RemoteWikiArchive::STEP_CLEANING, $job['step']);
        $this->assertSame($this->zipBytes, file_get_contents($part));
    }

    public function testSomeoneWhoIsNotAnAdministratorIsTurnedAway(): void
    {
        $this->isAdmin = false;

        $this->expectExceptionMessage('is not an administrator');
        $this->remote()->start('https://remote.org', 'RemoteAdmin', 'secret');
    }

    public function testAnAddressInsideThePrivateNetworkIsNeverReached(): void
    {
        $remote = new RemoteWikiArchive(null, new LocalFiles(), new SsrfUrlValidator());

        $this->expectExceptionMessage('private or reserved address');
        $remote->start('http://192.168.1.10/', 'RemoteAdmin', 'secret');
    }
}
